import { onBeforeUnmount, onMounted } from 'vue'
import { api } from '@/lib/api'

type Scope = 'market' | 'property' | 'user'
type State = {
  revision: string
  client: { key: string; cluster: string } | null
  channel: string
  auth: string | null
}

export function useLiveRefresh(scope: Scope, refresh: () => unknown | Promise<unknown>) {
  let active = false
  let reading = false
  let disabled = false
  let readAgain = false
  let revision: string | undefined
  let channel = ''
  let socket: WebSocket | undefined
  let timer: number | undefined
  let refreshTimer: number | undefined
  let retryAt = 0

  function rerender() {
    if (refreshTimer !== undefined) window.clearTimeout(refreshTimer)
    refreshTimer = window.setTimeout(() => {
      if (active && !document.hidden && navigator.onLine) {
        void Promise.resolve().then(refresh).then(result => {
          if (result === false && active) rerender()
        }).catch(() => {})
      }
    }, 180)
  }

  async function state(socketId?: string) {
    return (await api.get<State>('/live/' + scope, { params: { socket: socketId }, timeout: 5000 })).data
  }

  function disconnect() {
    const current = socket
    socket = undefined
    current?.close()
  }

  function connect(config: NonNullable<State['client']>) {
    if (socket || Date.now() < retryAt) return
    const address = 'wss://ws-' + config.cluster + '.pusher.com/app/' + encodeURIComponent(config.key)
    const current = new WebSocket(address + '?protocol=7&client=js&version=1.0')
    socket = current
    current.onclose = () => {
      if (socket === current) {
        socket = undefined
        retryAt = Date.now() + 10_000
      }
    }
    current.onmessage = async ({ data }) => {
      if (!active || socket !== current) return
      try {
        const packet = JSON.parse(String(data))
        if (packet.event === 'pusher:connection_established') {
          const socketId = JSON.parse(packet.data).socket_id
          const snapshot = await state(scope === 'market' ? undefined : socketId)
          if (!active || socket !== current) return
          if (snapshot.channel !== channel) return disconnect()
          current.send(JSON.stringify({ event: 'pusher:subscribe', data: {
            channel, ...(snapshot.auth ? { auth: snapshot.auth } : {}),
          } }))
        } else if (packet.event === 'pusher:ping') {
          current.send(JSON.stringify({ event: 'pusher:pong', data: {} }))
        } else if (packet.channel === channel && (packet.event === 'changed' || packet.event === 'pusher_internal:subscription_succeeded')) {
          void synchronize()
        } else if (packet.event === 'pusher:error') {
          current.close()
        }
      } catch {
        current.close()
      }
    }
  }

  async function synchronize(force = false) {
    if (!active || disabled || document.hidden || !navigator.onLine) return
    if (reading) {
      readAgain = true
      return
    }
    reading = true
    try {
      const snapshot = await state()
      if (!active) return
      if (force || (revision !== undefined && snapshot.revision !== revision)) rerender()
      revision = snapshot.revision
      if (channel !== snapshot.channel) {
        disconnect()
        channel = snapshot.channel
      }
      if (snapshot.client) connect(snapshot.client)
    } catch (error) {
      const status = (error as { status?: number }).status
      if (status === 404) {
        disabled = true
        disconnect()
      }
      if (status === 403) {
        disconnect()
        if (revision !== undefined) rerender()
        revision = undefined
      }
    } finally {
      reading = false
      if (readAgain) {
        readAgain = false
        void synchronize()
      }
    }
  }

  const resume = () => void synchronize(true)
  function visibility() {
    if (document.hidden) disconnect()
    else resume()
  }
  onMounted(() => {
    active = true
    void synchronize()
    timer = window.setInterval(() => void synchronize(), 12_000)
    window.addEventListener('online', resume)
    document.addEventListener('visibilitychange', visibility)
  })
  onBeforeUnmount(() => {
    active = false
    window.clearInterval(timer)
    window.clearTimeout(refreshTimer)
    window.removeEventListener('online', resume)
    document.removeEventListener('visibilitychange', visibility)
    disconnect()
  })
}

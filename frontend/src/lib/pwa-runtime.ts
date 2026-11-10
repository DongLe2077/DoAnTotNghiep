import { readonly, ref } from 'vue'
import { reloadBlockReason, updateLocked } from '@/lib/reload-safety'

type Connection = 'online' | 'offline' | 'unavailable' | 'restored'
const connection = ref<Connection>(typeof navigator === 'undefined' || navigator.onLine !== false ? 'online' : 'offline')
const updateAvailable = ref(false), offlineReady = ref(false), updateMessage = ref(''), registrationError = ref(false)
let registration: ServiceWorkerRegistration | undefined, initialized = false, lockToken = '', lockTimer: ReturnType<typeof setTimeout> | undefined
export function reportApiConnection(reachable: boolean) {
  if (typeof navigator === 'undefined') return
  if (navigator.onLine === false) { connection.value = 'offline'; return }
  if (!reachable) connection.value = 'unavailable'
  else if (connection.value === 'unavailable' || connection.value === 'offline') connection.value = 'restored'
}
function unlock() { updateLocked.value = false; lockToken = ''; if (lockTimer) clearTimeout(lockTimer) }
export function initializePwaRuntime() {
  if (initialized) return
  initialized = true
  window.addEventListener('offline', () => { connection.value = 'offline' })
  window.addEventListener('online', () => { connection.value = 'restored' })
  if (!import.meta.env.PROD || !('serviceWorker' in navigator) || !window.isSecureContext) return
  let controlled = !!navigator.serviceWorker.controller
  navigator.serviceWorker.addEventListener('message', event => {
    if (event.data?.type === 'PWA_PREPARE_UPDATE' && event.ports[0]) {
      const ready = !updateLocked.value && !reloadBlockReason()
      if (ready) {
        lockToken = event.data.token; updateLocked.value = true
        lockTimer = setTimeout(unlock, 30_000)
      }
      event.ports[0].postMessage({ ready }); event.ports[0].close()
    } else if (event.data?.type === 'PWA_RELEASE_UPDATE' && event.data.token === lockToken) unlock()
  })
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (updateLocked.value) { location.reload(); return }
    if (controlled) { updateAvailable.value = true; updateMessage.value = 'Bản mới đã sẵn sàng. Bạn có thể tải lại khi hoàn tất công việc.' }
    controlled = true
  })
  void navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' }).then(value => {
    registration = value
    const inspect = () => { if (value.waiting && navigator.serviceWorker.controller) updateAvailable.value = true }
    inspect()
    value.addEventListener('updatefound', () => value.installing?.addEventListener('statechange', inspect))
    void navigator.serviceWorker.ready.then(() => { offlineReady.value = true })
    const check = () => { if (navigator.onLine && document.visibilityState === 'visible') void value.update().catch(() => {}) }
    window.addEventListener('focus', check)
    document.addEventListener('visibilitychange', check)
    setInterval(check, 60 * 60 * 1000)
  }).catch(() => { registrationError.value = true })
}
async function applyUpdate() {
  const reason = reloadBlockReason()
  if (reason || updateLocked.value) { updateMessage.value = reason; return }
  const waiting = registration?.waiting
  if (!waiting) { location.reload(); return }
  updateMessage.value = 'Đang kiểm tra các tab Nhà Trọ đang mở…'
  const ready = await new Promise<boolean>(resolve => {
    const channel = new MessageChannel()
    const timer = setTimeout(() => { channel.port1.close(); resolve(false) }, 7000)
    channel.port1.onmessage = event => { clearTimeout(timer); channel.port1.close(); resolve(event.data?.ready === true) }
    try { waiting.postMessage({ type: 'PWA_APPLY_UPDATE' }, [channel.port2]) }
    catch { clearTimeout(timer); channel.port1.close(); resolve(false) }
  })
  if (!ready) updateMessage.value = 'Một tab còn đang thao tác hoặc chưa phản hồi. Hãy hoàn tất công việc, đóng tab không dùng rồi thử lại.'
}
export function usePwaRuntime() {
  return { connection: readonly(connection), updateAvailable: readonly(updateAvailable), offlineReady, updateMessage: readonly(updateMessage), registrationError: readonly(registrationError), updateLocked: readonly(updateLocked), applyUpdate }
}

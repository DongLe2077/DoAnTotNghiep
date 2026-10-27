import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { advanceSessionRevision, api, ApiError, prepareCsrf, toApiError } from '@/lib/api'
import { isUser, type PasswordChange, type User } from '@/types/auth'

type AuthStatus = 'idle' | 'loading' | 'authenticated' | 'anonymous' | 'error'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const status = ref<AuthStatus>('idle')
  const connectionError = ref('')
  const isAuthenticated = computed(() => user.value !== null)
  let initialization: Promise<User | null> | null = null
  let revision = 0
  let channel: BroadcastChannel | null = null

  function setUser(value: unknown): User {
    if (!isUser(value)) throw new ApiError(502, 'Thông tin tài khoản từ máy chủ không hợp lệ. Vui lòng thử lại.')
    advanceSessionRevision()
    user.value = value
    status.value = 'authenticated'
    connectionError.value = ''
    return value
  }

  function clearSession() {
    revision++
    advanceSessionRevision()
    user.value = null
    status.value = 'anonymous'
    connectionError.value = ''
  }

  async function initialize(force = false): Promise<User | null> {
    if (initialization) return initialization
    if (!force && (status.value === 'authenticated' || status.value === 'anonymous')) return user.value
    const startedAt = revision
    status.value = 'loading'
    initialization = (async () => {
      try {
        const response = await api.get<{ data: unknown }>('/auth/me')
        if (revision !== startedAt) return user.value
        return setUser(response.data.data)
      } catch (error) {
        if (revision !== startedAt) return user.value
        const normalized = toApiError(error)
        if (normalized.status === 401) {
          clearSession()
          return null
        }
        if (revision === startedAt) {
          status.value = 'error'
          connectionError.value = normalized.message
        }
        throw normalized
      } finally {
        initialization = null
      }
    })()
    return initialization
  }

  async function login(username: string, password: string): Promise<User> {
    await prepareCsrf()
    const response = await api.post<{ data: unknown }>('/auth/login', { username: username.trim(), password })
    revision++
    const signedIn = setUser(response.data.data)
    channel?.postMessage('session-changed')
    return signedIn
  }

  async function logout(): Promise<void> {
    try {
      await prepareCsrf()
      await api.post('/auth/logout')
    } catch (error) {
      if (toApiError(error).status !== 401) throw error
    }
    clearSession()
    channel?.postMessage('session-changed')
  }

  async function changePassword(input: PasswordChange): Promise<void> {
    await prepareCsrf()
    await api.put('/auth/password', input)
    clearSession()
    channel?.postMessage('session-changed')
  }

  function listenForSessionChanges(listener: () => void) {
    if (typeof BroadcastChannel === 'undefined') return () => {}
    channel = new BroadcastChannel('rental-management-session')
    channel.onmessage = event => {
      if (event.data === 'session-changed') listener()
    }
    return () => { channel?.close(); channel = null }
  }

  return { user, status, isAuthenticated, connectionError, initialize, login, logout, changePassword, clearSession, listenForSessionChanges }
})

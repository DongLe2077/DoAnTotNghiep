import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { AxiosError, AxiosHeaders, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios'
import { api, ApiError, onSessionExpired } from '@/lib/api'
import { useAuthStore } from './auth'

const owner = { id: 1, username: 'unit_owner', role: 'owner', tenant_id: null, is_active: true }
function response(config: InternalAxiosRequestConfig, data: unknown, status = 200): AxiosResponse {
  return { data, status, statusText: '', headers: new AxiosHeaders(), config }
}
function rejected(config: InternalAxiosRequestConfig, status: number) {
  return new AxiosError('Request failed', 'ERR_BAD_RESPONSE', config, undefined, response(config, {}, status))
}

beforeEach(() => { setActivePinia(createPinia()) })

describe('session boundary', () => {
  it('ignores an old me response that expires after a new login succeeds', async () => {
    const auth = useAuthStore()
    const expired = vi.fn(() => auth.clearSession())
    const unsubscribe = onSessionExpired(expired)
    let failOldRequest: (() => void) | undefined
    api.defaults.adapter = config => {
      if (config.url === '/auth/me') return new Promise((_resolve, reject) => {
        failOldRequest = () => reject(rejected(config, 401))
      })
      return Promise.resolve(response(config, config.url === '/auth/login' ? { data: owner } : {}, config.url === '/auth/csrf-cookie' ? 204 : 200))
    }
    try {
      const oldCheck = auth.initialize()
      await vi.waitFor(() => expect(failOldRequest).toBeTypeOf('function'))
      await auth.login('unit_owner', 'fixture-password')
      failOldRequest!()
      await oldCheck
      expect(expired).not.toHaveBeenCalled()
      expect(auth.user).toEqual(owner)
      expect(auth.status).toBe('authenticated')
    } finally { unsubscribe() }
  })

  it('keeps the authenticated user when logout has a network failure', async () => {
    const auth = useAuthStore()
    api.defaults.adapter = config => config.url === '/auth/logout'
      ? Promise.reject(new AxiosError('Network error', 'ERR_NETWORK', config))
      : Promise.resolve(response(config, config.url === '/auth/login' ? { data: owner } : {}))
    await auth.login('unit_owner', 'fixture-password')
    await expect(auth.logout()).rejects.toMatchObject({ status: 0 })
    expect(auth.user).toEqual(owner)
    expect(auth.isAuthenticated).toBe(true)
  })

  it('keeps a newly synchronized session when a previous-session API later expires', async () => {
    const auth = useAuthStore()
    const syncedOwner = { ...owner, id: 2, username: 'other_tab_owner' }
    const expired = vi.fn(() => auth.clearSession())
    const unsubscribe = onSessionExpired(expired)
    let failPreviousSession: (() => void) | undefined
    api.defaults.adapter = config => {
      if (config.url === '/owner/access') return new Promise((_resolve, reject) => {
        failPreviousSession = () => reject(rejected(config, 401))
      })
      return Promise.resolve(response(config, config.url === '/auth/me' ? { data: syncedOwner } : config.url === '/auth/login' ? { data: owner } : {}))
    }
    try {
      await auth.login('unit_owner', 'fixture-password')
      const previousRequest = api.get('/owner/access')
      await vi.waitFor(() => expect(failPreviousSession).toBeTypeOf('function'))
      await auth.initialize(true)
      failPreviousSession!()
      await expect(previousRequest).rejects.toMatchObject({ status: 401 })
      expect(expired).not.toHaveBeenCalled()
      expect(auth.user).toEqual(syncedOwner)
      expect(auth.status).toBe('authenticated')
    } finally { unsubscribe() }
  })

  it('never automatically replays a mutation after CSRF rejection', async () => {
    const auth = useAuthStore()
    const calls: string[] = []
    api.defaults.adapter = config => {
      calls.push(`${config.method} ${config.url}`)
      return config.url === '/auth/login' ? Promise.reject(rejected(config, 419)) : Promise.resolve(response(config, {}, 204))
    }
    await expect(auth.login('unit_owner', 'fixture-password')).rejects.toBeInstanceOf(ApiError)
    expect(calls).toEqual(['get /auth/csrf-cookie', 'post /auth/login'])
    expect(auth.user).toBeNull()
  })

  it('rejects unexpected role data instead of granting a workspace', async () => {
    const auth = useAuthStore()
    api.defaults.adapter = config => Promise.resolve(response(config, config.url === '/auth/login' ? { data: { ...owner, role: 'admin' } } : {}))
    await expect(auth.login('unit_owner', 'fixture-password')).rejects.toMatchObject({ status: 502 })
    expect(auth.user).toBeNull()
  })
})

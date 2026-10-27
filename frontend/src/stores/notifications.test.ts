import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { AxiosError, AxiosHeaders, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios'
import { api, ApiError } from '@/lib/api'
import type { User } from '@/types/auth'
import type { AppNotification } from '@/types/notifications'
import { useAuthStore } from './auth'
import { useNotificationsStore } from './notifications'

const owner: User = { id: 1, username: 'first_owner', role: 'owner', tenant_id: null, is_active: true }
const tenant: User = { id: 2, username: 'second_tenant', role: 'tenant', tenant_id: 1, is_active: true }
const originalAdapter = api.defaults.adapter

function notification(id = 1, title = 'Thông báo riêng của tài khoản trước', read = false): AppNotification {
  return { id, type: 'maintenance_updated', data: { title, body: 'Nội dung cập nhật sửa chữa' },
    invoice_id: null, maintenance_request_id: 1, is_read: read,
    read_at: read ? '2026-10-22 08:30:00' : null, created_at: '2026-10-22 08:00:00' }
}
function page(items: AppNotification[], pageNumber = 1, lastPage = 1, total = items.length) {
  return { data: items, meta: { current_page: pageNumber, last_page: lastPage, per_page: 15, total } }
}
function response(config: InternalAxiosRequestConfig, data: unknown, status = 200): AxiosResponse {
  return { data, status, statusText: '', headers: new AxiosHeaders(), config }
}
function rejected(config: InternalAxiosRequestConfig, status: number) {
  return new AxiosError('Request failed', 'ERR_BAD_RESPONSE', config, undefined, response(config, {}, status))
}
function setIdentity(user: User) {
  const auth = useAuthStore()
  auth.user = { ...user }
  auth.status = 'authenticated'
}
function deferredResponse() {
  let resolve!: (value: AxiosResponse) => void
  const promise = new Promise<AxiosResponse>(done => { resolve = done })
  return { promise, resolve }
}

beforeEach(() => { setActivePinia(createPinia()); setIdentity(owner) })
afterEach(() => { api.defaults.adapter = originalAdapter })

describe('notification privacy and request ordering', () => {
  it('clears identity-scoped state immediately and ignores delayed old list and unread-count responses', async () => {
    const store = useNotificationsStore()
    const oldList = deferredResponse()
    const oldCount = deferredResponse()
    let oldListConfig: InternalAxiosRequestConfig | undefined
    let oldCountConfig: InternalAxiosRequestConfig | undefined
    let oldIdentity = true
    api.defaults.adapter = config => {
      if (config.url === '/notifications' && oldIdentity) { oldListConfig = config; return oldList.promise }
      if (config.url === '/notifications/unread-count' && oldIdentity) { oldCountConfig = config; return oldCount.promise }
      return Promise.resolve(response(config, config.url === '/notifications' ? page([notification(2, 'Thông báo của người thuê mới')]) : { data: { unread_count: 1 } }))
    }
    const list = store.load()
    const count = store.refreshCount()
    await vi.waitFor(() => { expect(oldListConfig).toBeDefined(); expect(oldCountConfig).toBeDefined() })

    oldIdentity = false
    setIdentity(tenant)
    expect(store.items).toEqual([])
    expect(store.unreadCount).toBeNull()
    expect(store.loading).toBe(false)
    expect(store.filter).toEqual({ unreadOnly: false, type: '' })
    await Promise.all([store.load(), store.refreshCount()])
    oldList.resolve(response(oldListConfig!, page([notification()])))
    oldCount.resolve(response(oldCountConfig!, { data: { unread_count: 99 } }))
    await Promise.all([list, count])

    expect(store.items.map(item => item.id)).toEqual([2])
    expect(store.unreadCount).toBe(1)
    expect(store.error).toBe('')
    expect(store.countError).toBe(false)
  })

  it('keeps the latest filter result when an older unfiltered response finishes later', async () => {
    const store = useNotificationsStore()
    const oldList = deferredResponse()
    let oldConfig: InternalAxiosRequestConfig | undefined
    api.defaults.adapter = config => {
      if (config.url === '/notifications' && !config.params.unread_only) { oldConfig = config; return oldList.promise }
      return Promise.resolve(response(config, config.url === '/notifications' ? page([notification(2, 'Thông báo chưa đọc mới')]) : { data: { unread_count: 1 } }))
    }
    const first = store.load({ unreadOnly: false })
    await vi.waitFor(() => expect(oldConfig).toBeDefined())
    await store.load({ unreadOnly: true, type: 'maintenance_updated' })
    oldList.resolve(response(oldConfig!, page([notification()])))
    await first

    expect(store.items.map(item => item.id)).toEqual([2])
    expect(store.filter).toEqual({ unreadOnly: true, type: 'maintenance_updated' })
    expect(store.error).toBe('')
    expect(store.loading).toBe(false)
  })

  it('removes cached private rows while refreshing and keeps them cleared after a server failure', async () => {
    const store = useNotificationsStore()
    let failList = false
    api.defaults.adapter = config => config.url === '/notifications' && failList
      ? Promise.reject(rejected(config, 503))
      : Promise.resolve(response(config, config.url === '/notifications' ? page([notification()]) : { data: { unread_count: 1 } }))
    await store.load()
    expect(store.items).toHaveLength(1)
    failList = true
    const refresh = store.refresh()
    expect(store.items).toEqual([])
    await refresh
    expect(store.items).toEqual([])
    expect(store.errorStatus).toBe(503)
    expect(store.error).not.toBe('')
    expect(store.loading).toBe(false)
  })

  it('rejects malformed API lists instead of retaining previously visible notification content', async () => {
    const store = useNotificationsStore()
    let malformed = false
    api.defaults.adapter = config => Promise.resolve(response(config, config.url === '/notifications'
      ? malformed ? { data: [{ ...notification(), data: { title: 42, body: 'Bad title' } }], meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 } } : page([notification()])
      : { data: { unread_count: 1 } }))
    await store.load()
    malformed = true
    await store.refresh()
    expect(store.items).toEqual([])
    expect(store.errorStatus).toBe(502)
  })

  it('recovers a page made out of range while preserving unread/type filters', async () => {
    const store = useNotificationsStore()
    const requested: unknown[] = []
    api.defaults.adapter = config => {
      if (config.url === '/notifications') {
        requested.push({ ...config.params })
        return Promise.resolve(response(config, config.params.page === 2 ? page([], 2, 1, 1) : page([notification()], 1, 1, 1)))
      }
      return Promise.resolve(response(config, { data: { unread_count: 1 } }))
    }
    await store.load({ page: 2, unreadOnly: true, type: 'maintenance_updated' })
    expect(requested).toEqual([
      { page: 2, per_page: 15, unread_only: 1, type: 'maintenance_updated' },
      { page: 1, per_page: 15, unread_only: 1, type: 'maintenance_updated' },
    ])
    expect(store.page).toBe(1)
    expect(store.items.map(item => item.id)).toEqual([1])
    expect(store.loading).toBe(false)
  })
})

describe.each(['one', 'all'] as const)('explicit mark-%s-read actions', kind => {
  it('deduplicates simultaneous clicks, sends a CSRF-rejected write once, and allows an explicit retry', async () => {
    const store = useNotificationsStore()
    const calls: string[] = []
    let rejectWrite = true
    let isRead = false
    api.defaults.adapter = config => {
      calls.push(`${config.method} ${config.url}`)
      if (config.url === '/auth/csrf-cookie') return Promise.resolve(response(config, {}, 204))
      if (config.method === 'patch' || config.method === 'post') {
        if (rejectWrite) return Promise.reject(rejected(config, 419))
        isRead = true
        return Promise.resolve(response(config, kind === 'one' ? { data: notification(1, 'Thông báo', true) } : { data: { updated_count: 1 } }))
      }
      return Promise.resolve(response(config, config.url === '/notifications' ? page([notification(1, 'Thông báo', isRead)]) : { data: { unread_count: isRead ? 0 : 1 } }))
    }
    await store.load()
    await store.refreshCount()
    calls.length = 0
    const run = () => kind === 'one' ? store.markRead(1) : store.markAllRead()
    const outcomes = await Promise.allSettled([run(), run()])
    expect(outcomes).toMatchObject([
      { status: 'rejected', reason: { status: 419 } },
      { status: 'rejected', reason: { status: 419 } },
    ])
    expect(calls).toEqual(['get /auth/csrf-cookie', kind === 'one' ? 'patch /notifications/1/read' : 'post /notifications/read-all'])
    expect(store.actionError).not.toBe('')
    expect(store.items[0]?.is_read).toBe(false)
    expect(store.busyIds).toEqual([])
    expect(store.readingAll).toBe(false)

    rejectWrite = false
    await run()
    expect(calls.filter(call => call.startsWith('patch ') || call.startsWith('post '))).toHaveLength(2)
    expect(calls.filter(call => call === 'get /auth/csrf-cookie')).toHaveLength(2)
    expect(store.items[0]?.is_read).toBe(true)
    expect(store.unreadCount).toBe(0)
    expect(store.actionError).toBe('')
  })

  it('does not dispatch a mutation when the account changes while CSRF preparation is pending', async () => {
    const store = useNotificationsStore()
    const csrf = deferredResponse()
    let csrfConfig: InternalAxiosRequestConfig | undefined
    const writes: string[] = []
    api.defaults.adapter = config => {
      if (config.url === '/auth/csrf-cookie') { csrfConfig = config; return csrf.promise }
      if (config.method !== 'get') writes.push(String(config.url))
      return Promise.resolve(response(config, {}))
    }
    const pending = kind === 'one' ? store.markRead(1) : store.markAllRead()
    const settled = pending.then(() => null, error => error)
    await vi.waitFor(() => expect(csrfConfig).toBeDefined())
    setIdentity(tenant)
    csrf.resolve(response(csrfConfig!, {}, 204))

    expect(await settled).toMatchObject({ status: 401 })
    expect(writes).toEqual([])
    expect(store.items).toEqual([])
    expect(store.unreadCount).toBeNull()
    expect(store.actionError).toBe('')
    expect(store.busyIds).toEqual([])
    expect(store.readingAll).toBe(false)
  })

  it('rejects an old action if the account changes during its follow-up refresh', async () => {
    const store = useNotificationsStore()
    const refresh = deferredResponse()
    let refreshConfig: InternalAxiosRequestConfig | undefined
    api.defaults.adapter = config => {
      if (config.url === '/auth/csrf-cookie') return Promise.resolve(response(config, {}, 204))
      if (config.url === '/notifications') { refreshConfig = config; return refresh.promise }
      if (config.method === 'patch' || config.method === 'post') return Promise.resolve(response(config, { data: notification(1, 'Thông báo riêng', true) }))
      return Promise.resolve(response(config, { data: { unread_count: 0 } }))
    }
    const pending = kind === 'one' ? store.markRead(1) : store.markAllRead()
    const settled = pending.then(() => null, error => error)
    await vi.waitFor(() => expect(refreshConfig).toBeDefined())
    setIdentity(tenant)
    refresh.resolve(response(refreshConfig!, page([notification(1, 'Thông báo riêng', true)])))

    expect(await settled).toBeInstanceOf(ApiError)
    expect(await settled).toMatchObject({ status: 401 })
    expect(store.items).toEqual([])
    expect(store.unreadCount).toBeNull()
    expect(store.actionError).toBe('')
  })
})

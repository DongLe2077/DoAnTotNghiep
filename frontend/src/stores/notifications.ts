import { reactive, ref, watch } from 'vue'
import { defineStore } from 'pinia'
import { api, ApiError, prepareCsrf, toApiError } from '@/lib/api'
import { useAuthStore } from './auth'
import { isNotification, isNotificationPage, type AppNotification, type NotificationType } from '@/types/notifications'

type FilterOptions = { page?: number; unreadOnly?: boolean; type?: NotificationType | '' }

export const useNotificationsStore = defineStore('notifications', () => {
  const auth = useAuthStore()
  const items = ref<AppNotification[]>([])
  const loading = ref(false)
  const error = ref('')
  const errorStatus = ref(0)
  const actionError = ref('')
  const unreadCount = ref<number | null>(null)
  const countError = ref(false)
  const busyIds = ref<number[]>([])
  const readingAll = ref(false)
  const page = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const filter = reactive<{ unreadOnly: boolean; type: NotificationType | '' }>({ unreadOnly: false, type: '' })
  let scope = 0
  let listSequence = 0
  let countSequence = 0
  let listController: AbortController | null = null
  let countController: AbortController | null = null
  let countFlight: Promise<void> | null = null
  const readFlights = new Map<number, Promise<void>>()
  let readAllFlight: Promise<void> | null = null

  function invalidateCount() {
    countSequence++
    countController?.abort()
    countController = null
    countFlight = null
  }

  function reset() {
    scope++
    listSequence++
    listController?.abort()
    listController = null
    invalidateCount()
    items.value = []
    unreadCount.value = null
    countError.value = false
    error.value = ''; errorStatus.value = 0; actionError.value = ''
    loading.value = false; readingAll.value = false; busyIds.value = []
    page.value = 1; lastPage.value = 1; total.value = 0
    filter.unreadOnly = false; filter.type = ''
    readFlights.clear(); readAllFlight = null
  }

  watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', reset, { immediate: true, flush: 'sync' })

  function isCurrent(startedAt: number) { return startedAt === scope && !!auth.user }
  function requireCurrent(startedAt: number) {
    if (!isCurrent(startedAt)) throw new ApiError(401, 'Phiên đăng nhập đã thay đổi. Vui lòng đăng nhập lại.')
  }

  function refreshCount(force = false): Promise<void> {
    if (!auth.user) { unreadCount.value = null; return Promise.resolve() }
    if (force) invalidateCount()
    if (countFlight) return countFlight
    const startedAt = scope
    const sequence = ++countSequence
    const controller = new AbortController()
    countController = controller
    const pending: Promise<void> = Promise.resolve().then(async () => {
      try {
        const response = await api.get<{ data: { unread_count: unknown } }>('/notifications/unread-count', { signal: controller.signal })
        if (!isCurrent(startedAt) || sequence !== countSequence) return
        const count = response.data?.data?.unread_count
        if (!Number.isSafeInteger(count) || Number(count) < 0) throw new ApiError(502, 'Số thông báo chưa đọc chưa được cập nhật.')
        unreadCount.value = Number(count)
        countError.value = false
      } catch {
        if (isCurrent(startedAt) && sequence === countSequence) countError.value = true
      } finally {
        if (countFlight === pending) { countFlight = null; countController = null }
      }
    })
    countFlight = pending
    return pending
  }

  async function load(options: FilterOptions = {}): Promise<void> {
    if (!auth.user) { reset(); return }
    if (options.unreadOnly !== undefined) filter.unreadOnly = options.unreadOnly
    if (options.type !== undefined) filter.type = options.type
    page.value = Number.isSafeInteger(options.page) && Number(options.page) > 0 ? Number(options.page) : options.page === undefined ? page.value : 1
    const startedAt = scope
    const sequence = ++listSequence
    listController?.abort()
    const controller = new AbortController()
    listController = controller
    items.value = []
    loading.value = true; error.value = ''; errorStatus.value = 0
    try {
      const response = await api.get<unknown>('/notifications', {
        params: { page: page.value, per_page: 15, ...(filter.unreadOnly ? { unread_only: 1 } : {}), ...(filter.type ? { type: filter.type } : {}) },
        signal: controller.signal,
      })
      if (!isCurrent(startedAt) || sequence !== listSequence) return
      if (!isNotificationPage(response.data)) throw new ApiError(502, 'Thông tin thông báo chưa hợp lệ. Vui lòng tải lại.')
      const result = response.data
      if (result.meta.current_page > result.meta.last_page) {
        await load({ page: result.meta.last_page })
        return
      }
      items.value = result.data
      page.value = result.meta.current_page
      lastPage.value = result.meta.last_page
      total.value = result.meta.total
      void refreshCount()
    } catch (caught) {
      if (!isCurrent(startedAt) || sequence !== listSequence) return
      const normalized = toApiError(caught)
      items.value = []
      error.value = normalized.message
      errorStatus.value = normalized.status
    } finally {
      if (isCurrent(startedAt) && sequence === listSequence) { loading.value = false; listController = null }
    }
  }

  async function refresh() { await load() }

  function markRead(id: number): Promise<void> {
    if (readFlights.has(id)) return readFlights.get(id)!
    const startedAt = scope
    if (!auth.user) return Promise.reject(new ApiError(401, 'Vui lòng đăng nhập để đọc thông báo.'))
    if (!Number.isSafeInteger(id) || id <= 0) return Promise.reject(new ApiError(422, 'Thông báo không hợp lệ.'))
    actionError.value = ''
    busyIds.value = [...busyIds.value, id]
    const pending: Promise<void> = Promise.resolve().then(async () => {
      try {
        requireCurrent(startedAt)
        await prepareCsrf()
        requireCurrent(startedAt)
        const response = await api.patch<{ data: unknown }>(`/notifications/${id}/read`)
        requireCurrent(startedAt)
        if (!isNotification(response.data.data)) throw new ApiError(502, 'Chưa xác nhận được trạng thái đã đọc. Vui lòng tải lại.')
        items.value = filter.unreadOnly ? items.value.filter(item => item.id !== id) : items.value.map(item => item.id === id ? response.data.data as AppNotification : item)
        await Promise.all([refreshCount(true), load()])
        requireCurrent(startedAt)
      } catch (caught) {
        const normalized = toApiError(caught)
        if (isCurrent(startedAt)) {
          if (normalized.status === 404 || normalized.status === 403) {
            items.value = items.value.filter(item => item.id !== id)
            await Promise.all([refreshCount(true), load()])
            if (isCurrent(startedAt)) actionError.value = 'Thông báo không còn khả dụng hoặc bạn không còn quyền xem. Danh sách đã được tải lại.'
          } else actionError.value = normalized.message
        }
        throw normalized
      } finally {
        if (readFlights.get(id) === pending) readFlights.delete(id)
        if (isCurrent(startedAt)) busyIds.value = busyIds.value.filter(value => value !== id)
      }
    })
    readFlights.set(id, pending)
    return pending
  }

  function markAllRead(): Promise<void> {
    if (readAllFlight) return readAllFlight
    if (!auth.user) return Promise.reject(new ApiError(401, 'Vui lòng đăng nhập để đọc thông báo.'))
    const startedAt = scope
    readingAll.value = true; actionError.value = ''
    const pending: Promise<void> = Promise.resolve().then(async () => {
      try {
        requireCurrent(startedAt)
        await prepareCsrf()
        requireCurrent(startedAt)
        await api.post('/notifications/read-all')
        requireCurrent(startedAt)
        await Promise.all([refreshCount(true), load()])
        requireCurrent(startedAt)
      } catch (caught) {
        const normalized = toApiError(caught)
        if (isCurrent(startedAt)) actionError.value = normalized.message
        throw normalized
      } finally {
        if (readAllFlight === pending) readAllFlight = null
        if (isCurrent(startedAt)) readingAll.value = false
      }
    })
    readAllFlight = pending
    return pending
  }

  return { items, loading, error, errorStatus, actionError, unreadCount, countError, busyIds, readingAll, page, lastPage, total, filter, load, refresh, refreshCount, markRead, markAllRead, reset }
})

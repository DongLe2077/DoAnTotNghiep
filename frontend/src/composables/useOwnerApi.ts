import { onBeforeUnmount, ref, watch } from 'vue'
import { api, ApiError, prepareCsrf, toApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { PageResult } from '@/types/owner'

export function useOwnerApi() {
  const auth = useAuthStore()
  const busy = ref(false)
  const error = ref('')
  const errors = ref<Record<string, string[]>>({})
  let alive = true
  let revision = 0
  const controllers = new Set<AbortController>()
  function clearError() { error.value = ''; errors.value = {} }
  function check(scope: number) {
    if (!alive || scope !== revision || auth.user?.role !== 'owner') throw new ApiError(401, 'Phiên đăng nhập đã thay đổi. Vui lòng đăng nhập lại.')
  }
  function invalidate() { revision++; controllers.forEach(controller => controller.abort()); controllers.clear(); clearError(); busy.value = false }
  watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', invalidate, { flush: 'sync' })
  onBeforeUnmount(() => { alive = false; invalidate() })
  async function get<T>(path: string, params: Record<string, unknown> = {}): Promise<T> {
    const scope = revision
    check(scope)
    const controller = new AbortController(); controllers.add(controller)
    try {
      const result = await api.get<T>(`/owner${path}`, { params, signal: controller.signal })
      check(scope)
      return result.data
    } finally { controllers.delete(controller) }
  }
  async function write<T>(method: 'post' | 'put' | 'patch' | 'delete', path: string, data?: unknown): Promise<T> {
    if (busy.value) throw new ApiError(409, 'Đang lưu thao tác trước. Vui lòng chờ.')
    const scope = revision
    busy.value = true; clearError()
    try {
      check(scope)
      await prepareCsrf(); check(scope)
      const result = await api.request<T>({ method, url: `/owner${path}`, data })
      check(scope)
      return result.data
    } catch (caught) {
      const normalized = toApiError(caught)
      if (alive && scope === revision) { error.value = normalized.message; errors.value = normalized.errors }
      throw normalized
    } finally { if (alive && scope === revision) busy.value = false }
  }
  return { get, write, busy, error, errors, clearError }
}

export function useOwnerList<T>(path: string, initial: Record<string, unknown> = {}) {
  const client = useOwnerApi()
  const items = ref<T[]>([])
  const loading = ref(false)
  const loadError = ref('')
  const page = ref(1), lastPage = ref(1), total = ref(0)
  let query = { ...initial }, sequence = 0, alive = true
  onBeforeUnmount(() => { alive = false; sequence++ })
  async function load(nextPage = page.value, filters?: Record<string, unknown>) {
    if (filters) query = { ...filters }
    const current = ++sequence
    loading.value = true; loadError.value = ''; items.value = []
    try {
      const result = await client.get<PageResult<T>>(path, { ...query, page: nextPage, per_page: 15 })
      if (!alive || current !== sequence) return
      if (!Array.isArray(result.data) || !result.meta || !Number.isSafeInteger(result.meta.last_page) || result.meta.last_page < 1) throw new ApiError(502, 'Dữ liệu danh sách chưa hợp lệ. Vui lòng tải lại.')
      if (nextPage > result.meta.last_page) { await load(result.meta.last_page); return }
      items.value = result.data as typeof items.value
      page.value = result.meta.current_page; lastPage.value = result.meta.last_page; total.value = result.meta.total
    } catch (caught) { if (alive && current === sequence) loadError.value = toApiError(caught).message }
    finally { if (alive && current === sequence) loading.value = false }
  }
  return { items, loading, loadError, page, lastPage, total, load }
}

export function localEventTime() {
  const parts = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' }).format(new Date())
  return parts.replace(' ', 'T')
}
export function eventPayload(value: string) { return value.replace('T', ' ').padEnd(19, ':00') }

import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { api, ApiError, prepareCsrf, toApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { PageResult } from '@/types/owner'

export function useTenantApi() {
  const auth = useAuthStore(), busy = ref(false), error = ref(''), errors = ref<Record<string, string[]>>({})
  let alive = true, revision = 0
  const controllers = new Set<AbortController>()
  function clearError() { error.value = ''; errors.value = {} }
  function check(scope: number) { if (!alive || scope !== revision || auth.user?.role !== 'tenant') throw new ApiError(401, 'Phiên đăng nhập đã thay đổi. Vui lòng đăng nhập lại.') }
  function invalidate() { revision++; controllers.forEach(item => item.abort()); controllers.clear(); clearError(); busy.value = false }
  watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', invalidate, { flush: 'sync' })
  onBeforeUnmount(() => { alive = false; invalidate() })
  async function get<T>(path: string, params: Record<string, unknown> = {}): Promise<T> {
    const scope = revision; check(scope)
    const controller = new AbortController(); controllers.add(controller)
    try { const result = await api.get<T>(`/tenant${path}`, { params, signal: controller.signal }); check(scope); return result.data }
    finally { controllers.delete(controller) }
  }
  async function post<T>(path: string, data: unknown): Promise<T> {
    if (busy.value) throw new ApiError(409, 'Đang gửi thao tác trước. Vui lòng chờ.')
    const scope = revision; busy.value = true; clearError()
    try { check(scope); await prepareCsrf(); check(scope); const result = await api.post<T>(`/tenant${path}`, data); check(scope); return result.data }
    catch (caught) { const normalized = toApiError(caught); if (alive && scope === revision) { error.value = normalized.message; errors.value = normalized.errors }; throw normalized }
    finally { if (alive && scope === revision) busy.value = false }
  }
  return { get, post, busy, error, errors, clearError }
}

export function useTenantResource<T>(path: string | (() => string), refreshOnFocus = true) {
  const client = useTenantApi(), auth = useAuthStore(), data = ref<T | null>(null), loading = ref(true), error = ref(''), status = ref(0)
  let alive = true, sequence = 0
  function clear() { sequence++; data.value = null; error.value = ''; status.value = 0 }
  watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', clear, { flush: 'sync' })
  onBeforeUnmount(() => { alive = false; clear() })
  async function load() {
    const current = ++sequence; data.value = null; error.value = ''; status.value = 0; loading.value = true
    try { const result = await client.get<{ data: T }>(typeof path === 'string' ? path : path()); if (alive && current === sequence) data.value = result.data as typeof data.value }
    catch (caught) { if (alive && current === sequence) { const normalized = toApiError(caught); error.value = normalized.message; status.value = normalized.status } }
    finally { if (alive && current === sequence) loading.value = false }
  }
  function refreshVisible() { if (refreshOnFocus && document.visibilityState === 'visible') void load() }
  onMounted(() => { window.addEventListener('focus', refreshVisible) })
  onBeforeUnmount(() => { window.removeEventListener('focus', refreshVisible) })
  return { data, loading, error, status, load }
}

export function useTenantList<T>(path: string) {
  const client = useTenantApi(), auth = useAuthStore(), items = ref<T[]>([]), loading = ref(false), error = ref(''), status = ref(0)
  const page = ref(1), lastPage = ref(1), total = ref(0)
  let alive = true, sequence = 0, query: Record<string, unknown> = {}
  watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', () => { sequence++; items.value = []; total.value = 0; error.value = '' }, { flush: 'sync' })
  onBeforeUnmount(() => { alive = false; sequence++ })
  async function load(next = page.value, filters?: Record<string, unknown>) {
    if (filters) query = { ...filters }
    const current = ++sequence; items.value = []; error.value = ''; status.value = 0; loading.value = true
    try {
      const result = await client.get<PageResult<T>>(path, { ...query, page: next, per_page: 15 })
      if (!alive || current !== sequence) return
      if (next > result.meta.last_page) { await load(result.meta.last_page); return }
      items.value = result.data as typeof items.value; page.value = result.meta.current_page; lastPage.value = result.meta.last_page; total.value = result.meta.total
    } catch (caught) { if (alive && current === sequence) { const normalized = toApiError(caught); error.value = normalized.message; status.value = normalized.status } }
    finally { if (alive && current === sequence) loading.value = false }
  }
  return { items, page, lastPage, total, loading, error, status, load }
}

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import { eventPayload, useOwnerApi } from '@/composables/useOwnerApi'
import { useAuthStore } from '@/stores/auth'
import { ApiError, toApiError } from '@/lib/api'
import { decimalText, inputTime, previousMonth } from '@/lib/billing'
import { formatDateTime } from '@/lib/notifications'
import { formatUnitPrice } from '@/lib/unit-price'
import { bulkMeterPayloadErrors, bulkMeterStoragePrefix, meterConsumption, normalizeMeterDecimal, readPendingBulkMeter, validMeterMonth } from '@/lib/bulk-meters'
import type { BulkMeterPayload, BulkMeterResponse, BulkMeterRow, PendingBulkMeter } from '@/types/bulk-meters'
import type { PageResult } from '@/types/owner'

type RowState = 'idle' | 'saved' | 'replayed' | 'rejected' | 'conflict' | 'unknown'
interface Draft { row: BulkMeterRow; selected: boolean; start: string; end: string; previous: string; current: string; errors: Record<string, string[]>; state: RowState; message: string; stale: boolean; readingId: number | null }
interface Result { key: string; label: string; state: RowState; message: string; readingId: number | null; occupancyId: number }
const client = useOwnerApi(), auth = useAuthStore()
const month = ref(previousMonth()), search = ref(''), loadedMonth = ref(month.value), loadedSearch = ref('')
const rows = ref<BulkMeterRow[]>([]), drafts = reactive<Record<string, Draft>>({}), loading = ref(false), loadError = ref(''), notice = ref('')
const page = ref(1), last = ref(1), total = ref(0), pending = ref<PendingBulkMeter[]>([]), storageError = ref(''), invalidKeys = ref<string[]>([])
const reviewing = ref(false), reviewed = ref<PendingBulkMeter[]>([]), confirmed = ref(false), sending = ref(false), recovering = ref(false), results = ref<Result[]>([])
const progressTotal = ref(0), progressCompleted = ref(0)
const replacement = ref<{ month: string; q: string; page: number } | null>(null), refreshDraft = ref<Draft | null>(null), discardCorrupt = ref(false)
let alive = true, sequence = 0, refreshSequence = 0
const ownerId = computed(() => auth.user?.role === 'owner' ? auth.user.id : null)
const draftKey = (row: Pick<BulkMeterRow, 'billing_month' | 'occupancy_service_id'>) => `${row.billing_month}:${row.occupancy_service_id}`
const label = (row: BulkMeterRow) => `Phòng ${row.room.room_number} · ${row.service.name} · khoảng #${row.occupancy_service_id}`
const visible = computed(() => rows.value.map(row => drafts[draftKey(row)]).filter((value): value is Draft => !!value))
const selected = computed(() => Object.values(drafts).filter(draft => draft.selected && editable(draft)))
const dirty = computed(() => Object.values(drafts).some(draft => !['saved', 'replayed', 'unknown'].includes(draft.state) && !rowPending(draft.row) && (draft.selected || draft.current !== '' || !draft.row.baseline_known && draft.previous !== '' || draft.start !== inputTime(draft.row.period_start ?? '') || draft.end !== inputTime(draft.row.period_end ?? ''))))
const anyOpenDialog = computed(() => reviewing.value || !!replacement.value || !!refreshDraft.value || discardCorrupt.value)
function readingInput(event: Event, draft: Draft, field: 'previous' | 'current') { draft[field] = (event.target as HTMLInputElement).value }
function rowPending(row: BulkMeterRow) { return pending.value.some(item => item.payload.billing_month === row.billing_month && item.payload.occupancy_service_id === row.occupancy_service_id) }
function editable(draft: Draft) { return draft.row.can_record && !draft.stale && !['saved', 'replayed', 'unknown', 'conflict', 'rejected'].includes(draft.state) && !rowPending(draft.row) }
function newDraft(row: BulkMeterRow): Draft { return { row, selected: false, start: inputTime(row.period_start ?? ''), end: inputTime(row.period_end ?? ''), previous: row.previous_reading ?? '', current: '', errors: {}, state: 'idle', message: '', stale: false, readingId: null } }
function clearView() {
  sequence++; refreshSequence++; rows.value = []; for (const key of Object.keys(drafts)) delete drafts[key]
  pending.value = []; results.value = []; reviewing.value = false; reviewed.value = []; confirmed.value = false; sending.value = false; recovering.value = false
  replacement.value = null; refreshDraft.value = null; discardCorrupt.value = false; storageError.value = ''; invalidKeys.value = []; notice.value = ''; loadError.value = ''
}
function synchronizePending() {
  pending.value = []; invalidKeys.value = []; storageError.value = ''
  if (!ownerId.value) return
  try {
    const prefix = bulkMeterStoragePrefix(ownerId.value)
    for (const key of Object.keys(localStorage).filter(key => key.startsWith(prefix)).sort()) {
      const raw = localStorage.getItem(key), item = raw ? readPendingBulkMeter(raw, ownerId.value) : null
      if (!item || key !== `${prefix}${item.payload.idempotency_key}`) invalidKeys.value.push(key)
      else pending.value.push(item)
    }
    if (invalidKeys.value.length) storageError.value = 'Có bản chờ không đọc được. Cần đối chiếu lịch sử chỉ số trước khi bỏ bản chờ và tạo yêu cầu mới.'
  } catch { storageError.value = 'Trình duyệt chưa cho đọc bản chờ. Chưa thể gửi chỉ số an toàn; hãy kiểm tra quyền lưu trữ rồi thử lại.' }
}
async function load(next = page.value, filters = { month: loadedMonth.value, q: loadedSearch.value }, reset = false) {
  if (!validMeterMonth(filters.month)) { loadError.value = 'Chọn tháng hợp lệ trước khi tải danh sách.'; return }
  const seq = ++sequence; loading.value = true; loadError.value = ''
  try {
    const response = await client.get<PageResult<BulkMeterRow>>('/meter-readings/bulk', { billing_month: filters.month, q: filters.q, page: next, per_page: 20 })
    if (!alive || seq !== sequence) return
    if (!Array.isArray(response.data) || !response.meta || response.meta.last_page < 1) throw new ApiError(502, 'Danh sách chỉ số chưa hợp lệ. Hãy tải lại.')
    if (next > response.meta.last_page) { await load(response.meta.last_page, filters, reset); return }
    if (reset) for (const key of Object.keys(drafts)) delete drafts[key]
    for (const row of response.data) {
      const key = draftKey(row), previous = drafts[key]
      if (!previous) drafts[key] = newDraft(row)
      else if (previous.row.context_token !== row.context_token && !['saved', 'replayed'].includes(previous.state)) { previous.stale = true; previous.selected = false }
      else if (previous.row.context_token === row.context_token) previous.row = row
    }
    rows.value = response.data; loadedMonth.value = filters.month; loadedSearch.value = filters.q; page.value = response.meta.current_page; last.value = response.meta.last_page; total.value = response.meta.total
  } catch (caught) { if (alive && seq === sequence) loadError.value = toApiError(caught).message }
  finally { if (alive && seq === sequence) loading.value = false }
}
function changeFilters() {
  const filters = { month: month.value, q: search.value.trim(), page: 1 }
  if (dirty.value) replacement.value = filters
  else void load(1, filters, true)
}
function refreshList() { if (dirty.value) replacement.value = { month: loadedMonth.value, q: loadedSearch.value, page: page.value }; else void load() }
function applyReplacement() { const request = replacement.value; replacement.value = null; if (request) void load(request.page, request, true) }
function toggleVisible() { const values = visible.value.filter(editable), all = values.length > 0 && values.every(draft => draft.selected); values.forEach(draft => { draft.selected = !all }) }
function payload(draft: Draft): Omit<BulkMeterPayload, 'idempotency_key'> { return { billing_month: draft.row.billing_month, occupancy_id: draft.row.occupancy_id, occupancy_service_id: draft.row.occupancy_service_id, period_start: eventPayload(draft.start), period_end: eventPayload(draft.end), previous_reading: draft.previous, current_reading: draft.current, context_token: draft.row.context_token } }
function reviewSelected() {
  notice.value = ''; client.clearError(); synchronizePending()
  if (!ownerId.value || storageError.value || sending.value || !selected.value.length) return
  if (selected.value.length > 100) { notice.value = 'Mỗi lượt xem lại tối đa 100 dòng. Hãy chia thành nhiều lượt để dễ đối chiếu.'; return }
  let invalid = false
  for (const draft of selected.value) { draft.errors = bulkMeterPayloadErrors(payload(draft)); invalid ||= Object.keys(draft.errors).length > 0 }
  if (invalid) { notice.value = 'Có dòng chưa hợp lệ. Kiểm tra các lỗi ngay dưới ô nhập rồi xem lại.'; return }
  reviewed.value = selected.value.map(draft => ({ owner_id: ownerId.value!, label: label(draft.row), unit: draft.row.unit, state: 'queued', payload: { ...payload(draft), previous_reading: normalizeMeterDecimal(draft.previous)!, current_reading: normalizeMeterDecimal(draft.current)!, idempotency_key: crypto.randomUUID() } }))
  recovering.value = false; confirmed.value = false; reviewing.value = true
}
function reviewPending() { synchronizePending(); if (storageError.value || !pending.value.length) return; reviewed.value = pending.value.slice(0, 100).map(item => ({ ...item, payload: { ...item.payload } })); recovering.value = true; confirmed.value = false; reviewing.value = true }
function updateResult(item: PendingBulkMeter, state: RowState, message: string, readingId: number | null = null, errors: Record<string, string[]> = {}) {
  const key = `${item.payload.billing_month}:${item.payload.occupancy_service_id}`, draft = drafts[key]
  if (draft) { draft.selected = false; draft.state = state; draft.message = message; draft.errors = errors; draft.readingId = readingId }
  results.value = [{ key, label: item.label, state, message, readingId, occupancyId: item.payload.occupancy_id }, ...results.value.filter(result => result.key !== key)]
}
function clearStored(item: PendingBulkMeter) {
  const key = `${bulkMeterStoragePrefix(item.owner_id)}${item.payload.idempotency_key}`, current = localStorage.getItem(key)
  if (current && JSON.stringify(readPendingBulkMeter(current, item.owner_id)?.payload) === JSON.stringify(item.payload)) localStorage.removeItem(key)
}
async function sendReviewed() {
  if (sending.value || !confirmed.value || !ownerId.value || !reviewed.value.length) return
  const scopeOwner = ownerId.value, operations = reviewed.value.map(item => ({ ...item, payload: { ...item.payload } }))
  synchronizePending(); if (storageError.value) return
  try {
    for (const item of operations) {
      const conflicts = pending.value.some(prior => prior.payload.billing_month === item.payload.billing_month && prior.payload.occupancy_service_id === item.payload.occupancy_service_id && prior.payload.idempotency_key !== item.payload.idempotency_key)
      if (conflicts) { storageError.value = 'Dòng này có yêu cầu khác đang chờ. Đóng hộp thoại và mở bản chờ để đối chiếu trước.'; return }
    }
    for (const item of operations) localStorage.setItem(`${bulkMeterStoragePrefix(scopeOwner)}${item.payload.idempotency_key}`, JSON.stringify(item))
  } catch { storageError.value = 'Chưa lưu được bản chờ; chưa gửi dữ liệu. Giữ trang này và cho phép lưu trữ rồi thử lại.'; return }
  progressTotal.value = operations.length; progressCompleted.value = 0
  sending.value = true; notice.value = ''
  for (const item of operations) {
    if (!alive || ownerId.value !== scopeOwner) break
    try {
      localStorage.setItem(`${bulkMeterStoragePrefix(scopeOwner)}${item.payload.idempotency_key}`, JSON.stringify({ ...item, state: 'unknown' }))
      const response = await client.write<BulkMeterResponse>('post', '/meter-readings/bulk/rows', item.payload)
      if (!alive || ownerId.value !== scopeOwner) break
      if (!Number.isSafeInteger(response.reading_id) || response.reading_id < 1 || typeof response.replayed !== 'boolean') throw new ApiError(502, 'Kết quả lưu chưa rõ. Hãy gửi lại bản chờ để đối chiếu.')
      const message = response.reading_deleted ? 'Yêu cầu đã ghi trước đây; kỳ chỉ số đó hiện đã xóa. Không tạo lại.' : response.reading_changed ? 'Yêu cầu đã ghi trước đây; chỉ số hiện đã được sửa. Mở lịch sử để đối chiếu.' : response.message
      try { clearStored(item) } catch { /* An explicit replay of the retained key remains safe. */ }
      updateResult(item, response.replayed ? 'replayed' : 'saved', message, response.reading_id)
    } catch (caught) {
      if (!alive || ownerId.value !== scopeOwner) break
      const error = toApiError(caught), unknown = error.status === 0 || error.status === 408 || error.status >= 500 || error.status === 401
      if (unknown) updateResult(item, 'unknown', 'Chưa rõ kết quả. Giữ nguyên bản chờ và gửi lại để đối chiếu.')
      else {
        try { clearStored(item) } catch { /* Keep a recoverable original request if storage is unavailable. */ }
        updateResult(item, error.status === 409 ? 'conflict' : 'rejected', error.message, null, error.errors)
      }
      if (unknown || [403, 419, 429].includes(error.status)) { notice.value = 'Đã dừng lượt gửi. Những dòng còn chờ chưa được tự gửi tiếp; hãy kiểm tra kết quả rồi chủ động gửi lại.'; break }
    } finally { if (alive && ownerId.value === scopeOwner) progressCompleted.value++ }
  }
  if (alive && ownerId.value === scopeOwner) { sending.value = false; reviewing.value = false; reviewed.value = []; synchronizePending(); if (!notice.value) notice.value = 'Đã xử lý các dòng được xác nhận. Xem kết quả từng dòng bên dưới; hệ thống không tự phát hành hóa đơn.' }
}
async function refreshOne() {
  const target = refreshDraft.value; refreshDraft.value = null
  if (!target || sending.value) return
  const seq = ++refreshSequence, key = draftKey(target.row)
  try {
    const response = await client.get<PageResult<BulkMeterRow>>('/meter-readings/bulk', { billing_month: target.row.billing_month, occupancy_service_id: target.row.occupancy_service_id, per_page: 1 })
    if (!alive || seq !== refreshSequence) return
    const row = response.data.find(item => draftKey(item) === key)
    if (!row) { target.stale = true; target.message = 'Khoảng dịch vụ không còn trong danh sách tháng này. Mở chi tiết đợt thuê để đối chiếu.'; return }
    drafts[key] = newDraft(row); notice.value = 'Đã tải lại riêng dòng này. Kiểm tra và nhập lại trước khi tạo yêu cầu mới.'
  } catch (caught) { if (alive && seq === refreshSequence) notice.value = toApiError(caught).message }
}
function discardInvalid() { if (!sending.value) { try { invalidKeys.value.forEach(key => localStorage.removeItem(key)); discardCorrupt.value = false; synchronizePending() } catch { storageError.value = 'Chưa xóa được bản chờ; hãy kiểm tra quyền lưu trữ.' } } }
function stateLabel(state: RowState) { return { idle: 'Chưa gửi', saved: 'Đã lưu', replayed: 'Đã đối chiếu', rejected: 'Cần sửa thông tin', conflict: 'Dữ liệu đã thay đổi', unknown: 'Chưa rõ kết quả' }[state] }
function stateTone(state: RowState): 'success' | 'warning' | 'danger' | 'neutral' { return ['saved', 'replayed'].includes(state) ? 'success' : state === 'unknown' ? 'warning' : state === 'idle' ? 'neutral' : 'danger' }
function storageChanged(event: StorageEvent) { if (!sending.value && ownerId.value && (!event.key || event.key.startsWith(bulkMeterStoragePrefix(ownerId.value)))) synchronizePending() }
function beforeUnload(event: BeforeUnloadEvent) { if (dirty.value && !sending.value) { event.preventDefault(); event.returnValue = '' } }
watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', () => { clearView(); synchronizePending() }, { flush: 'sync' })
onBeforeRouteLeave(() => sending.value ? false : !dirty.value || window.confirm('Các chỉ số chưa xác nhận chỉ giữ trên trang. Rời trang và bỏ bản nháp? Bản chờ đã gửi vẫn được giữ để đối chiếu.'))
window.addEventListener('storage', storageChanged); window.addEventListener('beforeunload', beforeUnload)
onBeforeUnmount(() => { alive = false; clearView(); window.removeEventListener('storage', storageChanged); window.removeEventListener('beforeunload', beforeUnload) })
synchronizePending(); void load(1)
</script>

<template>
  <section class="management-page bulk-meters">
    <RouterLink class="back-link" to="/owner/meters">← Điện nước từng phòng</RouterLink>
    <header class="management-header"><div><span class="eyebrow">Ghi chỉ số cuối kỳ</span><h1>Nhập chỉ số nhiều phòng</h1><p>Mỗi dòng là một dịch vụ trong một khoảng áp dụng giá. Chỉ lưu sau khi bạn xem lại.</p></div></header>
    <UiNotice v-if="notice" :tone="notice.startsWith('Đã xử lý') ? 'success' : 'warning'">{{ notice }}</UiNotice>
    <div v-if="sending" class="bulk-progress" role="status" aria-atomic="true"><label for="bulk-progress">Tiến độ lượt gửi: {{ progressCompleted }} / {{ progressTotal }} dòng đã thử xử lý</label><progress id="bulk-progress" :value="progressCompleted" :max="progressTotal" /><p class="help-text">Xem kết quả riêng từng dòng; những dòng chưa rõ kết quả được giữ để đối chiếu.</p></div>
    <UiNotice v-if="pending.length" tone="warning" title="Có chỉ số đang chờ đối chiếu"><p>{{ pending.length }} dòng đã xác nhận được giữ theo tài khoản, gồm cả các tháng khác. Gửi lại nguyên yêu cầu sẽ nhận diện lần lưu trước.</p><UiButton variant="secondary" :disabled="sending" @click="reviewPending">Xem lại các dòng đang chờ</UiButton></UiNotice>
    <UiNotice v-if="storageError" tone="warning"><p>{{ storageError }}</p><div class="button-row"><UiButton variant="secondary" :disabled="sending" @click="synchronizePending">Kiểm tra lại bản chờ</UiButton><UiButton v-if="invalidKeys.length" variant="ghost" :disabled="sending" @click="discardCorrupt = true">Đối chiếu bản chờ không đọc được</UiButton></div></UiNotice>
    <form class="management-toolbar" @submit.prevent="changeFilters"><label>Tháng chỉ số<input v-model="month" type="month" class="ui-input" required :disabled="sending || anyOpenDialog" /></label><label>Tìm phòng<input v-model="search" class="ui-input" maxlength="100" placeholder="Nhập số phòng" :disabled="sending || anyOpenDialog" /></label><UiButton type="submit" variant="secondary" :loading="loading" :disabled="sending || anyOpenDialog">Tải danh sách phòng</UiButton><UiButton variant="ghost" :disabled="sending || anyOpenDialog || loading" @click="refreshList">Làm mới danh sách</UiButton></form>
    <p class="help-text">Đang hiển thị tháng {{ loadedMonth }}. Bản nháp chưa xác nhận chỉ giữ trên trang; các dòng đã gửi có bản chờ riêng để thử lại an toàn.</p>
    <UiPageState v-if="loading" kind="loading" title="Đang tải các khoảng chỉ số…" />
    <UiPageState v-else-if="loadError" kind="error" :description="loadError"><UiButton @click="load()">Thử lại danh sách</UiButton></UiPageState>
    <UiPageState v-else-if="!visible.length" title="Chưa có dịch vụ tính theo chỉ số phù hợp" description="Chọn tháng khác hoặc kiểm tra điện/nước đã áp dụng cho các đợt thuê." />
    <template v-else>
      <div class="bulk-tools"><div><strong>{{ selected.length }} dòng được chọn</strong><p class="help-text">Dòng đã khóa hoặc thiếu kỳ trước cần được xử lý ở màn hình từng phòng.</p></div><div class="button-row"><UiButton variant="secondary" :disabled="sending || anyOpenDialog" @click="toggleVisible">Chọn / bỏ các dòng trang này</UiButton><UiButton :disabled="!selected.length || sending || anyOpenDialog || !!storageError" @click="reviewSelected">Xem lại các chỉ số đã chọn</UiButton></div></div>
      <div class="bulk-grid" role="list" aria-label="Các dòng chỉ số nhiều phòng">
        <article v-for="draft in visible" :key="draftKey(draft.row)" class="record-card meter-row" role="listitem" :aria-label="label(draft.row)">
          <header><div><label class="row-pick"><input v-model="draft.selected" type="checkbox" :aria-label="`Chọn ${label(draft.row)}`" :disabled="!editable(draft) || sending" /><strong>Phòng {{ draft.row.room.room_number }} · {{ draft.row.service.name }}</strong></label><p class="help-text">Nhóm {{ draft.row.group.group_code }} · Khoảng #{{ draft.row.occupancy_service_id }}</p><p class="help-text">{{ formatUnitPrice(draft.row.unit_price) }} / {{ draft.row.unit }} · từ {{ formatDateTime(draft.row.effective_from) }}<template v-if="draft.row.effective_until"> đến {{ formatDateTime(draft.row.effective_until) }}</template></p></div><UiBadge :tone="stateTone(draft.state)">{{ stateLabel(draft.state) }}</UiBadge></header>
          <UiNotice v-if="draft.row.blocked_reason || draft.stale || rowPending(draft.row)" tone="warning">{{ rowPending(draft.row) ? 'Dòng này có yêu cầu đang chờ đối chiếu. Dùng bản chờ để gửi lại nguyên thông tin.' : draft.stale ? 'Dữ liệu dòng đã thay đổi. Bản nháp được giữ để đối chiếu; cần tải lại riêng dòng này trước khi nhập tiếp.' : draft.row.blocked_reason }}</UiNotice>
          <fieldset class="meter-inputs" :disabled="!editable(draft) || sending">
            <label>Đầu kỳ (giờ Việt Nam)<input v-model="draft.start" aria-label="Đầu kỳ (giờ Việt Nam)" type="datetime-local" step="1" class="ui-input" :aria-invalid="!!draft.errors.period_start || undefined" /><span v-if="draft.errors.period_start" class="field-error" role="alert">{{ draft.errors.period_start[0] }}</span></label>
            <label>Cuối kỳ (giờ Việt Nam)<input v-model="draft.end" aria-label="Cuối kỳ (giờ Việt Nam)" type="datetime-local" step="1" class="ui-input" :aria-invalid="!!draft.errors.period_end || undefined" /><span v-if="draft.errors.period_end" class="field-error" role="alert">{{ draft.errors.period_end[0] }}</span></label>
            <label>Chỉ số đầu<input :value="draft.previous" @input="readingInput($event, draft, 'previous')" aria-label="Chỉ số đầu" type="number" min="0" step="0.001" class="ui-input" :readonly="draft.row.baseline_known" :aria-invalid="!!draft.errors.previous_reading || undefined" /><small>{{ draft.row.baseline_known ? 'Lấy từ kỳ trước; giữ liên tục cùng đồng hồ.' : 'Chưa có kỳ trước: nhập số thực tế, không mặc định bằng 0.' }}</small><span v-if="draft.errors.previous_reading" class="field-error" role="alert">{{ draft.errors.previous_reading[0] }}</span></label>
            <label>Chỉ số cuối<input :value="draft.current" @input="readingInput($event, draft, 'current')" aria-label="Chỉ số cuối" type="number" min="0" step="0.001" class="ui-input" :aria-invalid="!!draft.errors.current_reading || undefined" /><span v-if="draft.errors.current_reading" class="field-error" role="alert">{{ draft.errors.current_reading[0] }}</span></label>
          </fieldset>
          <p class="meter-consumption">Tiêu thụ: <strong>{{ meterConsumption(draft.previous, draft.current) === null ? 'Chưa đủ chỉ số hợp lệ' : `${decimalText(meterConsumption(draft.previous, draft.current)!)} ${draft.row.unit}` }}</strong></p>
          <p v-if="draft.message" :class="['row-message', { 'field-error': ['rejected', 'conflict', 'unknown'].includes(draft.state) }]" role="status">{{ draft.message }}</p>
          <footer><RouterLink class="record-link" :to="`/owner/meters?occupancy_id=${draft.row.occupancy_id}`">Xem lịch sử từng phòng →</RouterLink><RouterLink v-if="draft.row.invoice_id" class="record-link" :to="`/owner/invoices/${draft.row.invoice_id}`">Xem hóa đơn đã khóa</RouterLink><UiButton v-if="draft.stale || ['conflict', 'rejected', 'saved', 'replayed'].includes(draft.state)" variant="ghost" :disabled="sending || rowPending(draft.row)" @click="refreshDraft = draft">Tải lại riêng dòng này</UiButton></footer>
        </article>
      </div>
      <OwnerPager :page="page" :last-page="last" :total="total" :loading="loading || sending || anyOpenDialog" @change="load($event)" />
    </template>
    <section v-if="results.length" class="record-card bulk-results" aria-label="Kết quả nhập chỉ số"><h2>Kết quả từng dòng</h2><ul><li v-for="result in results" :key="result.key"><strong>{{ result.label }}</strong><UiBadge :tone="stateTone(result.state)">{{ stateLabel(result.state) }}</UiBadge><p>{{ result.message }}</p><RouterLink class="record-link" :to="`/owner/meters?occupancy_id=${result.occupancyId}`">{{ result.readingId ? `Đối chiếu kỳ #${result.readingId}` : 'Mở lịch sử chỉ số' }} →</RouterLink></li></ul></section>
    <UiDialog :open="reviewing" title="Xem lại chỉ số nhiều phòng" description="Mỗi dòng được lưu riêng. Dòng lỗi không làm mất các dòng đã lưu thành công; không tự phát hành hóa đơn." @update:open="value => { if (!value && !sending) reviewing = false }">
      <UiNotice v-if="recovering" tone="warning">Các thông tin và mã yêu cầu đang chờ được giữ nguyên. Hệ thống đối chiếu lần ghi trước thay vì tạo trùng. Mỗi lượt xử lý tối đa 100 dòng; các dòng còn lại vẫn được giữ để bạn xem lại ở lượt sau.</UiNotice><p v-if="storageError" class="form-error" role="alert">{{ storageError }}</p>
      <ol class="bulk-review"><li v-for="item in reviewed" :key="item.payload.idempotency_key"><strong>{{ item.label }}</strong><p>Tháng {{ item.payload.billing_month }} · {{ item.payload.previous_reading }} → {{ item.payload.current_reading }} {{ item.unit }}</p><p>{{ formatDateTime(item.payload.period_start) }} → {{ formatDateTime(item.payload.period_end) }}</p><small v-if="recovering">{{ item.state === 'queued' ? 'Dòng đang chờ gửi' : 'Cần đối chiếu kết quả lần gửi trước' }}</small></li></ol>
      <label class="bulk-confirm"><input v-model="confirmed" type="checkbox" :disabled="sending" /> Tôi đã kiểm tra từng dòng và đồng ý lưu các chỉ số này.</label>
      <template #actions><UiButton variant="secondary" :disabled="sending" @click="reviewing = false">Đóng</UiButton><UiButton :loading="sending" :disabled="!confirmed || !!storageError" @click="sendReviewed">{{ recovering ? 'Gửi lại các dòng đang chờ' : 'Xác nhận lưu các chỉ số' }}</UiButton></template>
    </UiDialog>
    <UiDialog :open="!!replacement" title="Tải danh sách mới?" description="Các bản nháp chưa xác nhận sẽ được bỏ khi tải thành công. Bản chờ đã xác nhận vẫn được giữ để đối chiếu." @update:open="value => { if (!value) replacement = null }"><template #actions><UiButton variant="secondary" @click="replacement = null">Giữ bản nháp</UiButton><UiButton @click="applyReplacement">Bỏ bản nháp và tải danh sách</UiButton></template></UiDialog>
    <UiDialog :open="!!refreshDraft" title="Tải lại dòng chỉ số?" description="Bỏ bản nháp riêng dòng này và lấy dữ liệu/phiên bản mới nhất. Các dòng khác được giữ nguyên." @update:open="value => { if (!value) refreshDraft = null }"><template #actions><UiButton variant="secondary" @click="refreshDraft = null">Giữ bản nháp</UiButton><UiButton @click="refreshOne">Xác nhận tải lại dòng</UiButton></template></UiDialog>
    <UiDialog :open="discardCorrupt" title="Đối chiếu bản chờ không đọc được" description="Trước khi bỏ bản chờ, hãy mở lịch sử từng phòng để kiểm tra kỳ nào đã được lưu. Bỏ bản chờ không hoàn tác dữ liệu máy chủ." @update:open="value => { if (!value) discardCorrupt = false }"><p>Chỉ các bản chờ bị hỏng bị bỏ; các bản chờ hợp lệ vẫn giữ nguyên.</p><template #actions><UiButton variant="secondary" @click="discardCorrupt = false">Giữ bản chờ</UiButton><UiButton variant="danger" @click="discardInvalid">Đã đối chiếu, bỏ bản chờ bị hỏng</UiButton></template></UiDialog>
  </section>
</template>

<style scoped>
.bulk-progress { margin: 20px 0; padding: 20px; border: 1px solid var(--color-border); border-radius: var(--radius-control); background: var(--color-primary-soft); font-size: 14px; }
.bulk-progress progress { display: block; width: 100%; height: 12px; margin-top: 12px; accent-color: var(--color-primary); }

.bulk-tools { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin: 24px 0; }
.bulk-grid { display: grid; gap: 20px; }.meter-row { min-width: 0; }.meter-row header > div { min-width: 0; }.row-pick { display: flex; gap: 12px; align-items: start; }.row-pick input,.bulk-confirm input { width: 20px; height: 20px; flex-shrink: 0; accent-color: var(--color-primary); }.row-pick strong { font-size: 17px; line-height: 1.6; }
.meter-inputs { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 16px; border: 0; padding: 0; margin: 20px 0; min-width: 0; }.meter-inputs label { display: grid; align-content: start; gap: 8px; min-width: 0; font-size: 13px; }.meter-inputs input { min-width: 0; }.meter-inputs input[readonly] { background: var(--color-bg); color: var(--color-text-secondary); }.meter-inputs small { color: var(--color-text-secondary); font-size: 12px; line-height: 1.6; }.meter-inputs:disabled { opacity: .7; }
.meter-consumption { font-size: 14px; padding: 12px 16px; background: var(--color-primary-soft); border-radius: 10px; }.meter-consumption strong { color: var(--color-primary); font-variant-numeric: tabular-nums; }.row-message { font-size: 14px; line-height: 1.8; }.bulk-results { margin-top: 24px; }.bulk-results ul { list-style: none; padding: 0; margin: 0; }.bulk-results li { padding: 18px 0; border-bottom: 1px solid var(--color-border); }.bulk-results li:last-child { border-bottom: 0; }.bulk-results .ui-badge { margin-left: 12px; }.bulk-results p { font-size: 14px; line-height: 1.8; }.bulk-review { padding-left: 22px; }.bulk-review li { padding: 14px 0; border-bottom: 1px solid var(--color-border); overflow-wrap: anywhere; }.bulk-review p,.bulk-review small { font-size: 13px; color: var(--color-text-secondary); line-height: 1.7; }.bulk-confirm { display: flex; gap: 12px; align-items: start; font-size: 14px; line-height: 1.7; margin-top: 24px; }
@media(max-width:1100px) { .meter-inputs { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media(max-width:600px) { .meter-inputs { grid-template-columns:minmax(0,1fr); }.bulk-tools > .button-row { width:100%; }.meter-row > header { flex-wrap:wrap; }.bulk-results .ui-badge { display:inline-flex; margin:8px 0; } }
</style>

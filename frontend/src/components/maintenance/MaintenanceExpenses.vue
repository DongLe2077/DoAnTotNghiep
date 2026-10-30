<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import CashCreateDialog from '@/components/cash/CashCreateDialog.vue'
import CashDetailDialog from '@/components/cash/CashDetailDialog.vue'
import { useOwnerApi } from '@/composables/useOwnerApi'
import { useAuthStore } from '@/stores/auth'
import { ApiError, toApiError } from '@/lib/api'
import { readPendingMaintenanceExpense, type PendingMaintenanceExpense } from '@/lib/maintenance-expenses'
import { formatDateTime } from '@/lib/notifications'
import { cashMoney, type CashTransaction } from '@/types/cash'
import type { PageResult } from '@/types/owner'
import type { MaintenanceExpense, MaintenanceExpenseHistory, MaintenanceExpenseSummary } from '@/types/maintenance-expenses'

const props = defineProps<{ requestId: number }>()
const client = useOwnerApi(), auth = useAuthStore()
const items = ref<MaintenanceExpense[]>([]), summary = ref<MaintenanceExpenseSummary | null>(null)
const loading = ref(false), error = ref(''), notice = ref(''), page = ref(1), last = ref(1), total = ref(0)
const dialog = ref(false), creating = ref(false), detailId = ref<number | null>(null), mode = ref<'link' | 'unlink'>('link')
const chosen = ref<MaintenanceExpense['cash_transaction'] | null>(null), association = ref<MaintenanceExpense | null>(null)
const selecting = ref(false), selectError = ref(''), reason = ref(''), reviewing = ref(false), abandoning = ref(false)
const pending = ref<PendingMaintenanceExpense | null>(null), storageError = ref(''), invalidStorageKey = ref<string | null>(null)
const candidates = ref<CashTransaction[]>([]), search = ref(''), candidateLoading = ref(false), candidateError = ref('')
const candidatePage = ref(1), candidateLast = ref(1), candidateTotal = ref(0)
const historyOpen = ref(false), historyLoaded = ref(false), histories = ref<MaintenanceExpenseHistory[]>([])
const historyLoading = ref(false), historyError = ref(''), historyPage = ref(1), historyLast = ref(1), historyTotal = ref(0)
let alive = true, sequence = 0, candidateSequence = 0, selectionSequence = 0, historySequence = 0
const storagePrefix = computed(() => `rental.maintenance-expense.pending.v1:${auth.user?.id ?? 'none'}:${props.requestId}:`)
const busy = computed(() => client.busy.value)
const selectionBlocked = computed(() => Boolean(association.value?.is_active))
function pendingKeys() { return Object.keys(localStorage).filter(key => key.startsWith(storagePrefix.value)).sort() }
function restore() {
  pending.value = null; storageError.value = ''; invalidStorageKey.value = null
  try {
    const key = pendingKeys()[0]
    if (!key) return
    const raw = localStorage.getItem(key)
    const value = raw ? readPendingMaintenanceExpense(raw, props.requestId) : null
    if (!value || key !== `${storagePrefix.value}${value.idempotency_key}`) { invalidStorageKey.value = key; throw new Error() }
    pending.value = value
  } catch { storageError.value = 'Không đọc được yêu cầu đang lưu. Hãy đối chiếu chi phí và lịch sử liên kết trước khi bỏ bản chờ.' }
}
function clearData() {
  sequence++; candidateSequence++; selectionSequence++; historySequence++
  items.value = []; summary.value = null; candidates.value = []; chosen.value = null; association.value = null
  histories.value = []; historyLoaded.value = false; pending.value = null; storageError.value = ''; error.value = ''
  historyOpen.value = false
  dialog.value = false; creating.value = false; detailId.value = null; reviewing.value = false; abandoning.value = false; reason.value = ''; notice.value = ''
}
watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', clearData, { flush: 'sync' })
watch(() => props.requestId, () => { clearData(); restore(); void load(1) }, { immediate: true })
onBeforeUnmount(() => { alive = false; clearData(); window.removeEventListener('storage', storageChanged) })

async function load(next = page.value) {
  const seq = ++sequence
  loading.value = true; error.value = ''; items.value = []; summary.value = null
  try {
    const result = await client.get<PageResult<MaintenanceExpense> & { summary: MaintenanceExpenseSummary }>(`/maintenance-requests/${props.requestId}/expenses`, { page: next, per_page: 10 })
    if (!alive || seq !== sequence) return
    if (!result.meta || !Number.isSafeInteger(result.meta.last_page) || result.meta.last_page < 1) throw new ApiError(502, 'Danh sách chi phí chưa hợp lệ. Hãy tải lại.')
    if (next > result.meta.last_page) { await load(result.meta.last_page); return }
    items.value = result.data; summary.value = result.summary; page.value = result.meta.current_page; last.value = result.meta.last_page; total.value = result.meta.total
  } catch (caught) { if (alive && seq === sequence) error.value = toApiError(caught).message }
  finally { if (alive && seq === sequence) loading.value = false }
}
async function loadCandidates(next = 1) {
  const seq = ++candidateSequence
  candidateLoading.value = true; candidateError.value = ''; candidates.value = []
  try {
    const result = await client.get<PageResult<CashTransaction>>('/cash-transactions', { transaction_type: 'expense', state: 'active', q: search.value.trim(), page: next, per_page: 10 })
    if (!alive || seq !== candidateSequence || !dialog.value) return
    if (next > result.meta.last_page) { await loadCandidates(result.meta.last_page); return }
    candidates.value = result.data; candidatePage.value = result.meta.current_page; candidateLast.value = result.meta.last_page; candidateTotal.value = result.meta.total
  } catch (caught) { if (alive && seq === candidateSequence) candidateError.value = toApiError(caught).message }
  finally { if (alive && seq === candidateSequence) candidateLoading.value = false }
}
async function choose(entry: MaintenanceExpense['cash_transaction']) {
  const seq = ++selectionSequence
  chosen.value = entry; association.value = null; selecting.value = true; selectError.value = ''; client.clearError()
  try {
    const [result, latest] = await Promise.all([
      client.get<{ data: MaintenanceExpense | null }>(`/cash-transactions/${entry.id}/maintenance-link`),
      client.get<{ data: CashTransaction }>(`/cash-transactions/${entry.id}`),
    ])
    if (alive && seq === selectionSequence) {
      association.value = result.data; chosen.value = latest.data
      if (latest.data.is_deleted || latest.data.transaction_type !== 'expense') selectError.value = 'Khoản đã thay đổi hoặc không còn là chi phí còn hiệu lực. Hãy chọn khoản khác.'
    }
  } catch (caught) { if (alive && seq === selectionSequence) selectError.value = toApiError(caught).message }
  finally { if (alive && seq === selectionSequence) selecting.value = false }
}
function openLink() {
  if (busy.value) return
  restore(); mode.value = pending.value?.action ?? 'link'; reason.value = pending.value?.reason ?? ''
  chosen.value = null; association.value = null; selectError.value = ''; reviewing.value = Boolean(pending.value); abandoning.value = false; client.clearError(); dialog.value = true
  if (!pending.value && !storageError.value) void loadCandidates(1)
}
function openUnlink(item: MaintenanceExpense) {
  openLink()
  if (pending.value || storageError.value) return
  mode.value = 'unlink'; chosen.value = item.cash_transaction; association.value = item; candidateSequence++; candidateLoading.value = false
}
function close() { if (!busy.value) { dialog.value = false; candidateSequence++; selectionSequence++ } }
function review() {
  client.clearError()
  if (!chosen.value || selecting.value || selectError.value || mode.value === 'link' && selectionBlocked.value) return
  reason.value = reason.value.trim()
  if (reason.value.length < 3 || reason.value.length > 1000) { client.errors.value = { reason: ['Nhập lý do từ 3 đến 1.000 ký tự.'] }; client.error.value = 'Vui lòng kiểm tra lý do liên kết.'; return }
  reviewing.value = true
}
function clearStored(expected: PendingMaintenanceExpense) {
  const key = `${storagePrefix.value}${expected.idempotency_key}`, raw = localStorage.getItem(key)
  if (raw && readPendingMaintenanceExpense(raw, expected.request_id)?.idempotency_key === expected.idempotency_key) localStorage.removeItem(key)
}
async function send() {
  if (busy.value || storageError.value || !reviewing.value) return
  if (!pending.value) {
    if (!chosen.value || selecting.value || selectError.value || mode.value === 'link' && selectionBlocked.value || mode.value === 'unlink' && !association.value) return
    pending.value = { action: mode.value, request_id: props.requestId, cash_transaction_id: chosen.value.id,
      version_token: mode.value === 'link' ? chosen.value.version_token : association.value!.version_token,
      ...(mode.value === 'link' ? { link_version_token: association.value?.version_token ?? null } : {}),
      reason: reason.value, idempotency_key: crypto.randomUUID() }
  }
  const sent = pending.value, ownerId = auth.user?.id, reportId = props.requestId, scopePrefix = storagePrefix.value
  try {
    if (pendingKeys().some(key => key !== `${scopePrefix}${sent.idempotency_key}`)) { restore(); reviewing.value = Boolean(pending.value); client.error.value = 'Có yêu cầu khác đang chờ đối chiếu cho sửa chữa này. Hãy xử lý yêu cầu đó trước.'; return }
    localStorage.setItem(`${scopePrefix}${sent.idempotency_key}`, JSON.stringify(sent))
  } catch { storageError.value = 'Chưa lưu được yêu cầu để gửi lại an toàn. Chưa gửi thao tác này; hãy cho phép lưu trữ rồi thử lại.'; return }
  const path = `/maintenance-requests/${sent.request_id}/expenses${sent.action === 'unlink' ? `/${sent.cash_transaction_id}/unlink` : ''}`
  const payload = sent.action === 'link'
    ? { cash_transaction_id: sent.cash_transaction_id, cash_version_token: sent.version_token, version_token: sent.link_version_token ?? null, reason: sent.reason, idempotency_key: sent.idempotency_key }
    : { version_token: sent.version_token, reason: sent.reason, idempotency_key: sent.idempotency_key }
  try {
    const result = await client.write<{ message: string; replayed: boolean; data: MaintenanceExpense }>('post', path, payload)
    if (!alive || auth.user?.id !== ownerId || props.requestId !== reportId) return
    try { clearStored(sent) } catch { /* Retaining the same key keeps an explicit replay safe. */ }
    pending.value = null; dialog.value = false; reviewing.value = false
    notice.value = result.replayed ? 'Đã đối chiếu yêu cầu trước đó. Liên kết không bị ghi lại; danh sách bên dưới là trạng thái hiện tại.' : result.message
    await load(1)
    if (historyOpen.value) await loadHistory(1)
  } catch (caught) {
    if (!alive || auth.user?.id !== ownerId || props.requestId !== reportId) return
    if (toApiError(caught).status === 422) {
      try { clearStored(sent); pending.value = null; reviewing.value = false } catch { storageError.value = 'Không xóa được yêu cầu bị từ chối. Hãy tải lại để đối chiếu.' }
    }
  }
}
function abandon() {
  if (busy.value) return
  try {
    if (pending.value) clearStored(pending.value)
    else if (invalidStorageKey.value) localStorage.removeItem(invalidStorageKey.value)
    pending.value = null; storageError.value = ''; invalidStorageKey.value = null; abandoning.value = false; dialog.value = false; reviewing.value = false; client.clearError(); restore(); void load()
  } catch { storageError.value = 'Chưa xóa được bản chờ. Vui lòng kiểm tra quyền lưu trữ của trình duyệt.' }
}
function createExpense() { close(); creating.value = true }
function created(entry: CashTransaction, replayed: boolean) {
  creating.value = false
  if (entry.is_deleted || entry.transaction_type !== 'expense') { notice.value = 'Khoản đã ghi nhận chưa phải chi phí còn hiệu lực. Hãy xem sổ thu chi trước khi chọn khoản để liên kết.'; detailId.value = entry.id; return }
  openLink(); if (!pending.value && !storageError.value) void choose(entry)
  notice.value = replayed ? 'Đã tìm thấy khoản đã tạo trước đó. Xem lại để liên kết với yêu cầu sửa chữa.' : 'Đã tạo khoản chi trong sổ. Xem lại và xác nhận liên kết với yêu cầu sửa chữa.'
}
async function cashChanged() { await load(); if (historyOpen.value) await loadHistory(1) }
async function loadHistory(next = 1) {
  const seq = ++historySequence
  historyLoading.value = true; historyError.value = ''; histories.value = []
  try {
    const result = await client.get<PageResult<MaintenanceExpenseHistory>>(`/maintenance-requests/${props.requestId}/expenses/history`, { page: next, per_page: 10 })
    if (!alive || seq !== historySequence) return
    if (next > result.meta.last_page) { await loadHistory(result.meta.last_page); return }
    histories.value = result.data; historyPage.value = result.meta.current_page; historyLast.value = result.meta.last_page; historyTotal.value = result.meta.total; historyLoaded.value = true
  } catch (caught) { if (alive && seq === historySequence) historyError.value = toApiError(caught).message }
  finally { if (alive && seq === historySequence) historyLoading.value = false }
}
function toggleHistory(event: Event) { historyOpen.value = (event.target as HTMLDetailsElement).open; if (historyOpen.value && !historyLoaded.value) void loadHistory(1) }
function storageChanged(event: StorageEvent) {
  if (event.key?.startsWith(storagePrefix.value) || event.key === null) {
    if (!busy.value) {
      const wasPending = Boolean(pending.value)
      restore()
      if (dialog.value && pending.value) { mode.value = pending.value.action; reason.value = pending.value.reason; reviewing.value = true }
      else if (dialog.value && wasPending) { dialog.value = false; reviewing.value = false; notice.value = 'Bản chờ đã được xử lý hoặc thay đổi ở tab khác. Hãy đối chiếu danh sách mới nhất.'; void load(); if (historyOpen.value) void loadHistory(1) }
    }
  }
}
window.addEventListener('storage', storageChanged)
</script>

<template>
  <section class="record-card maintenance-expenses" aria-label="Chi phí sửa chữa">
    <header class="expense-heading"><div><h2>Chi phí sửa chữa</h2><p class="help-text">Liên kết khoản chi thực tế trong sổ thu chi. Mỗi khoản được tính một lần.</p></div><div class="button-row"><UiButton variant="secondary" :loading="loading" @click="load()">Làm mới chi phí</UiButton><UiButton @click="openLink">Liên kết khoản chi</UiButton></div></header>
    <UiNotice v-if="notice" tone="success">{{ notice }}</UiNotice>
    <UiNotice v-if="pending || storageError" tone="warning">Có yêu cầu liên kết đang chờ đối chiếu. <UiButton variant="ghost" @click="openLink">Mở yêu cầu đang chờ</UiButton></UiNotice>
    <UiPageState v-if="loading" kind="loading" heading-level="h3" title="Đang tải chi phí sửa chữa…" />
    <UiPageState v-else-if="error" kind="error" heading-level="h3" :description="error"><UiButton @click="load()">Thử lại chi phí</UiButton></UiPageState>
    <template v-else>
      <div v-if="summary" class="expense-total" role="group" aria-label="Tổng chi phí sửa chữa"><span>Tổng khoản chi còn hiệu lực</span><strong>{{ cashMoney(summary.total_amount) }}</strong><small>{{ summary.active_count }} khoản chi<span v-if="summary.deleted_count"> · {{ summary.deleted_count }} khoản đã xóa không tính vào tổng</span></small></div>
      <UiPageState v-if="!items.length" heading-level="h3" title="Chưa liên kết khoản chi" description="Bạn có thể chọn khoản đã ghi hoặc tạo khoản chi mới. Hoàn tất sửa chữa không tự ghi tiền." />
      <ul v-else class="expense-list" aria-label="Các khoản chi sửa chữa"><li v-for="item in items" :key="item.id"><article><div><h3>{{ item.cash_transaction.description }}</h3><p class="help-text">Khoản chi #{{ item.cash_transaction_id }} · {{ formatDateTime(item.cash_transaction.occurred_at) }}</p><UiBadge :tone="item.included_in_total ? 'warning' : 'neutral'">{{ item.included_in_total ? 'Đang tính vào chi phí' : 'Đã xóa khỏi sổ · Không tính vào tổng' }}</UiBadge></div><div class="expense-actions"><strong>{{ cashMoney(item.cash_transaction.amount) }}</strong><div class="button-row"><UiButton variant="secondary" :aria-label="`Xem khoản chi ${item.cash_transaction_id}`" @click="detailId = item.cash_transaction_id">Xem khoản chi</UiButton><UiButton variant="ghost" :aria-label="`Bỏ liên kết khoản chi ${item.cash_transaction_id}`" @click="openUnlink(item)">Bỏ liên kết</UiButton></div></div></article></li></ul>
      <OwnerPager v-if="total > 10" :page="page" :last-page="last" :total="total" :loading="loading" @change="load($event)" />
    </template>
    <p class="help-text expense-note">Chỉ chủ trọ xem phần chi phí. Bỏ liên kết không xóa khoản chi trong sổ; sửa, xóa hoặc khôi phục khoản gốc sẽ cập nhật tổng tương ứng.</p>
    <details class="expense-history" :open="historyOpen" @toggle="toggleHistory"><summary>Lịch sử liên kết chi phí</summary><UiPageState v-if="historyLoading" kind="loading" heading-level="h3" /><UiPageState v-else-if="historyError" kind="error" heading-level="h3" :description="historyError"><UiButton @click="loadHistory(historyPage)">Thử lại lịch sử liên kết</UiButton></UiPageState><template v-else><p v-if="!histories.length" class="help-text">Chưa có lịch sử liên kết.</p><ol v-else aria-label="Nhật ký liên kết khoản chi"><li v-for="entry in histories" :key="entry.id"><strong>{{ entry.action === 'linked' ? 'Liên kết' : 'Bỏ liên kết' }} khoản chi #{{ entry.cash_transaction_id }}</strong><p>{{ entry.actor.username }} · {{ formatDateTime(entry.created_at) }}</p><p v-if="entry.after.cash_snapshot">Số tiền khi ghi nhận: {{ cashMoney(entry.after.cash_snapshot.amount) }} · {{ entry.after.cash_snapshot.description }}</p><p class="expense-reason">Lý do: {{ entry.reason }}</p></li></ol><OwnerPager v-if="historyTotal > 10" :page="historyPage" :last-page="historyLast" :total="historyTotal" :loading="historyLoading" @change="loadHistory($event)" /><UiButton v-if="historyLoaded" variant="ghost" @click="loadHistory(1)">Làm mới lịch sử liên kết</UiButton></template></details>
    <UiDialog :open="dialog" :title="abandoning ? 'Đối chiếu trước khi bỏ yêu cầu' : reviewing ? 'Xem lại liên kết chi phí' : mode === 'unlink' ? 'Bỏ liên kết khoản chi' : 'Chọn khoản chi sửa chữa'" @update:open="value => { if (!value) close() }">
      <p v-if="client.error.value" class="form-error" role="alert">{{ client.error.value }}</p>
      <UiNotice v-if="storageError" tone="warning">{{ storageError }}</UiNotice>
      <template v-if="abandoning"><UiNotice tone="warning">Thao tác có thể đã được ghi nhận. Hãy đóng hộp thoại để xem danh sách và lịch sử liên kết, rồi quay lại đối chiếu trước khi bỏ bản chờ. Bỏ bản chờ không hoàn tác thao tác trên máy chủ.</UiNotice><p class="help-text">Nếu chưa rõ kết quả, gửi lại yêu cầu cũ để tìm đúng lần ghi nhận trước đó.</p></template>
      <template v-else-if="reviewing">
        <UiNotice v-if="pending" tone="warning">Yêu cầu được giữ nguyên để gửi lại sau lỗi mạng hoặc tải lại trang.</UiNotice>
        <dl class="record-facts review-box"><div><dt>Thao tác</dt><dd>{{ (pending?.action ?? mode) === 'link' ? 'Liên kết khoản chi' : 'Bỏ liên kết khoản chi' }}</dd></div><div><dt>Yêu cầu sửa chữa</dt><dd>#{{ requestId }}</dd></div><div><dt>Khoản chi</dt><dd>#{{ pending?.cash_transaction_id ?? chosen?.id }}</dd></div><div v-if="chosen"><dt>Nội dung / số tiền hiện tại</dt><dd>{{ chosen.description }} · {{ cashMoney(chosen.amount) }}</dd></div><div><dt>Lý do</dt><dd class="expense-reason">{{ pending?.reason ?? reason }}</dd></div></dl>
        <p class="help-text">Thao tác này không tạo thêm dòng tiền và không sửa nội dung khoản chi.</p>
      </template>
      <form v-else-if="!storageError" id="maintenance-expense-form" class="form-grid" @submit.prevent="review">
        <template v-if="mode === 'link'">
          <div class="button-row"><UiButton variant="secondary" @click="createExpense">Tạo khoản chi mới</UiButton></div>
          <p class="help-text">Khoản mới được ghi vào sổ trước, sau đó bạn xác nhận liên kết tại đây.</p>
          <label>Tìm khoản chi đã ghi<input v-model="search" class="ui-input" maxlength="255" @keydown.enter.prevent="loadCandidates(1)" /></label><UiButton variant="secondary" :loading="candidateLoading" @click="loadCandidates(1)">Tìm khoản chi</UiButton>
          <p v-if="candidateError" class="form-error" role="alert">{{ candidateError }} <UiButton variant="ghost" @click="loadCandidates(candidatePage)">Thử lại danh sách</UiButton></p><p v-else-if="candidateLoading" role="status">Đang tìm khoản chi…</p><p v-else-if="!candidates.length" class="help-text">Không có khoản chi còn hiệu lực phù hợp.</p>
          <fieldset class="expense-choices"><legend class="sr-only">Khoản chi có thể chọn</legend><label v-for="entry in candidates" :key="entry.id"><input type="radio" name="repair-expense-choice" :checked="chosen?.id === entry.id" @change="choose(entry)" /><span><strong>#{{ entry.id }} · {{ entry.description }}</strong><small>{{ cashMoney(entry.amount) }} · {{ formatDateTime(entry.occurred_at) }}</small></span></label></fieldset>
          <OwnerPager v-if="candidateTotal > 10" :page="candidatePage" :last-page="candidateLast" :total="candidateTotal" :loading="candidateLoading" @change="loadCandidates($event)" />
        </template>
        <div v-if="chosen" class="review-box"><strong>Đã chọn khoản chi #{{ chosen.id }}</strong><p>{{ chosen.description }} · {{ cashMoney(chosen.amount) }}</p><p v-if="selecting" role="status">Đang kiểm tra liên kết hiện tại…</p><p v-if="selectError" class="form-error" role="alert">{{ selectError }} <UiButton variant="ghost" @click="choose(chosen)">Kiểm tra lại</UiButton></p><UiNotice v-if="mode === 'link' && selectionBlocked" tone="warning">Khoản này đã liên kết với yêu cầu #{{ association?.maintenance_request_id }}. Hãy đối chiếu và bỏ liên kết cũ ở yêu cầu đó trước khi chuyển khoản chi.<RouterLink v-if="association" class="record-link" :to="`/owner/maintenance/${association.maintenance_request_id}`" @click="close">Mở yêu cầu đang liên kết</RouterLink></UiNotice></div>
        <OwnerField v-model="reason" label="Lý do liên kết / bỏ liên kết" name="reason" :errors="client.errors.value" :max-length="1000" hint="Từ 3 đến 1.000 ký tự; được giữ trong lịch sử của chủ trọ." required />
        <p v-if="mode === 'unlink'" class="help-text">Khoản chi vẫn còn trong sổ thu chi sau khi bỏ liên kết.</p>
      </form>
      <template #actions>
        <UiButton variant="secondary" :disabled="busy" @click="close">Đóng</UiButton>
        <template v-if="abandoning"><UiButton variant="secondary" @click="abandoning = false">Quay lại yêu cầu</UiButton><UiButton variant="danger" @click="abandon">Đã đối chiếu, bỏ bản chờ</UiButton></template>
        <template v-else><UiButton v-if="pending || storageError" variant="ghost" :disabled="busy" @click="abandoning = true">Đối chiếu và bỏ bản chờ</UiButton><UiButton v-if="reviewing && !pending" variant="secondary" @click="reviewing = false">Chỉnh lại</UiButton><UiButton v-if="!reviewing && !storageError" type="submit" form="maintenance-expense-form" :disabled="!chosen || selecting || !!selectError || mode === 'link' && selectionBlocked">Xem lại liên kết</UiButton><UiButton v-if="reviewing" :loading="busy" @click="storageError = ''; send()">{{ pending ? 'Gửi lại yêu cầu đang chờ' : mode === 'unlink' ? 'Xác nhận bỏ liên kết' : 'Xác nhận liên kết' }}</UiButton></template>
      </template>
    </UiDialog>
    <CashCreateDialog :open="creating" @close="creating = false" @saved="created" />
    <CashDetailDialog :open="detailId !== null" :id="detailId" @close="detailId = null" @changed="cashChanged" />
  </section>
</template>

<style scoped>
.expense-heading { display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 16px; }
.expense-heading h2 { margin-bottom: 6px; }.expense-total { display: grid; gap: 8px; margin: 20px 0; padding: 18px; background: var(--color-primary-soft); border-radius: 12px; }.expense-total strong { font-size: 26px; color: var(--color-primary); overflow-wrap: anywhere; }.expense-total span, .expense-total small { font-size: 13px; color: var(--color-text-secondary); }
.expense-list { list-style: none; margin: 0; padding: 0; }.expense-list article { display: flex; justify-content: space-between; align-items: start; gap: 24px; padding: 20px 0; border-bottom: 1px solid var(--color-border); }.expense-list h3 { font-size: 16px; line-height: 1.7; overflow-wrap: anywhere; }.expense-list article > div { min-width: 0; }.expense-actions { display: grid; gap: 12px; justify-items: end; }.expense-actions > strong { white-space: nowrap; }.expense-note { margin-top: 20px; line-height: 1.8; }
.expense-history { margin-top: 24px; border-top: 1px solid var(--color-border); padding-top: 14px; }.expense-history summary { cursor: pointer; color: var(--color-primary); padding: 8px 0; font-size: 14px; }.expense-history ol { padding-left: 20px; }.expense-history li { padding: 14px 0; font-size: 14px; border-bottom: 1px solid var(--color-border); }.expense-history li p { font-size: 13px; color: var(--color-text-secondary); }.expense-reason { white-space: pre-wrap; overflow-wrap: anywhere; }
.expense-choices { margin: 0; padding: 0; border: 0; display: grid; gap: 8px; }.expense-choices > label { display: flex; align-items: start; gap: 12px; padding: 14px; border: 1px solid var(--color-border); border-radius: 10px; cursor: pointer; }.expense-choices input { width: 18px; height: 18px; flex-shrink: 0; margin-top: 3px; }.expense-choices span { min-width: 0; }.expense-choices strong, .expense-choices small { display: block; font-size: 13px; line-height: 1.7; overflow-wrap: anywhere; }.expense-choices small { color: var(--color-text-secondary); }
@media (max-width: 650px) { .expense-list article { flex-direction: column; gap: 14px; }.expense-actions { justify-items: start; }.expense-actions > strong { white-space: normal; overflow-wrap: anywhere; }.expense-heading > .button-row { width: 100%; } }
</style>

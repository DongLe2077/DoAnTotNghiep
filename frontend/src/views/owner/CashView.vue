<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import CashCreateDialog from '@/components/cash/CashCreateDialog.vue'
import CashDetailDialog from '@/components/cash/CashDetailDialog.vue'
import { eventPayload, localEventTime, useOwnerApi } from '@/composables/useOwnerApi'
import { ApiError, toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import { cashMoney, cashTypeLabel, type CashEntry, type CashEntryType, type CashSummary, type CashTransaction } from '@/types/cash'
import type { PageResult } from '@/types/owner'

interface Row { key: string; type: CashEntryType; amount: string; occurredAt: string; description: string; cashId: number | null; invoiceId: number | null; deleted: boolean }
const client = useOwnerApi(), creating = ref(false), detailId = ref<number | null>(null), notice = ref('')
const rows = ref<Row[]>([]), summary = ref<CashSummary | null>(null), loading = ref(false), error = ref('')
const page = ref(1), lastPage = ref(1), total = ref(0), state = ref<'active' | 'deleted'>('active')
const periodsOpen = ref(false)
const route = useRoute()
const initialMonth = typeof route.query.month === 'string' && /^[1-9]\d{3}-(?:0[1-9]|1[0-2])$/.test(route.query.month) ? route.query.month : localEventTime().slice(0, 7)
const initialType = typeof route.query.entry_type === 'string' && ['invoice_payment', 'other_income', 'expense'].includes(route.query.entry_type) ? route.query.entry_type : ''
const filters = reactive({ timeMode: 'month', month: initialMonth, from: '', until: '', entryType: initialType, q: '', groupBy: 'day' })
const applied = ref<Record<string, unknown>>({ month: filters.month, group_by: 'day', ...(initialType ? { entry_type: initialType } : {}) })
let sequence = 0, alive = true
const filtered = computed(() => Boolean(applied.value.q || applied.value.entry_type))
onBeforeUnmount(() => { sequence++; alive = false })
function query() {
  const params: Record<string, unknown> = { group_by: filters.groupBy }
  if (filters.timeMode === 'month') params.month = filters.month
  else { params.from = filters.from ? eventPayload(filters.from) : '1000-01-01 00:00:00'; if (filters.until) params.until = eventPayload(filters.until) }
  if (filters.q.trim()) params.q = filters.q.trim()
  if (filters.entryType) params.entry_type = filters.entryType
  return params
}
function apply() { applied.value = query(); void load(1) }
function switchState(next: 'active' | 'deleted') {
  if (state.value === next) return
  state.value = next
  if (next === 'deleted' && filters.entryType === 'invoice_payment') { filters.entryType = ''; applied.value = { ...applied.value }; delete applied.value.entry_type }
  void load(1)
}
async function load(nextPage = page.value) {
  const seq = ++sequence, tab = state.value
  loading.value = true; error.value = ''; rows.value = []; summary.value = null
  try {
    const params: Record<string, unknown> = { ...applied.value, page: nextPage, per_page: 15 }
    let data: Row[], meta: PageResult<CashEntry>['meta'], totals: CashSummary | null = null
    if (tab === 'active') {
      const [result, summaryResult] = await Promise.all([client.get<PageResult<CashEntry>>('/cash-flow', params), client.get<{ data: CashSummary }>('/cash-flow/summary', applied.value)])
      meta = result.meta; totals = summaryResult.data
      data = result.data.map(item => ({ key: item.id, type: item.entry_type, amount: item.amount, occurredAt: item.occurred_at, description: item.description, cashId: item.source === 'cash_transaction' ? item.source_id : null, invoiceId: item.source === 'payment' ? item.invoice_id : null, deleted: false }))
    } else {
      const { entry_type, group_by: _groupBy, ...rest } = params
      const result = await client.get<PageResult<CashTransaction>>('/cash-transactions', { ...rest, state: 'deleted', ...(entry_type === 'expense' || entry_type === 'other_income' ? { transaction_type: entry_type } : {}) })
      meta = result.meta
      data = result.data.map(item => ({ key: `cash_transaction:${item.id}`, type: item.transaction_type, amount: item.amount, occurredAt: item.occurred_at, description: item.description, cashId: item.id, invoiceId: null, deleted: true }))
    }
    if (!alive || seq !== sequence) return
    if (!meta || !Number.isSafeInteger(meta.last_page) || meta.last_page < 1) throw new ApiError(502, 'Dữ liệu sổ thu chi chưa hợp lệ. Vui lòng tải lại.')
    if (nextPage > meta.last_page) { await load(meta.last_page); return }
    rows.value = data; summary.value = totals; page.value = meta.current_page; lastPage.value = meta.last_page; total.value = meta.total
  } catch (caught) { if (alive && seq === sequence) error.value = toApiError(caught).message }
  finally { if (alive && seq === sequence) loading.value = false }
}
function saved(entry: CashTransaction, replayed: boolean) {
  notice.value = entry.is_deleted ? 'Yêu cầu này đã được ghi nhận trước đó; khoản gốc hiện đã xóa. Mở nhật ký để đối chiếu hoặc khôi phục.' : replayed ? 'Đã đối chiếu yêu cầu và tìm thấy đúng khoản đã ghi nhận trước đó.' : 'Đã ghi nhận khoản thu chi.'
  detailId.value = entry.id; void load(1)
}
function changed() { notice.value = 'Đã cập nhật sổ thu chi theo thay đổi mới nhất.'; void load() }
void load(1)
</script>

<template>
  <section class="management-page cash-page">
    <header class="management-header workflow-header">
      <div><h1>{{ state === 'active' ? 'Thu chi' : 'Các khoản đã xóa' }}</h1></div>
      <div class="button-row">
        <UiButton @click="creating = true">Thêm khoản thu chi</UiButton>
        <UiButton variant="ghost" @click="switchState(state === 'active' ? 'deleted' : 'active')">{{ state === 'active' ? 'Khoản đã xóa' : 'Sổ thu chi' }}</UiButton>
      </div>
    </header>
    <UiNotice v-if="notice" tone="success">{{ notice }}</UiNotice>
    <form class="management-toolbar" @submit.prevent="apply">
      <label v-if="filters.timeMode === 'month'">Tháng phát sinh<input v-model="filters.month" type="month" class="ui-input" required /></label>
      <template v-else><label>Từ thời điểm (giờ Việt Nam)<input v-model="filters.from" type="datetime-local" step="1" class="ui-input" /></label><label>Đến thời điểm (giờ Việt Nam)<input v-model="filters.until" type="datetime-local" step="1" class="ui-input" /></label></template>
      <label>Loại khoản<select v-model="filters.entryType" class="ui-select"><option value="">Tất cả</option><option v-if="state === 'active'" value="invoice_payment">Thu hóa đơn</option><option value="other_income">Thu khác</option><option value="expense">Chi phí</option></select></label>
      <label>Tìm nội dung hoặc mã hóa đơn<input v-model="filters.q" class="ui-input" maxlength="255" /></label>
      <UiButton variant="secondary" type="submit" :loading="loading">Áp dụng bộ lọc</UiButton>
      <details class="workflow-disclosure">
        <summary>Bộ lọc thêm<span v-if="applied.from || applied.group_by === 'month'"> · Đang áp dụng</span></summary>
        <div class="workflow-disclosure-body workflow-extra-fields">
          <label>Khoảng thời gian<select v-model="filters.timeMode" class="ui-select"><option value="month">Theo tháng</option><option value="range">Từ ngày / đến ngày</option></select></label>
          <label v-if="state === 'active'">Nhóm báo cáo<select v-model="filters.groupBy" class="ui-select"><option value="day">Theo ngày</option><option value="month">Theo tháng</option></select></label>
          <UiButton variant="ghost" :loading="loading" @click="load()">Làm mới sổ</UiButton>
        </div>
      </details>
    </form>
    <UiNotice v-if="state === 'deleted'" tone="info">Khoản đã xóa không tính vào tổng thu chi. Mở nhật ký và hoàn tác thao tác mới nhất để khôi phục.</UiNotice>
    <UiPageState v-if="loading" kind="loading" title="Đang tải sổ thu chi" />
    <UiPageState v-else-if="error" kind="error" :description="error"><UiButton @click="load()">Thử tải lại sổ</UiButton></UiPageState>
    <template v-else>
      <template v-if="summary">
        <dl class="record-card cash-totals" aria-label="Tổng hợp thu chi">
          <div><dt>Tổng thu thực tế</dt><dd>{{ cashMoney(summary.total_income) }}</dd></div>
          <div><dt>Tổng chi phí</dt><dd>{{ cashMoney(summary.expense_total) }}</dd></div>
          <div><dt>Chênh lệch thu − chi</dt><dd :class="{ 'cash-negative': summary.net_cash_flow.startsWith('-') }">{{ cashMoney(summary.net_cash_flow) }}</dd></div>
        </dl>
        <p v-if="filtered" class="help-text">Số liệu theo bộ lọc đang áp dụng.</p>
      </template>
      <h2 v-if="state === 'active'">Các khoản phát sinh</h2>
      <UiPageState v-if="!rows.length" title="Chưa có khoản thu chi phù hợp" description="Thay đổi bộ lọc thời gian, nội dung hoặc thêm khoản mới khi có phát sinh." />
      <div v-else class="cash-ledger"><article v-for="row in rows" :key="row.key" class="record-card cash-ledger-row"><div class="cash-ledger-body"><div class="button-row"><UiBadge :tone="row.type === 'expense' ? 'warning' : 'success'">{{ cashTypeLabel(row.type) }}</UiBadge><span class="help-text">{{ formatDateTime(row.occurredAt) }}</span></div><h3>{{ row.description }}</h3><p v-if="row.deleted" class="help-text">Đã xóa · Có thể xem nhật ký để khôi phục</p></div><div class="cash-ledger-side"><strong :class="{ 'cash-negative': row.type === 'expense' }">{{ row.type === 'expense' ? '−' : '+' }}{{ cashMoney(row.amount) }}</strong><UiButton v-if="row.cashId" variant="secondary" :aria-label="`Chi tiết và nhật ký: ${row.description}`" @click="detailId = row.cashId">Chi tiết và nhật ký</UiButton><RouterLink v-else-if="row.invoiceId" class="record-link" :to="`/owner/invoices/${row.invoiceId}`">Xem hóa đơn</RouterLink></div></article></div>
      <OwnerPager v-if="total > 0" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" />
      <details v-if="summary" class="workflow-disclosure cash-periods" :open="periodsOpen" @toggle="periodsOpen = ($event.currentTarget as HTMLDetailsElement).open">
        <summary>Báo cáo thu chi chi tiết</summary>
        <div class="workflow-disclosure-body">
          <p class="help-text">Thu hóa đơn: {{ cashMoney(summary.invoice_payment_total) }} · Thu khác: {{ cashMoney(summary.other_income_total) }}. Chênh lệch chưa gồm số dư đầu kỳ.</p>
          <h2 v-if="summary.periods.length">{{ summary.group_by === 'day' ? 'Tổng hợp từng ngày' : 'Tổng hợp từng tháng' }}</h2>
          <p v-if="summary.periods.length" class="help-text cash-scroll-hint">Vuốt ngang bảng để xem các cột còn lại.</p>
          <div v-if="summary.periods.length" class="cash-table-wrap" tabindex="0" role="region" aria-label="Bảng tổng hợp thu chi theo kỳ">
            <table>
              <thead><tr><th scope="col">Kỳ phát sinh</th><th scope="col">Tổng thu</th><th scope="col">Tổng chi</th><th scope="col">Chênh lệch</th><th scope="col">Số khoản</th></tr></thead>
              <tbody><tr v-for="period in summary.periods" :key="period.period"><th scope="row">{{ period.period.split('-').reverse().join('/') }}</th><td>{{ cashMoney(period.total_income) }}</td><td>{{ cashMoney(period.expense_total) }}</td><td>{{ cashMoney(period.net_cash_flow) }}</td><td>{{ period.entry_count }}</td></tr></tbody>
            </table>
          </div>
        </div>
      </details>
    </template>
    <CashCreateDialog :open="creating" @close="creating = false" @saved="saved" />
    <CashDetailDialog :open="detailId !== null" :id="detailId" @close="detailId = null" @changed="changed" />
  </section>
</template>

<style scoped>
.cash-totals { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 0 0 16px; }
.cash-totals dt { color: var(--color-text-secondary); font-size: 13px; }
.cash-totals dd { margin: 4px 0 0; font-size: clamp(16px, 4vw, 24px); font-weight: 650; overflow-wrap: anywhere; }
.cash-ledger { display: grid; gap: 10px; }
.cash-ledger-row { display: flex; align-items: center; justify-content: space-between; gap: 12px 24px; padding: 16px; }
.cash-ledger-body { min-width: 0; }
.cash-ledger-body h3 { margin-top: 4px; line-height: 1.6; }
.cash-ledger-side { display: grid; gap: 4px; justify-items: end; min-width: 0; }
.cash-ledger-side > strong { color: var(--color-primary); font-size: 19px; overflow-wrap: anywhere; }
.cash-negative { color: var(--color-danger) !important; }
.cash-periods { margin-top: 16px; }
.cash-table-wrap { max-width: 100%; overflow-x: auto; }
.cash-scroll-hint { display: none; }
table { width: 100%; min-width: 560px; border-collapse: collapse; font-size: 14px; text-align: right; }
th, td { padding: 12px 10px; border-bottom: 1px solid var(--color-border); }
th { font-weight: 500; color: var(--color-text-secondary); }
th:first-child { text-align: left; }
@media (max-width: 600px) {
  .cash-scroll-hint { display: block; }
  th:first-child { position: sticky; inset-inline-start: 0; background: var(--color-surface); }
  .cash-totals { grid-template-columns: 1fr; padding: 12px 16px; }
  .cash-totals > div { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 4px 12px; }
  .cash-totals dd { margin: 0; }
  .cash-ledger-row { flex-direction: column; align-items: start; }
  .cash-ledger-side { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 4px 12px; width: 100%; }
}
</style>

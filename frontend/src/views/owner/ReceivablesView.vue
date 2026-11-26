<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import { useRoute, type LocationQueryRaw } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import RemotePicker from '@/components/owner/RemotePicker.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import { useOwnerApi } from '@/composables/useOwnerApi'
import { toApiError } from '@/lib/api'
import { dueDateText, dueStatusLabel } from '@/lib/invoice-controls'
import { formatMoney } from '@/lib/format'
import type { PageResult } from '@/types/owner'
import type { InvoiceSummary, OutstandingInvoice, ReceivableGroup } from '@/types/reporting'
const route = useRoute(), { get } = useOwnerApi()
function initialId(key: string) { const value = Number(route.query[key]); return Number.isSafeInteger(value) && value > 0 ? [value] : [] }
const dueStatus = ref(typeof route.query.due_status === 'string' && ['overdue', 'upcoming', 'not_set'].includes(route.query.due_status) ? route.query.due_status : '')
const month = ref(typeof route.query.billing_month === 'string' ? route.query.billing_month : '')
const rooms = ref<number[]>(initialId('room_id')), groups = ref<number[]>(initialId('rental_group_id'))
const view = ref<'invoice' | 'room' | 'rental_group'>('invoice'), filterOpen = ref(false)
const loading = ref(true), error = ref(''), summary = ref<InvoiceSummary | null>(null), invoices = ref<OutstandingInvoice[]>([]), grouped = ref<ReceivableGroup[]>([])
const page = ref(1), lastPage = ref(1), total = ref(0)
let sequence = 0, alive = true
const applied = ref<Record<string, unknown>>({}), activeView = ref<'invoice' | 'room' | 'rental_group'>('invoice')
onBeforeUnmount(() => { alive = false; sequence++ })
const scopeDescription = computed(() => applied.value.billing_month ? `Kỳ hóa đơn ${applied.value.billing_month}` : 'Mọi tháng tính tiền')
function filter() { applied.value = { ...(dueStatus.value ? { due_status: dueStatus.value } : {}), ...(month.value ? { billing_month: month.value } : {}), ...(rooms.value[0] ? { room_id: rooms.value[0] } : {}), ...(groups.value[0] ? { rental_group_id: groups.value[0] } : {}), ...(initialId('occupancy_id')[0] ? { occupancy_id: initialId('occupancy_id')[0] } : {}) }; activeView.value = view.value; void load(1) }
async function load(next = page.value) {
  const current = ++sequence; loading.value = true; error.value = ''; invoices.value = []; grouped.value = []; summary.value = null
  try {
    const selectedView = activeView.value
    const endpoint = selectedView === 'invoice' ? '/receivables' : '/receivables/groups'
    const results = await Promise.all([get<{ data: InvoiceSummary }>('/receivables/summary', applied.value), get<PageResult<OutstandingInvoice | ReceivableGroup>>(endpoint, { ...applied.value, ...(selectedView !== 'invoice' ? { group_by: selectedView } : {}), page: next, per_page: 15 })])
    if (!alive || current !== sequence) return
    const [aggregate, list] = results
    if (next > list.meta.last_page) { await load(list.meta.last_page); return }
    summary.value = aggregate.data; page.value = list.meta.current_page; lastPage.value = list.meta.last_page; total.value = list.meta.total
    if (selectedView === 'invoice') invoices.value = list.data as OutstandingInvoice[]
    else grouped.value = list.data as ReceivableGroup[]
  } catch (caught) { if (alive && current === sequence) error.value = toApiError(caught).message }
  finally { if (alive && current === sequence) loading.value = false }
}
function groupQuery(item: ReceivableGroup): LocationQueryRaw {
  const query: LocationQueryRaw = {}
  for (const [key, value] of Object.entries(applied.value)) if (typeof value === 'string' || typeof value === 'number') query[key] = value
  query[item.group_by === 'room' ? 'room_id' : 'rental_group_id'] = item.group_id
  return query
}
filter()
</script>
<template>
  <section class="management-page">
    <header class="management-header workflow-header">
      <div><h1>Công nợ</h1><p>Hóa đơn còn cần thu, gồm cả đợt thuê đã kết thúc.</p></div>
      <UiButton variant="ghost" :loading="loading" @click="load()">Làm mới</UiButton>
    </header>
    <form class="management-toolbar" @submit.prevent="filter">
      <label>Hạn thanh toán<select v-model="dueStatus" class="ui-select"><option value="">Tất cả công nợ</option><option value="overdue">Quá hạn</option><option value="upcoming">Chưa quá hạn</option><option value="not_set">Chưa đặt hạn</option></select></label>
      <label>Kỳ hóa đơn<input v-model="month" type="month" class="ui-input" aria-describedby="receivables-month-hint" /></label>
      <UiButton type="submit" :disabled="loading">Áp dụng bộ lọc</UiButton>
      <p id="receivables-month-hint" class="toolbar-help">Để trống kỳ hóa đơn để xem công nợ mọi tháng.</p>
      <details class="workflow-disclosure" @toggle="filterOpen = ($event.currentTarget as HTMLDetailsElement).open">
        <summary>Bộ lọc thêm<span v-if="applied.room_id || applied.rental_group_id || activeView !== 'invoice'"> · Đang lọc</span></summary>
        <div class="workflow-disclosure-body workflow-extra-fields">
          <div class="filter-field">
            <label for="receivables-view">Cách xem</label>
            <select id="receivables-view" v-model="view" class="ui-select"><option value="invoice">Từng hóa đơn</option><option value="room">Gộp theo phòng</option><option value="rental_group">Gộp theo nhóm thuê</option></select>
          </div>
          <div v-if="filterOpen" class="management-grid extra-pickers">
            <RemotePicker v-model="rooms" label="Phòng" path="/rooms" label-key="room_number" />
            <RemotePicker v-model="groups" label="Nhóm thuê" path="/rental-groups" label-key="group_code" />
          </div>
        </div>
      </details>
    </form>
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="error" kind="error" :description="error"><UiButton @click="load()">Tải lại</UiButton></UiPageState>
    <template v-else-if="summary">
      <div class="management-grid workflow-grid">
        <article class="stat-card"><span>Tổng còn cần thu</span><strong class="stat-money">{{ formatMoney(summary.outstanding_amount) }}</strong><small>{{ scopeDescription }} · {{ summary.open_invoice_count }} hóa đơn</small></article>
        <article class="stat-card"><span>Công nợ quá hạn</span><strong class="stat-money">{{ formatMoney(summary.overdue_amount ?? '0.00') }}</strong><small>{{ summary.overdue_count ?? 0 }} hóa đơn · Tính đến hôm nay</small></article>
      </div>
      <UiNotice v-if="summary.inconsistent_count" tone="warning">Có {{ summary.inconsistent_count }} hóa đơn cần đối chiếu, đã loại khỏi tổng tiền hợp lệ. <RouterLink class="record-link" to="/owner/invoices?payment_status=inconsistent">Xem hóa đơn</RouterLink></UiNotice>
      <UiPageState v-if="!total" title="Không có công nợ phù hợp" description="Các hóa đơn trong phạm vi này đã thu đủ hoặc chưa được phát hành." />
      <div v-else class="management-grid dashboard-section workflow-grid">
        <article v-for="invoice in invoices" :key="invoice.id" class="record-card workflow-list-card">
          <header>
            <div><h2>Phòng {{ invoice.room.room_number }}</h2><span class="help-text">{{ invoice.invoice_number }} · Kỳ {{ invoice.billing_month }}</span></div>
            <UiBadge tone="warning">{{ invoice.payment_status === 'partially_paid' ? 'Đã thu một phần' : 'Chưa thu' }}</UiBadge>
          </header>
          <div class="workflow-list-amount"><span class="workflow-caption">Còn cần thu</span><strong class="workflow-amount">{{ formatMoney(invoice.outstanding_amount) }}</strong></div>
          <p class="workflow-deadline"><strong>{{ invoice.due_date ? `Hạn ${dueDateText(invoice.due_date)}` : 'Chưa đặt hạn' }}</strong><span v-if="invoice.due_status === 'overdue'">{{ dueStatusLabel(invoice) }}</span></p>
          <footer><RouterLink class="workflow-card-link" :to="`/owner/invoices/${invoice.id}`">Xem hóa đơn / Thu tiền →</RouterLink></footer>
        </article>
        <article v-for="group in grouped" :key="group.group_id" class="record-card workflow-list-card">
          <header><div><h2>{{ group.group_by === 'room' ? 'Phòng' : 'Nhóm thuê' }} {{ group.group_label }}</h2><span class="help-text">{{ group.invoice_count }} hóa đơn còn nợ</span></div></header>
          <div class="workflow-list-amount"><span class="workflow-caption">Còn cần thu</span><strong class="workflow-amount">{{ formatMoney(group.outstanding_amount) }}</strong></div>
          <footer><RouterLink class="workflow-card-link" :to="{ path: '/owner/invoices', query: groupQuery(group) }">Xem các hóa đơn trong phạm vi →</RouterLink></footer>
        </article>
      </div>
      <OwnerPager v-if="total > 0" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" />
      <details class="workflow-disclosure">
        <summary>Thống kê công nợ chi tiết</summary>
        <div class="workflow-disclosure-body">
          <dl class="record-facts">
            <div><dt>Phòng còn nợ</dt><dd>{{ summary.outstanding_room_count }}</dd></div>
            <div><dt>Nhóm thuê còn nợ</dt><dd>{{ summary.outstanding_group_count }}</dd></div>
            <div><dt>Đã thu trong phạm vi hóa đơn</dt><dd>{{ formatMoney(summary.paid_amount) }}</dd></div>
          </dl>
          <p class="help-text">Tổng đã thu gồm cả hóa đơn đã trả đủ trong phạm vi đang lọc.</p>
        </div>
      </details>
    </template>
  </section>
</template>

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
function filter() { applied.value = { ...(dueStatus.value ? { due_status: dueStatus.value } : {}), ...(month.value ? { billing_month: month.value } : {}), ...(rooms.value[0] ? { room_id: rooms.value[0] } : {}), ...(groups.value[0] ? { rental_group_id: groups.value[0] } : {}), ...(initialId('occupancy_id')[0] ? { occupancy_id: initialId('occupancy_id')[0] } : {}) }; activeView.value = view.value; void load(1); filterOpen.value = false }
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
  <section class="management-page"><header class="management-header"><div><span class="eyebrow">Không gian chủ trọ</span><h1>Công nợ</h1><p>Các hóa đơn còn cần thu, gồm cả những đợt thuê đã kết thúc.</p></div><UiButton variant="secondary" :loading="loading" @click="load()">Làm mới</UiButton></header>
    <form class="management-toolbar" @submit.prevent="filter"><label>Hạn thanh toán<select v-model="dueStatus" class="ui-select"><option value="">Tất cả công nợ</option><option value="overdue">Quá hạn</option><option value="upcoming">Chưa quá hạn</option><option value="not_set">Chưa đặt hạn</option></select></label><label>Kỳ hóa đơn<input v-model="month" type="month" class="ui-input" /><span class="help-text">Để trống để xem mọi tháng</span></label><label>Cách xem<select v-model="view" class="ui-select"><option value="invoice">Từng hóa đơn</option><option value="room">Gộp theo phòng</option><option value="rental_group">Gộp theo nhóm thuê</option></select></label><UiButton variant="secondary" @click="filterOpen = !filterOpen">Chọn phòng / nhóm thuê{{ rooms.length || groups.length ? ' · Có lựa chọn' : '' }}</UiButton><UiButton type="submit" :disabled="loading">Áp dụng bộ lọc</UiButton><div v-if="filterOpen" class="form-grid" style="width: 100%"><RemotePicker v-model="rooms" label="Phòng" path="/rooms" label-key="room_number" /><RemotePicker v-model="groups" label="Nhóm thuê" path="/rental-groups" label-key="group_code" /></div></form>
    <UiPageState v-if="loading" kind="loading" /><UiPageState v-else-if="error" kind="error" :description="error"><UiButton @click="load()">Thử lại</UiButton></UiPageState>
    <template v-else-if="summary"><div class="dashboard-stats"><article class="stat-card"><span>Tổng còn cần thu</span><strong class="stat-money">{{ formatMoney(summary.outstanding_amount) }}</strong><small>{{ scopeDescription }} · {{ summary.open_invoice_count }} hóa đơn</small></article><article class="stat-card"><span>Công nợ quá hạn</span><strong class="stat-money">{{ formatMoney(summary.overdue_amount ?? '0.00') }}</strong><small>{{ summary.overdue_count ?? 0 }} hóa đơn · Tính đến hôm nay</small></article><article class="stat-card"><span>Phòng còn nợ</span><strong>{{ summary.outstanding_room_count }}</strong><small>{{ summary.outstanding_group_count }} nhóm thuê</small></article><article class="stat-card"><span>Đã thu trong phạm vi hóa đơn</span><strong class="stat-money">{{ formatMoney(summary.paid_amount) }}</strong><small>Tính đến hiện tại, gồm cả hóa đơn đã thu đủ</small></article></div>
      <UiNotice v-if="summary.inconsistent_count" tone="warning">Có {{ summary.inconsistent_count }} hóa đơn cần đối chiếu, đã loại khỏi tổng tiền hợp lệ. <RouterLink class="record-link" to="/owner/invoices?payment_status=inconsistent">Xem hóa đơn</RouterLink></UiNotice>
      <UiPageState v-if="!total" title="Không có công nợ phù hợp" description="Các hóa đơn trong phạm vi này đã thu đủ hoặc chưa được phát hành." />
      <div v-else class="management-grid dashboard-section"><article v-for="invoice in invoices" :key="invoice.id" class="record-card"><header><div><h2>{{ invoice.invoice_number }}</h2><span class="help-text">Phòng {{ invoice.room.room_number }} · {{ invoice.group.group_code }}</span></div><UiBadge tone="warning">{{ invoice.payment_status === 'partially_paid' ? 'Đã thu một phần' : 'Chưa thu' }}</UiBadge></header><dl class="record-facts"><div><dt>Hạn thanh toán</dt><dd>{{ dueDateText(invoice.due_date) }} · {{ dueStatusLabel(invoice) }}</dd></div><div><dt>Kỳ hóa đơn</dt><dd>{{ invoice.billing_month }}</dd></div><div><dt>Tổng hóa đơn</dt><dd>{{ formatMoney(invoice.total_amount) }}</dd></div><div><dt>Đã thu</dt><dd>{{ formatMoney(invoice.paid_amount) }}</dd></div><div><dt>Còn cần thu</dt><dd class="ui-money">{{ formatMoney(invoice.outstanding_amount) }}</dd></div></dl><footer><RouterLink class="record-link" :to="`/owner/invoices/${invoice.id}`">Xem hóa đơn / Thu tiền →</RouterLink></footer></article>
      <article v-for="group in grouped" :key="group.group_id" class="record-card"><h2>{{ group.group_by === 'room' ? 'Phòng' : 'Nhóm thuê' }} {{ group.group_label }}</h2><dl class="record-facts"><div><dt>Hóa đơn còn nợ</dt><dd>{{ group.invoice_count }}</dd></div><div><dt>Tổng tiền các hóa đơn còn nợ</dt><dd>{{ formatMoney(group.total_amount) }}</dd></div><div><dt>Đã thu</dt><dd>{{ formatMoney(group.paid_amount) }}</dd></div><div><dt>Còn cần thu</dt><dd class="ui-money">{{ formatMoney(group.outstanding_amount) }}</dd></div></dl><footer><RouterLink class="record-link" :to="{ path: '/owner/invoices', query: groupQuery(group) }">Xem các hóa đơn trong phạm vi →</RouterLink></footer></article></div>
      <OwnerPager v-if="total > 0" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" /><p class="help-text">Công nợ theo hóa đơn/phòng/nhóm; không chia thành nợ cá nhân của từng thành viên. Tổng nhóm chỉ gồm hóa đơn còn nợ; tổng hợp ở trên gồm cả hóa đơn đã trả trong cùng phạm vi.</p>
    </template>
  </section>
</template>

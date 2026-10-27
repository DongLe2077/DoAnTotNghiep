<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import DashboardCharts from '@/components/reporting/DashboardCharts.vue'
import { localEventTime, useOwnerApi } from '@/composables/useOwnerApi'
import { formatMoney } from '@/lib/format'
import { formatDateTime } from '@/lib/notifications'
import { toApiError } from '@/lib/api'
import type { Dashboard } from '@/types/reporting'
const { get } = useOwnerApi()
const month = ref(localEventTime().slice(0, 7)), data = ref<Dashboard | null>(null), loading = ref(true), error = ref('')
let sequence = 0, alive = true
onBeforeUnmount(() => { alive = false; sequence++ })
async function load() {
  const current = ++sequence
  loading.value = true; data.value = null; error.value = ''
  try { const result = await get<{ data: Dashboard }>('/dashboard', { month: month.value }); if (alive && current === sequence) data.value = result.data }
  catch (caught) { if (alive && current === sequence) error.value = toApiError(caught).message }
  finally { if (alive && current === sequence) loading.value = false }
}
const tasks = computed(() => data.value ? [
  { title: 'Yêu cầu sửa chữa đang chờ', count: data.value.maintenance.open_count, to: '/owner/maintenance', description: `${data.value.maintenance.submitted_count} mới gửi · ${data.value.maintenance.in_progress_count} đang xử lý` },
  { title: 'Hóa đơn quá hạn', count: data.value.receivables.overdue_count ?? 0, to: '/owner/receivables?due_status=overdue', description: `Còn ${formatMoney(data.value.receivables.overdue_amount ?? '0.00')} quá hạn thanh toán` },
  { title: 'Hóa đơn còn cần thu', count: data.value.receivables.open_invoice_count, to: '/owner/receivables', description: 'Công nợ mọi tháng, gồm cả đợt thuê đã đóng' },
  { title: 'Phòng sẵn sàng nhận khách', count: data.value.rooms.available_for_check_in_count, to: '/owner/stays/new', description: 'Phòng vận hành và không có đợt thuê đang mở' },
] : [])
void load()
</script>
<template>
  <section class="management-page dashboard-page">
    <header class="management-header"><div><span class="eyebrow">Không gian chủ trọ</span><h1>Tổng quan</h1><p>Theo dõi nơi ở, các việc cần xử lý và dòng tiền.</p></div><form class="button-row" @submit.prevent="load"><label class="select-field">Tháng theo dõi<input v-model="month" type="month" class="ui-input" required /></label><UiButton type="submit" variant="secondary" :loading="loading">Cập nhật</UiButton></form></header>
    <UiPageState v-if="loading" kind="loading" title="Đang tổng hợp thông tin…" />
    <UiPageState v-else-if="error" kind="error" :description="error"><UiButton @click="load">Thử lại</UiButton></UiPageState>
    <template v-else-if="data">
      <section aria-labelledby="current-title"><div class="dashboard-section-heading"><h2 id="current-title">Tình hình hiện tại</h2><p class="help-text">Cập nhật {{ formatDateTime(data.as_of) }}</p></div><div class="dashboard-stats">
        <RouterLink class="stat-card" to="/owner/rooms"><span>Tổng phòng</span><strong>{{ data.rooms.total_count }}</strong><small>{{ data.rooms.occupied_count }} có người · {{ data.rooms.vacant_count }} trống · {{ data.rooms.maintenance_count }} bảo trì</small></RouterLink>
        <RouterLink class="stat-card" to="/owner/tenants"><span>Người đang cư trú</span><strong>{{ data.tenants.residing_count }}</strong><small>{{ data.tenants.total_count }} hồ sơ người thuê</small></RouterLink>
        <RouterLink class="stat-card" to="/owner/stays"><span>Đợt thuê đang mở</span><strong>{{ data.rooms.active_occupancy_count }}</strong><small>Chọn đợt thuê để quản lý thành viên và dịch vụ</small></RouterLink>
      </div></section>
      <DashboardCharts :data="data" />
      <section class="dashboard-section" aria-labelledby="tasks-title"><h2 id="tasks-title">Cần theo dõi</h2><div class="dashboard-stats"><RouterLink v-for="task in tasks" :key="task.to" class="stat-card task-card" :to="task.to"><span>{{ task.title }}</span><strong>{{ task.count }}</strong><small>{{ task.description }}</small><span class="task-link">Mở danh sách →</span></RouterLink></div></section>
      <section class="dashboard-section" aria-labelledby="cash-title"><h2 id="cash-title">Thực thu / chi tháng {{ data.month }}</h2><p class="help-text">Theo ngày tiền thực sự được ghi nhận trong tháng. Chênh lệch thu chi không phải lợi nhuận kế toán.</p><div class="dashboard-stats"><RouterLink class="stat-card" :to="{ path: '/owner/cash', query: { month: data.month } }"><span>Tổng thực thu</span><strong class="stat-money">{{ formatMoney(data.cash_flow.total_income) }}</strong><small>Thu hóa đơn {{ formatMoney(data.cash_flow.invoice_payment_total) }} · Thu khác {{ formatMoney(data.cash_flow.other_income_total) }}</small></RouterLink><RouterLink class="stat-card" :to="{ path: '/owner/cash', query: { month: data.month, entry_type: 'expense' } }"><span>Tổng chi</span><strong class="stat-money">{{ formatMoney(data.cash_flow.expense_total) }}</strong><small>Các khoản chi còn hiệu lực trong sổ</small></RouterLink><RouterLink class="stat-card" :to="{ path: '/owner/cash', query: { month: data.month } }"><span>Chênh lệch thu chi</span><strong class="stat-money">{{ formatMoney(data.cash_flow.net_cash_flow) }}</strong><small>{{ data.cash_flow.entry_count }} khoản thực thu/chi</small></RouterLink></div></section>
      <section class="dashboard-section" aria-labelledby="invoices-title"><h2 id="invoices-title">Hóa đơn kỳ {{ data.month }}</h2><p class="help-text">Theo tháng tính tiền của hóa đơn. Số đã thu/còn thiếu được tính đến hiện tại.</p><div class="management-grid"><article class="record-card"><h3>{{ data.invoices.invoice_count }} hóa đơn</h3><dl class="record-facts"><div><dt>Tổng tiền hợp lệ</dt><dd>{{ formatMoney(data.invoices.total_amount) }}</dd></div><div><dt>Đã thu đến hiện tại</dt><dd>{{ formatMoney(data.invoices.paid_amount) }}</dd></div><div><dt>Còn cần thu</dt><dd>{{ formatMoney(data.invoices.outstanding_amount) }}</dd></div></dl><RouterLink class="record-link" :to="{ path: '/owner/invoices', query: { billing_month: data.month } }">Xem hóa đơn kỳ này →</RouterLink></article><article class="record-card"><h3>Công nợ mọi tháng</h3><p class="dashboard-debt">{{ formatMoney(data.receivables.outstanding_amount) }}</p><p class="help-text">{{ data.receivables.outstanding_room_count }} phòng · {{ data.receivables.outstanding_group_count }} nhóm còn nợ, kể cả sau chuyển/trả phòng.</p><RouterLink class="record-link" to="/owner/receivables">Xem chi tiết công nợ →</RouterLink></article></div></section>
      <UiNotice v-if="data.receivables.inconsistent_count" tone="warning" title="Có hóa đơn cần đối chiếu">{{ data.receivables.inconsistent_count }} hóa đơn có dữ liệu chưa khớp và đã được loại khỏi tổng tiền hợp lệ. <RouterLink class="record-link" to="/owner/invoices?payment_status=inconsistent">Mở danh sách đối chiếu</RouterLink></UiNotice>
      <section class="dashboard-section record-card"><h2>Thao tác nhanh</h2><div class="button-row"><RouterLink class="record-link" to="/owner/stays/new">Nhận phòng</RouterLink><RouterLink class="record-link" to="/owner/meters">Ghi điện nước</RouterLink><RouterLink class="record-link" to="/owner/invoices/new">Lập hóa đơn</RouterLink><RouterLink class="record-link" to="/owner/payments">Biên nhận thu tiền</RouterLink><RouterLink class="record-link" to="/owner/cash">Sổ thu chi</RouterLink><RouterLink class="record-link" to="/owner/maintenance">Xử lý sửa chữa</RouterLink></div></section>
    </template>
  </section>
</template>

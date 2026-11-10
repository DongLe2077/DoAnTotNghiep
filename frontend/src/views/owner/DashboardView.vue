<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
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
void load()
</script>
<template>
  <section class="management-page dashboard-page">
    <header class="management-header workflow-header">
      <div><h1>Tổng quan</h1></div>
      <form class="dashboard-period-filter" @submit.prevent="load">
        <label class="select-field">Tháng theo dõi<input v-model="month" type="month" class="ui-input" required /></label>
        <UiButton type="submit" variant="secondary" :loading="loading">Cập nhật</UiButton>
      </form>
    </header>
    <UiPageState v-if="loading" kind="loading" skeleton="dashboard" title="Đang tổng hợp thông tin…" />
    <UiPageState v-else-if="error" kind="error" :description="error"><UiButton @click="load">Thử lại</UiButton></UiPageState>
    <template v-else-if="data">
      <section aria-labelledby="current-title">
        <h2 id="current-title">Tình hình hiện tại</h2>
        <div class="dashboard-stats overview-stats">
          <RouterLink class="stat-card" :to="{ path: '/owner/cash', query: { month: data.month } }">
            <span>Thực thu tháng {{ data.month }}</span><strong class="stat-money">{{ formatMoney(data.cash_flow.total_income) }}</strong>
          </RouterLink>
          <RouterLink class="stat-card" to="/owner/receivables?due_status=overdue">
            <span>Công nợ quá hạn</span><strong class="stat-money">{{ formatMoney(data.receivables.overdue_amount ?? '0.00') }}</strong>
            <small>{{ data.receivables.overdue_count ?? 0 }} hóa đơn · Mọi kỳ</small>
          </RouterLink>
          <RouterLink class="stat-card" to="/owner/rooms">
            <span>Phòng trống</span><strong>{{ data.rooms.vacant_count }}</strong><small>{{ data.rooms.total_count }} phòng hiện có</small>
          </RouterLink>
          <RouterLink class="stat-card" to="/owner/maintenance">
            <span>Sửa chữa đang chờ</span><strong>{{ data.maintenance.open_count }}</strong>
            <small>{{ data.maintenance.submitted_count }} mới · {{ data.maintenance.in_progress_count }} đang xử lý</small>
          </RouterLink>
        </div>
      </section>

      <UiNotice v-if="data.receivables.inconsistent_count" tone="warning" title="Có hóa đơn cần đối chiếu">
        {{ data.receivables.inconsistent_count }} hóa đơn chưa khớp số liệu. <RouterLink class="record-link" to="/owner/invoices?payment_status=inconsistent">Mở danh sách đối chiếu</RouterLink>
      </UiNotice>

      <DashboardCharts :data="data" />

      <details class="workflow-disclosure">
        <summary>Xem số liệu chi tiết</summary>
        <div class="workflow-disclosure-body management-grid">
          <section>
            <h3>Hóa đơn kỳ {{ data.month }}</h3>
            <dl class="record-facts">
              <div><dt>Tổng tiền hợp lệ</dt><dd>{{ formatMoney(data.invoices.total_amount) }}</dd></div>
              <div><dt>Đã thu đến hiện tại</dt><dd>{{ formatMoney(data.invoices.paid_amount) }}</dd></div>
              <div><dt>Còn cần thu của kỳ này</dt><dd>{{ formatMoney(data.invoices.outstanding_amount) }}</dd></div>
            </dl>
            <RouterLink class="workflow-card-link" :to="{ path: '/owner/invoices', query: { billing_month: data.month } }">Xem hóa đơn kỳ này →</RouterLink>
          </section>
          <section>
            <h3>Thu chi tháng {{ data.month }}</h3>
            <dl class="record-facts">
              <div><dt>Thu hóa đơn</dt><dd>{{ formatMoney(data.cash_flow.invoice_payment_total) }}</dd></div>
              <div><dt>Thu khác</dt><dd>{{ formatMoney(data.cash_flow.other_income_total) }}</dd></div>
              <div><dt>Tổng chi</dt><dd>{{ formatMoney(data.cash_flow.expense_total) }}</dd></div>
              <div><dt>Chênh lệch thu chi</dt><dd>{{ formatMoney(data.cash_flow.net_cash_flow) }}</dd></div>
            </dl>
            <p class="help-text">Theo ngày thực nhận hoặc chi tiền.</p>
          </section>
          <section>
            <h3>Công nợ mọi kỳ</h3>
            <dl class="record-facts">
              <div><dt>Còn phải thu</dt><dd>{{ formatMoney(data.receivables.outstanding_amount) }}</dd></div>
              <div><dt>Hóa đơn còn nợ</dt><dd>{{ data.receivables.open_invoice_count }}</dd></div>
              <div><dt>Phòng · Nhóm còn nợ</dt><dd>{{ data.receivables.outstanding_room_count }} · {{ data.receivables.outstanding_group_count }}</dd></div>
            </dl>
            <RouterLink class="workflow-card-link" to="/owner/receivables">Xem công nợ mọi kỳ →</RouterLink>
          </section>
          <section>
            <h3>Người thuê và phòng</h3>
            <dl class="record-facts">
              <div><dt>Người đang cư trú</dt><dd>{{ data.tenants.residing_count }} / {{ data.tenants.total_count }} hồ sơ</dd></div>
              <div><dt>Đợt thuê đang mở</dt><dd>{{ data.rooms.active_occupancy_count }}</dd></div>
              <div><dt>Phòng sẵn sàng nhận khách</dt><dd>{{ data.rooms.available_for_check_in_count }}</dd></div>
            </dl>
            <p class="help-text">Cập nhật {{ formatDateTime(data.as_of) }}</p>
          </section>
        </div>
      </details>
    </template>
  </section>
</template>

<style scoped>
.overview-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin: 12px 0 16px; }
.overview-stats .stat-card { gap: 4px; padding: 14px; }
.overview-stats .stat-card .stat-money { font-size: clamp(17px, 4.7vw, 23px); }
@media (max-width: 1000px) { .overview-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>

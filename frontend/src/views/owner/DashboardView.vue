<script setup lang="ts">
import { useLiveRefresh } from '@/composables/useLiveRefresh'
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
const reportedMonth = ref(month.value)
let sequence = 0, alive = true
onBeforeUnmount(() => { alive = false; sequence++ })
async function load(requested = month.value) {
  reportedMonth.value = requested
  const current = ++sequence
  loading.value = true; error.value = ''
  try { const result = await get<{ data: Dashboard }>('/dashboard', { month: requested }); if (alive && current === sequence) data.value = result.data }
  catch (caught) { if (alive && current === sequence) { data.value = null; error.value = toApiError(caught).message } }
  finally { if (alive && current === sequence) loading.value = false }
}
void load()
useLiveRefresh('property', () => !loading.value ? load(reportedMonth.value) : false)
</script>
<template>
  <section class="management-page dashboard-page">
    <header class="management-header workflow-header">
      <div>
        <span class="eyebrow">Không gian chủ trọ</span>
        <h1>Tổng quan</h1>
        <p aria-live="polite">{{ loading && data ? `Đang tải số liệu mới. Bạn đang xem kỳ ${data.month}.` : 'Nắm tình hình phòng ở, thu chi và việc cần xử lý.' }}</p>
      </div>
      <form class="dashboard-period-filter" @submit.prevent="load()">
        <label class="select-field">Tháng theo dõi<input v-model="month" type="month" class="ui-input" required /></label>
        <UiButton type="submit" variant="secondary" :loading="loading">{{ loading ? 'Đang cập nhật…' : 'Cập nhật' }}</UiButton>
      </form>
    </header>
    <p><RouterLink class="record-link" to="/owner/work">Xem việc cần làm →</RouterLink></p>
    <UiPageState v-if="loading && !data" kind="loading" skeleton="dashboard" title="Đang tổng hợp thông tin…" />
    <UiPageState v-else-if="error" kind="error" :description="error"><UiButton @click="load()">Tải lại</UiButton></UiPageState>
    <template v-else-if="data">
      <UiNotice v-if="data.receivables.inconsistent_count" tone="warning" title="Có hóa đơn cần đối chiếu">
        {{ data.receivables.inconsistent_count }} hóa đơn chưa khớp số liệu. <RouterLink class="record-link" to="/owner/invoices?payment_status=inconsistent">Mở danh sách đối chiếu</RouterLink>
      </UiNotice>

      <DashboardCharts :data="data" />

      <details v-if="data.room_details" class="workflow-disclosure">
        <summary>Phòng và số chỗ hiện tại</summary>
        <ul>
          <li v-for="room in data.room_details" :key="room.room_id">
            <strong>{{ room.room_number }}</strong> · {{ room.resident_count }}/{{ room.capacity }} người ·
            {{ room.status === 'maintenance' ? 'Bảo trì' : room.status === 'pending_deletion' ? 'Chờ xóa' : 'Còn ' + room.available_places + ' chỗ' }}
          </li>
        </ul>
      </details>

      <details v-if="data.archived_report" class="workflow-disclosure">
        <summary>Tổng kết đã lưu lúc {{ formatDateTime(data.archived_report.captured_at) }}</summary>
        <dl class="record-facts">
          <div><dt>Người ở tại mốc báo cáo</dt><dd>{{ data.archived_report.totals.occupancy.resident_at_end ?? 'Chưa đến kỳ' }}</dd></div>
          <div><dt>Phòng trống tại mốc báo cáo</dt><dd>{{ data.archived_report.totals.occupancy.vacant_at_end ?? 'Chưa có lịch sử' }}</dd></div>
          <div><dt>Phải thu theo hóa đơn</dt><dd>{{ formatMoney(data.archived_report.totals.invoices.total_amount) }}</dd></div>
          <div><dt>Còn phải thu của kỳ</dt><dd>{{ formatMoney(data.archived_report.totals.invoices.outstanding_amount) }}</dd></div>
          <div><dt>Thực thu</dt><dd>{{ formatMoney(data.archived_report.totals.cash.total_income) }}</dd></div>
          <div><dt>Tổng chi</dt><dd>{{ formatMoney(data.archived_report.totals.cash.expense_total) }}</dd></div>
        </dl>
      </details>

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
              <div><dt>Hóa đơn quá hạn</dt><dd>{{ data.receivables.overdue_count ?? 0 }}</dd></div>
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
          <section>
            <h3>Yêu cầu sửa chữa</h3>
            <dl class="record-facts">
              <div><dt>Mới gửi</dt><dd>{{ data.maintenance.submitted_count }}</dd></div>
              <div><dt>Đang xử lý</dt><dd>{{ data.maintenance.in_progress_count }}</dd></div>
            </dl>
            <RouterLink class="workflow-card-link" to="/owner/maintenance">Xem yêu cầu sửa chữa →</RouterLink>
          </section>
        </div>
      </details>
      <details v-if="data.occupancy_period" class="workflow-disclosure">
        <summary>Cư trú tháng {{ data.month.slice(5) }}/{{ data.month.slice(0, 4) }}</summary>
        <div class="workflow-disclosure-body">
          <p>Số liệu tính đến {{ data.occupancy_period.period_closed ? 'hết tháng đã chọn' : 'thời điểm cập nhật' }}.</p>
          <dl class="record-facts">
            <div><dt>Lượt vào ở</dt><dd>{{ data.occupancy_period.arrivals }}</dd></div>
            <div><dt>Lượt rời khu</dt><dd>{{ data.occupancy_period.departures }}</dd></div>
            <div><dt>Lượt chuyển phòng</dt><dd>{{ data.occupancy_period.transfers }}</dd></div>
            <div><dt>Phòng có người ở trong kỳ</dt><dd>{{ data.occupancy_period.rooms_used }}</dd></div>
            <div><dt>Người đang ở tại mốc báo cáo</dt><dd>{{ data.occupancy_period.resident_at_end ?? 'Chưa đến kỳ' }}</dd></div>
            <div><dt>Phòng trống tại mốc báo cáo</dt><dd>{{ data.occupancy_period.vacant_at_end ?? 'Chưa có lịch sử trạng thái phòng' }}</dd></div>
          </dl>
        </div>
      </details>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import DashboardInvoiceChart from './DashboardInvoiceChart.vue'
import DashboardTrendChart from './DashboardTrendChart.vue'
import { chartMonth, chartPercent } from '@/lib/charts'
import { formatMoney } from '@/lib/format'
import type { Dashboard } from '@/types/reporting'

const props = defineProps<{ data: Dashboard }>()
const rooms = computed(() => [
  { label: 'Đang ở', count: props.data.rooms.occupied_count, color: 'var(--color-chart-main)' },
  { label: 'Phòng trống', count: props.data.rooms.vacant_count, color: 'var(--color-chart-secondary)' },
  { label: 'Bảo trì', count: props.data.rooms.maintenance_count, color: '#b5873c' },
].map(item => ({ ...item, percent: chartPercent(BigInt(item.count), BigInt(props.data.rooms.total_count)) })))
</script>

<template>
  <section class="dashboard-charts" aria-labelledby="charts-title">
    <div class="chart-grid overview-stats">
      <section class="record-card finance-panel" aria-labelledby="finance-title">
        <span class="chart-kicker">Dòng tiền · {{ chartMonth(data.month) }}</span>
        <h2 id="finance-title">Thực thu trong kỳ</h2>
        <RouterLink class="finance-amount" :to="{ path: '/owner/cash', query: { month: data.month } }">
          <span class="sr-only">Thực thu tháng {{ data.month }}</span><strong>{{ formatMoney(data.cash_flow.total_income) }}</strong>
        </RouterLink>
        <dl class="record-facts">
          <div><dt>Thực chi</dt><dd>{{ formatMoney(data.cash_flow.expense_total) }}</dd></div>
          <div><dt>Chênh lệch thu chi</dt><dd>{{ formatMoney(data.cash_flow.net_cash_flow) }}</dd></div>
        </dl>
      </section>
      <section class="record-card rooms-panel" aria-labelledby="rooms-title">
        <header>
          <div><h2 class="chart-kicker">Tình hình hiện tại</h2><h3 id="rooms-title">Phòng ở</h3></div>
          <RouterLink class="record-link" to="/owner/rooms">Quản lý phòng →</RouterLink>
        </header>
        <p class="room-count"><strong>{{ data.rooms.total_count }}</strong> phòng</p>
        <div class="dashboard-meter" aria-hidden="true"><span v-for="item in rooms" :key="item.label" :style="{ width: item.percent + '%', background: item.color }" /></div>
        <ul class="dashboard-legend">
          <li v-for="item in rooms" :key="item.label">
            <RouterLink v-if="item.label === 'Phòng trống'" to="/owner/rooms" class="legend-item">
              <i :style="{ background: item.color }" aria-hidden="true" />{{ item.label }} <strong>{{ item.count }}</strong>
            </RouterLink>
            <span v-else class="legend-item"><i :style="{ background: item.color }" aria-hidden="true" />{{ item.label }} <strong>{{ item.count }}</strong></span>
          </li>
        </ul>
      </section>
    </div>
    <div class="chart-grid chart-grid-secondary">
      <DashboardInvoiceChart :month="data.month" :summary="data.invoices" />
      <section class="record-card tracking-panel" aria-labelledby="tracking-title">
        <span class="chart-kicker">Mọi kỳ</span>
        <h2 id="tracking-title">Cần theo dõi</h2>
        <dl class="record-facts">
          <div><dt><RouterLink class="record-link" to="/owner/receivables?due_status=overdue">Công nợ quá hạn</RouterLink></dt><dd>{{ formatMoney(data.receivables.overdue_amount ?? '0.00') }}</dd></div>
          <div><dt><RouterLink class="record-link" to="/owner/maintenance">Yêu cầu sửa chữa đang mở</RouterLink></dt><dd>{{ data.maintenance.open_count }}</dd></div>
        </dl>
      </section>
    </div>
    <h2 id="charts-title" class="trend-heading">Thống kê trực quan</h2>
    <details class="workflow-disclosure">
      <summary>Xem thêm thống kê</summary>
      <div class="workflow-disclosure-body">
        <DashboardTrendChart :month="data.month" :series="data.monthly_series ?? []" />
        <p class="help-text">Phòng bảo trì được tính riêng, kể cả khi còn người cư trú.</p>
      </div>
    </details>
  </section>
</template>

<style scoped>
.dashboard-charts { margin-bottom: 12px; }
.chart-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr); gap: 14px; }
.chart-grid-secondary { margin-top: 14px; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); }
.chart-grid > .record-card { min-width: 0; padding: clamp(16px, 2.5vw, 27px); }
.chart-grid h2, .chart-grid h3 { margin: 4px 0 10px; font-size: 19px; letter-spacing: -.022em; line-height: 1.35; }
.chart-grid h2.chart-kicker { margin: 0; font-size: 11px; line-height: inherit; letter-spacing: .13em; }
.rooms-panel > header { margin-bottom: 0; align-items: flex-start; }
.rooms-panel > header a { display: inline-flex; align-items: center; min-height: 44px; }
.finance-panel { background: #edf3ff; color: var(--color-text); border-color: #cbd9f4; }
.finance-panel .chart-kicker, .finance-amount { color: var(--color-action); }
.finance-amount { display: inline-flex; align-items: center; min-height: 44px; text-decoration-thickness: 1px; }
.finance-amount:focus-visible { outline-color: var(--color-action); }
.finance-amount strong, .room-count strong { font-size: clamp(25px, 4vw, 44px); line-height: 1.25; letter-spacing: -.05em; font-variant-numeric: tabular-nums; }
.room-count { margin: 12px 0 0; }
.record-facts { gap: 0; margin: 12px 0 0; }
.record-facts > div { padding: 8px 0; border-bottom: 1px solid var(--color-border); }
.record-facts dd { font-weight: 650; }
.finance-panel .record-facts > div { border-color: #cbd9f4; }
.tracking-panel a { text-decoration-color: transparent; }
.tracking-panel a:hover, .tracking-panel a:focus-visible { text-decoration-color: currentColor; }
.trend-heading { margin: 18px 0 10px; }
@media (max-width: 700px) { .chart-grid, .chart-grid-secondary { grid-template-columns: minmax(0, 1fr); } }
</style>

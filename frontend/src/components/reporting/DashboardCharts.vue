<script setup lang="ts">
import { computed } from 'vue'
import DashboardInvoiceChart from './DashboardInvoiceChart.vue'
import DashboardTrendChart from './DashboardTrendChart.vue'
import { chartPercent } from '@/lib/charts'
import type { Dashboard } from '@/types/reporting'
const props = defineProps<{ data: Dashboard }>()
const rooms = computed(() => [
  { label: 'Có người ở', count: props.data.rooms.occupied_count, color: '#235c4b' },
  { label: 'Phòng trống', count: props.data.rooms.vacant_count, color: '#46888e' },
  { label: 'Bảo trì', count: props.data.rooms.maintenance_count, color: '#af792b' },
])
</script>
<template>
  <section class="dashboard-section dashboard-charts" aria-labelledby="charts-title"><div class="dashboard-section-heading"><h2 id="charts-title">Thống kê trực quan</h2><p class="help-text">Chạm vào biểu đồ để xem thêm</p></div>
    <div class="chart-grid"><DashboardInvoiceChart :month="data.month" :summary="data.invoices" /><DashboardTrendChart :month="data.month" :series="data.monthly_series ?? []" /></div>
    <article class="record-card room-chart" aria-labelledby="room-chart-title">
      <div class="room-chart-title"><div><h3 id="room-chart-title">Tình trạng phòng hiện tại</h3><p class="help-text">{{ data.rooms.total_count }} phòng trong nhà trọ</p></div><RouterLink class="record-link" to="/owner/rooms">Quản lý phòng →</RouterLink></div>
      <div class="room-bar" role="img" :aria-label="rooms.map(item => `${item.label}: ${item.count} phòng`).join(', ')"><span v-for="item in rooms" :key="item.label" :style="{ width: `${chartPercent(BigInt(item.count), BigInt(data.rooms.total_count))}%`, background: item.color }" /></div>
      <ul class="room-legend"><li v-for="item in rooms" :key="item.label"><i :style="{ background: item.color }" aria-hidden="true" />{{ item.label }} <strong>{{ item.count }}</strong></li></ul>
      <p v-if="!data.rooms.total_count" class="help-text">Chưa có phòng để thống kê.</p><p class="room-note">Phòng bảo trì được tính riêng, kể cả khi còn người cư trú. Trạng thái này luôn phản ánh hiện tại.</p>
    </article>
  </section>
</template>
<style scoped>
.chart-grid { display: grid; grid-template-columns: minmax(260px, .85fr) minmax(0, 1.45fr); gap: 20px; align-items: stretch; margin-top: 20px; }
.room-chart { margin-top: 20px; }
.room-chart-title { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.room-bar { display: flex; overflow: hidden; height: 14px; border-radius: 7px; background: var(--color-border); margin: 18px 0; }
.room-bar span { display: block; height: 100%; }
.room-legend { display: flex; flex-wrap: wrap; gap: 10px 28px; margin: 0; padding: 0; list-style: none; }
.room-legend li { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--color-text-secondary); }
.room-legend i { width: 10px; height: 10px; border-radius: 3px; }
.room-legend strong { margin-left: 6px; color: var(--color-text); }
.room-note { margin: 16px 0 0; font-size: 12px; line-height: 1.7; color: var(--color-text-secondary); }
@media (max-width: 1100px) { .chart-grid { grid-template-columns: minmax(240px, 1fr) minmax(0, 1.35fr); } }
@media (max-width: 800px) { .chart-grid { grid-template-columns: 1fr; } }
</style>

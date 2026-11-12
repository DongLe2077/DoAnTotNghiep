<script setup lang="ts">
import { computed } from 'vue'
import { chartMonth, chartPercent } from '@/lib/charts'
import type { InvoiceSummary } from '@/types/reporting'

const props = defineProps<{ month: string; summary: InvoiceSummary }>()
const segments = computed(() => [
  { key: 'paid', label: 'Đã thu đủ', count: props.summary.paid_count, color: 'var(--color-chart-main)' },
  { key: 'partially_paid', label: 'Thu một phần', count: props.summary.partially_paid_count, color: 'var(--color-chart-secondary)' },
  { key: 'unpaid', label: 'Chưa thu', count: props.summary.unpaid_count, color: '#b5873c' },
  { key: 'cancelled', label: 'Đã hủy', count: props.summary.cancelled_count ?? 0, color: '#88919d' },
  { key: 'inconsistent', label: 'Cần đối chiếu', count: props.summary.inconsistent_count, color: '#ac5953' },
].map(item => ({ ...item, percent: chartPercent(BigInt(item.count), BigInt(props.summary.invoice_count)) })))
</script>

<template>
  <article class="record-card invoice-chart" aria-label="Trạng thái hóa đơn">
    <span class="chart-kicker">Hóa đơn · {{ chartMonth(month) }}</span>
    <h2>Tiến độ thu tiền</h2>
    <div class="dashboard-meter" aria-hidden="true"><span v-for="segment in segments" :key="segment.key" :style="{ width: segment.percent + '%', background: segment.color }" /></div>
    <ul class="dashboard-legend">
      <li v-for="segment in segments" :key="segment.key">
        <RouterLink class="legend-item" :to="{ path: '/owner/invoices', query: { billing_month: month, payment_status: segment.key } }">
          <i :style="{ background: segment.color }" aria-hidden="true" />{{ segment.label }} <strong>{{ segment.count }}</strong>
        </RouterLink>
      </li>
    </ul>
    <p v-if="!summary.invoice_count" class="help-text">Chưa có hóa đơn kỳ này.</p>
  </article>
</template>

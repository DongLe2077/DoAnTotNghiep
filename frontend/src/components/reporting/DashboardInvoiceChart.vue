<script setup lang="ts">
import { computed } from 'vue'
import { chartMonth, chartPercent } from '@/lib/charts'
import type { InvoiceSummary } from '@/types/reporting'
const props = defineProps<{ month: string; summary: InvoiceSummary }>()
const segments = computed(() => {
  let offset = 0
  return [
    { key: 'paid', label: 'Đã thu đủ', count: props.summary.paid_count, color: '#235c4b' },
    { key: 'partially_paid', label: 'Thu một phần', count: props.summary.partially_paid_count, color: '#46888e' },
    { key: 'unpaid', label: 'Chưa thu', count: props.summary.unpaid_count, color: '#af792b' },
    { key: 'cancelled', label: 'Đã hủy', count: props.summary.cancelled_count ?? 0, color: '#8a908c' },
    { key: 'inconsistent', label: 'Cần đối chiếu', count: props.summary.inconsistent_count, color: '#a13e35' },
  ].map(item => {
    const percent = chartPercent(BigInt(item.count), BigInt(props.summary.invoice_count))
    const segment = { ...item, percent, offset }; offset += percent; return segment
  })
})
</script>
<template>
  <article class="record-card invoice-chart" aria-labelledby="invoice-chart-title">
    <header><div><span class="eyebrow">Kỳ {{ chartMonth(month) }}</span><h3 id="invoice-chart-title">Trạng thái hóa đơn</h3></div><span class="chart-tag">Theo số hóa đơn</span></header>
    <svg class="invoice-donut" viewBox="0 0 220 220" role="img" :aria-label="`Phân bố ${summary.invoice_count} hóa đơn kỳ ${chartMonth(month)}`">
      <circle cx="110" cy="110" r="80" fill="none" stroke="var(--color-border)" stroke-width="24" />
      <circle v-for="segment in segments.filter(item => item.count > 0)" :key="segment.key" cx="110" cy="110" r="80" fill="none" :stroke="segment.color" stroke-width="24" pathLength="100" :stroke-dasharray="`${segment.percent} ${100 - segment.percent}`" :stroke-dashoffset="-segment.offset" transform="rotate(-90 110 110)" />
      <text x="110" y="108" text-anchor="middle" class="donut-count">{{ summary.invoice_count }}</text><text x="110" y="134" text-anchor="middle" class="donut-caption">hóa đơn</text>
    </svg>
    <p v-if="!summary.invoice_count" class="help-text empty-chart">Chưa có hóa đơn kỳ này.</p>
    <ul class="invoice-legend"><li v-for="segment in segments" :key="segment.key"><RouterLink :to="{ path: '/owner/invoices', query: { billing_month: month, payment_status: segment.key } }"><span class="legend-dot" :style="{ background: segment.color }" aria-hidden="true" /><span>{{ segment.label }}</span><strong>{{ segment.count }}</strong></RouterLink></li></ul>
    <p class="help-text chart-footnote">Bấm trạng thái để mở danh sách. Trạng thái thanh toán được tính đến hiện tại.</p>
  </article>
</template>
<style scoped>
.invoice-chart { min-width: 0; }
.invoice-chart > header { align-items: center; gap: 8px; flex-wrap: wrap; }
.invoice-chart h3 { margin-top: 5px; }
.chart-tag { padding: 6px 9px; border-radius: 8px; font-size: 11px; color: var(--color-text-secondary); background: var(--color-bg); }
.invoice-donut { display: block; width: 208px; max-width: 100%; margin: 6px auto 12px; }
.donut-count { font: 650 36px 'Be Vietnam Pro', sans-serif; fill: var(--color-text); }
.donut-caption { font: 13px 'Be Vietnam Pro', sans-serif; fill: var(--color-text-secondary); }
.invoice-legend { list-style: none; padding: 0; margin: 0; display: grid; gap: 3px; }
.invoice-legend a { display: flex; align-items: center; gap: 10px; padding: 9px 8px; min-height: 42px; border-radius: 8px; color: var(--color-text); text-decoration: none; font-size: 13px; }
.invoice-legend a:hover, .invoice-legend a:focus-visible { background: var(--color-primary-soft); }
.invoice-legend strong { margin-left: auto; font-variant-numeric: tabular-nums; }
.legend-dot { width: 10px; height: 10px; border-radius: 50%; flex: 0 0 auto; }
.chart-footnote { margin: 14px 0 0; font-size: 12px; line-height: 1.7; }
.empty-chart { text-align: center; }
</style>

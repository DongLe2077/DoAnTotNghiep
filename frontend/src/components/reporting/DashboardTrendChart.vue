<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { chartAxis, chartMonth, chartPercent, chartScale, minorUnits } from '@/lib/charts'
import { formatMoney } from '@/lib/format'
import type { MonthlyStatistics } from '@/types/reporting'
const props = defineProps<{ series: MonthlyStatistics[]; month: string }>()
const mode = ref<'invoices' | 'cash'>('invoices'), selectedMonth = ref(props.month)
watch(() => props.month, month => { selectedMonth.value = month })
const firstKey = computed(() => mode.value === 'invoices' ? 'invoice_total' : 'cash_income')
const secondKey = computed(() => mode.value === 'invoices' ? 'invoice_paid' : 'cash_expense')
const firstLabel = computed(() => mode.value === 'invoices' ? 'Tổng hóa đơn' : 'Thực thu')
const secondLabel = computed(() => mode.value === 'invoices' ? 'Đã thu theo hóa đơn' : 'Thực chi')
const selected = computed(() => props.series.find(item => item.month === selectedMonth.value) ?? props.series.at(-1))
const scale = computed(() => chartScale(props.series.flatMap(item => [item[firstKey.value], item[secondKey.value]])))
const axis = computed(() => chartAxis(scale.value))
const hasData = computed(() => props.series.some(item => minorUnits(item[firstKey.value]) > 0n || minorUnits(item[secondKey.value]) > 0n))
const excluded = computed(() => props.series.reduce((sum, item) => sum + item.inconsistent_count, 0))
function height(value: string) { return `${chartPercent(minorUnits(value), scale.value)}%` }
function label(item: MonthlyStatistics) { return `Tháng ${chartMonth(item.month)}: ${firstLabel.value} ${formatMoney(item[firstKey.value])}, ${secondLabel.value} ${formatMoney(item[secondKey.value])}` }
</script>
<template>
  <article class="record-card trend-chart" aria-labelledby="trend-chart-title">
    <header>
      <div><span class="eyebrow">{{ series.length }} tháng đến {{ chartMonth(month) }}</span><h3 id="trend-chart-title">{{ mode === 'invoices' ? 'Hóa đơn theo tháng' : 'Thu chi theo tháng' }}</h3></div>
      <div class="chart-switch" role="group" aria-label="Nội dung biểu đồ"><button :aria-pressed="mode === 'invoices'" @click="mode = 'invoices'">Hóa đơn</button><button :aria-pressed="mode === 'cash'" @click="mode = 'cash'">Thu / chi</button></div>
    </header>
    <p class="help-text chart-context">{{ mode === 'invoices' ? 'Theo tháng tính tiền. Số đã thu được tính đến hiện tại.' : 'Theo ngày thực nhận / thực chi, gồm cả tiền thu nợ của kỳ trước.' }}</p>
    <div class="trend-legend"><span><i class="series-a" aria-hidden="true" />{{ firstLabel }}</span><span><i class="series-b" :class="{ 'is-expense': mode === 'cash' }" aria-hidden="true" />{{ secondLabel }}</span></div>
    <p class="axis-unit">Đơn vị: {{ axis.unit }}</p>
    <div v-if="series.length" class="trend-plot">
      <div class="y-axis" aria-hidden="true"><span v-for="tick in axis.ticks" :key="tick.position">{{ tick.label }}</span></div>
      <div class="plot-content">
        <div class="grid-lines" aria-hidden="true"><span v-for="tick in axis.ticks" :key="tick.position" :style="{ bottom: `${tick.position}%` }" /></div>
        <div class="month-columns" :style="{ gridTemplateColumns: `repeat(${series.length}, minmax(0, 1fr))` }">
          <button v-for="item in series" :key="item.month" class="month-column" :class="{ 'is-selected': selected?.month === item.month }" :aria-label="label(item)" :title="label(item)" :aria-pressed="selected?.month === item.month" @click="selectedMonth = item.month">
            <span class="bar-pair" aria-hidden="true"><span class="bar series-a" :style="{ height: height(item[firstKey]) }" /><span class="bar series-b" :class="{ 'is-expense': mode === 'cash' }" :style="{ height: height(item[secondKey]) }" /></span>
            <span class="month-label">{{ item.month.slice(5) }}<small>{{ item.month.slice(0, 4) }}</small></span>
          </button>
        </div>
        <p v-if="!hasData" class="plot-empty">Chưa có phát sinh trong giai đoạn này.</p>
      </div>
    </div>
    <p v-else class="help-text">Chưa có dữ liệu thống kê theo tháng.</p>
    <div v-if="selected" class="trend-detail" aria-live="polite">
      <h4>Tháng {{ chartMonth(selected.month) }}</h4>
      <dl><div><dt>{{ firstLabel }}</dt><dd>{{ formatMoney(selected[firstKey]) }}</dd></div><div><dt>{{ secondLabel }}</dt><dd>{{ formatMoney(selected[secondKey]) }}</dd></div><div><dt>{{ mode === 'invoices' ? 'Còn cần thu' : 'Chênh lệch thu chi' }}</dt><dd>{{ formatMoney(mode === 'invoices' ? selected.invoice_outstanding : selected.cash_net) }}</dd></div></dl>
      <RouterLink class="record-link" :to="mode === 'invoices' ? { path: '/owner/invoices', query: { billing_month: selected.month } } : { path: '/owner/cash', query: { month: selected.month } }">{{ mode === 'invoices' ? 'Xem hóa đơn tháng này →' : 'Xem sổ thu chi tháng này →' }}</RouterLink>
    </div>
    <p class="chart-footnote">Bấm cột tháng để xem số tiền chính xác. {{ mode === 'invoices' && excluded ? `${excluded} hóa đơn cần đối chiếu đã được loại khỏi tổng tiền biểu đồ.` : mode === 'cash' ? 'Chênh lệch thu chi không phải lợi nhuận kế toán.' : '' }}</p>
  </article>
</template>
<style scoped>
.trend-chart { min-width: 0; }
.trend-chart > header { align-items: center; flex-wrap: wrap; }
.trend-chart h3 { margin-top: 5px; }
.chart-switch { display: inline-flex; border: 1px solid var(--color-border); background: var(--color-bg); padding: 3px; border-radius: 10px; }
.chart-switch button { border: 0; border-radius: 7px; background: transparent; color: var(--color-text-secondary); font: inherit; font-size: 12px; padding: 8px 11px; min-height: 36px; cursor: pointer; white-space: nowrap; }
.chart-switch button[aria-pressed="true"] { background: var(--color-surface); box-shadow: 0 1px 4px #233b3212; color: var(--color-primary); font-weight: 600; }
.chart-context { min-height: 40px; font-size: 12px; line-height: 1.7; }
.trend-legend { display: flex; gap: 12px; flex-wrap: wrap; font-size: 12px; margin: 12px 0; }
.trend-legend > span { display: inline-flex; align-items: center; gap: 6px; }
.trend-legend i { width: 10px; height: 10px; border-radius: 3px; }
.series-a { background: #235c4b; }
.series-b { background: #46888e; }
.series-b.is-expense { background: #af792b; }
.axis-unit { font-size: 11px; color: var(--color-text-secondary); margin: 16px 0 8px; }
.trend-plot { display: grid; grid-template-columns: 42px minmax(0, 1fr); gap: 6px; }
.y-axis { height: 200px; display: flex; flex-direction: column; justify-content: space-between; font-size: 11px; text-align: right; color: var(--color-text-secondary); transform: translateY(-6px); }
.plot-content { position: relative; min-width: 0; }
.grid-lines { position: absolute; inset: 0 0 auto; height: 200px; pointer-events: none; }
.grid-lines span { position: absolute; width: 100%; border-top: 1px dashed var(--color-border); }
.month-columns { position: relative; display: grid; gap: 4px; }
.month-column { min-width: 0; display: grid; grid-template-rows: 200px 42px; border: 0; border-radius: 6px; padding: 0 3px; background: transparent; color: var(--color-text-secondary); font: inherit; cursor: pointer; }
.month-column:hover, .month-column.is-selected { background: #235c4b09; }
.month-column.is-selected .month-label { color: var(--color-primary); font-weight: 700; }
.bar-pair { display: flex; align-items: end; justify-content: center; gap: 4px; min-width: 0; }
.bar { display: block; width: 18px; max-width: 38%; border-radius: 4px 4px 0 0; transition: height var(--motion-normal, 180ms) var(--motion-ease, ease), background var(--motion-normal, 180ms) ease; }
.month-label { display: block; padding-top: 8px; font-size: 12px; line-height: 1.3; }
.month-label small { display: block; margin-top: 3px; font-size: 10px; font-weight: 400; }
.plot-empty { position: absolute; top: 65px; left: 8px; right: 8px; padding: 16px; color: var(--color-text-secondary); text-align: center; font-size: 13px; background: var(--color-surface); border-radius: 8px; pointer-events: none; }
.trend-detail { margin-top: 18px; padding: 14px 16px; border-radius: 10px; background: var(--color-primary-soft); }
.trend-detail h4 { margin: 0 0 10px; font-size: 13px; }
.trend-detail dl { margin: 0 0 12px; display: grid; gap: 8px; }
.trend-detail dl > div { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px 12px; font-size: 12px; }
.trend-detail dt { color: var(--color-text-secondary); }
.trend-detail dd { margin: 0; font-weight: 600; font-variant-numeric: tabular-nums; }
.trend-detail .record-link { font-size: 12px; }
.chart-footnote { margin: 12px 0 0; color: var(--color-text-secondary); font-size: 11px; line-height: 1.7; }
button:focus-visible { outline: 3px solid var(--color-focus); outline-offset: 2px; }
@media (max-width: 400px) { .trend-chart { padding: 18px 14px; } .trend-plot { grid-template-columns: 32px minmax(0, 1fr); gap: 3px; } .month-columns { gap: 2px; } .bar-pair { gap: 3px; } .month-column { padding: 0 2px; } }
@media (prefers-reduced-motion: reduce) { .bar { transition: none; } }
</style>

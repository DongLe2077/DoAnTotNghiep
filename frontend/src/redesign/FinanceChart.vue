<script setup lang="ts">
import { computed, ref } from 'vue'
import { formatMoney } from '@/lib/format'
import type { MonthlyStatistics } from '@/types/reporting'

const props = defineProps<{ periods: MonthlyStatistics[] }>()
const selection = ref('')
const selected = computed(() => props.periods.find(p => p.month === selection.value) ?? props.periods.at(-1))
const max = computed(() => {
  const peak = Math.max(1, ...props.periods.flatMap(p => [Number(p.cash_income), Number(p.cash_expense)]))
  const unit = 10 ** Math.floor(Math.log10(peak))
  return Math.ceil((peak / unit) / 3) * 3 * unit
})
const axis = computed(() => [max.value, max.value * 2 / 3, max.value / 3, 0])
const unit = computed(() => max.value >= 1_000_000 ? 1_000_000 : max.value >= 1_000 ? 1_000 : 1)
const axisFormat = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 })
const month = (period: MonthlyStatistics) => Number(period.month.slice(-2))
const label = (period: MonthlyStatistics) => month(period) + '/' + period.month.slice(0, 4)
const height = (value: string) => Math.max(0, Number(value)) / max.value * 100 + '%'
</script>

<template>
  <section class="rb7-panel rb7-chart" aria-labelledby="rb7-chart-title">
    <header>
      <h2 id="rb7-chart-title">Thu và chi 6 tháng</h2>
      <p class="rb7-legend"><span>Thực thu</span><span>Thực chi</span></p>
    </header>
    <div class="rb7-plot">
      <div class="rb7-axis" aria-hidden="true"><span v-for="tick in axis" :key="tick">{{ axisFormat.format(tick / unit) }}</span></div>
      <div class="rb7-chart-body">
        <div class="rb7-gridlines" aria-hidden="true"><i v-for="tick in axis" :key="tick" /></div>
        <div class="rb7-periods">
          <button v-for="p in periods" :key="p.month" type="button" :aria-label="'Tháng ' + label(p)"
            :aria-pressed="p.month === selected?.month" @click="selection = p.month">
            <span class="rb7-bars" aria-hidden="true"><i :style="{ height: height(p.cash_income) }" /><i :style="{ height: height(p.cash_expense) }" /></span>
            <span aria-hidden="true">T{{ month(p) }}</span>
          </button>
        </div>
      </div>
    </div>
    <p class="rb7-unit">Đơn vị {{ unit === 1_000_000 ? 'triệu đồng' : unit === 1_000 ? 'nghìn đồng' : 'đồng' }}</p>
    <div v-if="selected" class="rb7-month" aria-live="polite">
      <strong>Tháng {{ label(selected) }}</strong>
      <span>Thu <b>{{ formatMoney(selected.cash_income) }}</b></span>
      <span>Chi <b>{{ formatMoney(selected.cash_expense) }}</b></span>
      <span>Chênh lệch <b :class="{ 'rb7-negative': Number(selected.cash_net) < 0 }">{{ formatMoney(selected.cash_net) }}</b></span>
    </div>
    <details>
      <summary>Xem số liệu theo tháng</summary>
      <table>
        <thead><tr><th scope="col">Tháng</th><th scope="col">Thu</th><th scope="col">Chi</th></tr></thead>
        <tbody><tr v-for="p in periods" :key="p.month"><th scope="row">{{ label(p) }}</th><td>{{ formatMoney(p.cash_income) }}</td><td>{{ formatMoney(p.cash_expense) }}</td></tr></tbody>
      </table>
    </details>
  </section>
</template>
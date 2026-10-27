<script setup lang="ts">
import { formatMoney } from '@/lib/format'
import { formatUnitPrice } from '@/lib/unit-price'
import { formatDateTime } from '@/lib/notifications'
import { decimalText } from '@/lib/billing'
import type { InvoiceLine } from '@/types/billing'
defineProps<{ items: InvoiceLine[] }>()
</script>
<template><ul class="detail-items"><li v-for="(line, index) in items" :key="line.id ?? index"><h3>{{ line.description }}</h3><dl class="record-facts"><div><dt>Số lượng</dt><dd>{{ decimalText(line.quantity) }} {{ line.unit }}</dd></div><div><dt>Đơn giá tính tiền</dt><dd>{{ formatUnitPrice(line.unit_price) }}</dd></div><div v-if="line.source_unit_price && line.source_unit_price !== line.unit_price"><dt>Giá gốc</dt><dd>{{ formatUnitPrice(line.source_unit_price) }}</dd></div><div v-if="line.meter_reading"><dt>Chỉ số đầu → cuối</dt><dd>{{ decimalText(line.meter_reading.previous_reading) }} → {{ decimalText(line.meter_reading.current_reading) }}</dd></div><div v-if="line.charge_period_start"><dt>Khoảng tính phí</dt><dd>{{ formatDateTime(line.charge_period_start) }}<br />→ {{ formatDateTime(line.charge_period_end) }}</dd></div><div><dt>Thành tiền</dt><dd><strong>{{ formatMoney(line.line_total) }}</strong></dd></div></dl></li></ul></template>

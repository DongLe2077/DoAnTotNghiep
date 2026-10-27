<script setup lang="ts">
import type { Invoice } from '@/types/billing'
import { formatMoney } from '@/lib/format'
import { formatDateTime } from '@/lib/notifications'
import { dueDateText, invoiceActionLabel } from '@/lib/invoice-controls'
defineProps<{ invoice: Invoice }>()
</script>
<template><section class="record-card wide"><h2>Lịch sử điều chỉnh hóa đơn</h2><dl class="record-facts"><div><dt>Tổng các dòng gốc</dt><dd>{{ formatMoney(invoice.original_amount ?? invoice.total_amount) }}</dd></div><div><dt>Điều chỉnh tăng / giảm</dt><dd>{{ formatMoney(invoice.adjustment_amount ?? '0.00') }}</dd></div></dl><p class="help-text">Giữ nguyên dòng tính tiền gốc và phiếu thu. Hủy hóa đơn ngừng tính công nợ; khôi phục sẽ tính lại số tiền đã điều chỉnh.</p><p v-if="!invoice.changes?.length" class="help-text">Chưa có thay đổi sau phát hành.</p><ol v-else class="detail-items"><li v-for="change in invoice.changes" :key="change.id"><h3>{{ invoiceActionLabel[change.action] }}<template v-if="change.action === 'adjustment'"> · {{ formatMoney(change.amount) }}</template></h3><p>{{ change.reason }}</p><p class="help-text">{{ formatDateTime(change.created_at) }}<template v-if="change.performed_by"> · {{ change.performed_by }}</template> · Lần {{ change.revision }}</p><p v-if="change.action === 'due_date'">Hạn đóng: {{ dueDateText(change.before.due_date) }} → {{ dueDateText(change.after.due_date) }}</p><p v-else>Tổng tiền: {{ formatMoney(change.before.total_amount) }} → {{ formatMoney(change.after.total_amount) }}</p></li></ol></section></template>

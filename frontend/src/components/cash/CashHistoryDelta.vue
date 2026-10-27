<script setup lang="ts">
import { computed } from 'vue'
import { cashMoney, cashTypeLabel, type CashSnapshot } from '@/types/cash'
import { formatDateTime } from '@/lib/notifications'
const props = defineProps<{ before: CashSnapshot | null; after: CashSnapshot }>()
function fields(value: CashSnapshot | null) {
  return value ? [cashTypeLabel(value.transaction_type), cashMoney(value.amount), formatDateTime(value.occurred_at), value.description, value.deleted_at ? 'Đã xóa' : 'Đang ghi nhận'] : null
}
const labels = ['Loại khoản', 'Số tiền', 'Thời điểm phát sinh', 'Nội dung', 'Trạng thái']
const rows = computed(() => { const before = fields(props.before), after = fields(props.after)!; return labels.map((label, index) => ({ label, before: before?.[index] ?? 'Chưa có', after: after[index] })).filter(row => row.before !== row.after) })
</script>
<template><dl class="cash-changes"><div v-for="row in rows" :key="row.label"><dt>{{ row.label }}</dt><dd><span>Trước: {{ row.before }}</span><strong>Sau: {{ row.after }}</strong></dd></div></dl></template>
<style scoped>.cash-changes { display: grid; gap: 14px; margin: 14px 0; font-size: 14px; }.cash-changes > div { display: grid; gap: 6px; }.cash-changes dt { color: var(--color-text-secondary); }.cash-changes dd { margin: 0; display: grid; gap: 4px; overflow-wrap: anywhere; }.cash-changes span { color: var(--color-text-secondary); }.cash-changes strong { font-weight: 500; }</style>

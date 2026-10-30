<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import { localEventTime, useOwnerApi } from '@/composables/useOwnerApi'
import { useAuthStore } from '@/stores/auth'
import { toApiError } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { dueDateText, invoiceActionLabel, readPendingInvoiceChange } from '@/lib/invoice-controls'
import type { Invoice, InvoiceAction } from '@/types/billing'
const props = defineProps<{ invoice: Invoice; action: InvoiceAction }>()
const emit = defineEmits<{ close: []; saved: [invoice: Invoice]; refresh: [] }>()
const client = useOwnerApi(), auth = useAuthStore(), ownerId = auth.user?.id
const key = `rental:pending-invoice-change:${ownerId}:${props.invoice.id}`
const pending = ref(readPendingInvoiceChange(key)), uncertain = ref(!!pending.value), confirmed = ref(false), reviewing = ref(!!pending.value), storageError = ref('')
const amount = ref(''), dueDate = ref(props.invoice.due_date ?? localEventTime().slice(0, 10)), reason = ref('')
const action = computed(() => pending.value?.action ?? props.action)
const conflict = ref(false)
let alive = true
onBeforeUnmount(() => { alive = false })
watch(() => auth.user?.id, id => { if (id !== ownerId) emit('close') }, { flush: 'sync' })
function clearPending() { pending.value = null; uncertain.value = false; try { sessionStorage.removeItem(key) } catch { /* The server also deduplicates by request key. */ } }
async function save() {
  if (client.busy.value || (!confirmed.value && !pending.value)) return
  if (!pending.value) {
    pending.value = { action: action.value, reason: reason.value.trim(), version_token: props.invoice.version_token ?? '', idempotency_key: crypto.randomUUID(),
      ...(action.value === 'adjustment' ? { amount: amount.value } : {}), ...(action.value === 'due_date' ? { due_date: dueDate.value } : {}) }
    try { sessionStorage.setItem(key, JSON.stringify(pending.value)) } catch { storageError.value = 'Chưa lưu được yêu cầu trong trình duyệt. Giữ hộp thoại mở nếu mất kết nối.' }
  }
  const wasUncertain = uncertain.value
  try {
    const result = await client.write<{ data: Invoice; replayed: boolean }>('post', `/invoices/${props.invoice.id}/changes`, { ...pending.value })
    clearPending()
    if (alive && auth.user?.id === ownerId) { emit('saved', result.data); emit('close') }
  } catch (caught) {
    const error = toApiError(caught)
    if (error.status === 409) { clearPending(); reviewing.value = false; confirmed.value = false; conflict.value = true }
    else if (wasUncertain || error.status === 0 || error.status === 401 || error.status >= 500 || error.status === 408) uncertain.value = true
    else { clearPending(); reviewing.value = false; confirmed.value = false }
  }
}
</script>
<template><UiDialog open :title="invoiceActionLabel[action]" description="Mỗi thay đổi cần lý do và được lưu vào lịch sử hóa đơn." @update:open="value => { if (!value && !client.busy.value) emit('close') }">
  <UiNotice v-if="uncertain" tone="warning">Yêu cầu trước có thể đã được ghi nhận. Gửi lại nguyên yêu cầu để kiểm tra; hệ thống không áp dụng thêm lần nữa. Nếu đóng trang, yêu cầu đang chờ vẫn được giữ trong phiên trình duyệt này.</UiNotice>
  <UiNotice v-if="storageError" tone="warning">{{ storageError }}</UiNotice>
  <p v-if="client.error.value" role="alert" class="form-error">{{ client.error.value }}</p>
  <dl class="record-facts"><div><dt>Hóa đơn</dt><dd>{{ invoice.invoice_number }}</dd></div><div><dt>Tổng hiện tại / đã thu</dt><dd>{{ formatMoney(invoice.total_amount) }} / {{ formatMoney(invoice.paid_amount) }}</dd></div></dl>
  <template v-if="conflict"><p class="help-text">Kiểm tra lại số tiền và lịch sử trước khi lập yêu cầu mới.</p><UiButton @click="emit('refresh'); emit('close')">Tải lại hóa đơn</UiButton></template>
  <form v-else-if="!reviewing" class="form-grid" @submit.prevent="reviewing = true; confirmed = false">
    <template v-if="action === 'adjustment'"><OwnerField v-model="amount" label="Khoản điều chỉnh tăng / giảm" name="amount" type="number" step="0.01" required :errors="client.errors.value" /><p class="help-text">Ví dụ: -50000 để giảm 50.000đ; 50000 để tăng 50.000đ. Tổng mới phải từ số đã thu trở lên.</p></template>
    <OwnerField v-if="action === 'due_date'" v-model="dueDate" label="Hạn thanh toán mới" name="due_date" type="date" required :min="localEventTime().slice(0, 10)" :errors="client.errors.value" />
    <UiNotice v-if="action === 'cancel'" tone="warning">Chỉ hủy khi không còn phiếu thu có hiệu lực. Hóa đơn và nguồn điện nước vẫn được giữ để đối chiếu, không phát hành trùng cùng kỳ. Có thể khôi phục hóa đơn khi cần.</UiNotice>
    <UiNotice v-if="action === 'restore'" tone="warning">Khôi phục sẽ đưa hóa đơn trở lại công nợ theo tổng đã điều chỉnh và hạn đóng đã lưu.</UiNotice>
    <OwnerField v-model="reason" label="Lý do thay đổi" name="reason" required :max-length="1000" :errors="client.errors.value" />
    <UiButton type="submit">Xem lại thay đổi</UiButton>
  </form>
  <template v-else-if="!conflict"><div class="review-box"><h3>{{ invoiceActionLabel[action] }}</h3><p v-if="action === 'adjustment'">Khoản tăng / giảm: <strong>{{ formatMoney(pending?.amount ?? amount) }}</strong></p><p v-if="action === 'due_date'">Hạn đóng mới: <strong>{{ dueDateText(pending?.due_date ?? dueDate) }}</strong></p><p>{{ pending?.reason ?? reason }}</p></div><label v-if="!pending" class="billing-confirm"><input v-model="confirmed" type="checkbox" /> Tôi đã kiểm tra và đồng ý lưu thay đổi này.</label><div class="button-row"><UiButton v-if="!pending" variant="secondary" @click="reviewing = false">Quay lại sửa</UiButton><UiButton :loading="client.busy.value" :disabled="!pending && !confirmed" @click="save">{{ uncertain ? 'Gửi lại thay đổi đang chờ' : 'Xác nhận thay đổi' }}</UiButton></div></template>
</UiDialog></template>
<style scoped>.billing-confirm{display:flex;gap:12px;align-items:flex-start;margin:20px 0}.billing-confirm input{width:20px;height:20px;accent-color:var(--color-primary)}</style>

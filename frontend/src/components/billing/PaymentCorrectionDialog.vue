<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import { eventPayload, useOwnerApi } from '@/composables/useOwnerApi'
import { useAuthStore } from '@/stores/auth'
import { toApiError } from '@/lib/api'
import { inputTime } from '@/lib/billing'
import { formatMoney } from '@/lib/format'
import { formatDateTime } from '@/lib/notifications'
import { paymentCorrectionKey, readPendingPaymentCorrection } from '@/lib/payment-corrections'
import type { Invoice, Payment, PaymentCorrectionPayload } from '@/types/billing'

const props = defineProps<{ payment: Payment }>()
const emit = defineEmits<{ close: []; saved: [invoice: Invoice, replayed: boolean]; refresh: [] }>()
const client = useOwnerApi(), auth = useAuthStore(), ownerId = auth.user?.id
const key = paymentCorrectionKey(ownerId, props.payment.id)
const pending = ref(readPendingPaymentCorrection(key)), uncertain = ref(!!pending.value), reviewing = ref(!!pending.value), confirmed = ref(false), conflict = ref(false), storageError = ref('')
const form = ref({ action: 'replace' as 'replace' | 'void', reason: '', amount: props.payment.amount, paid_at: inputTime(props.payment.paid_at), payment_method: props.payment.payment_method })
const request = computed<PaymentCorrectionPayload>(() => pending.value ?? { action: form.value.action, reason: form.value.reason.trim(), version_token: props.payment.version_token ?? '', idempotency_key: '', ...(form.value.action === 'replace' ? { replacement: { amount: form.value.amount, paid_at: eventPayload(form.value.paid_at), payment_method: form.value.payment_method } } : {}) })
let alive = true
onBeforeUnmount(() => { alive = false })
watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', scope => { if (scope !== `${ownerId}:owner`) { pending.value = null; form.value.reason = ''; emit('close') } }, { flush: 'sync' })
function clearPending() { pending.value = null; uncertain.value = false; try { sessionStorage.removeItem(key) } catch { /* The current in-memory request is still cleared. */ } }
async function save() {
  if (!reviewing.value || client.busy.value || (!confirmed.value && !pending.value)) return
  if (!pending.value) {
    pending.value = { ...request.value, idempotency_key: crypto.randomUUID() }
    try { sessionStorage.setItem(key, JSON.stringify(pending.value)) } catch { storageError.value = 'Chưa lưu được yêu cầu trong trình duyệt. Giữ hộp thoại mở nếu mất kết nối để gửi lại nguyên yêu cầu.' }
  }
  const wasUncertain = uncertain.value
  try {
    const result = await client.write<{ data: Payment; invoice: Invoice; replacement: Payment | null; replayed: boolean }>('post', `/payments/${props.payment.id}/corrections`, { ...pending.value })
    clearPending()
    if (alive && auth.user?.id === ownerId) { emit('saved', result.invoice, result.replayed); emit('close') }
  } catch (caught) {
    if (!alive || auth.user?.id !== ownerId) return
    const error = toApiError(caught)
    if (error.status === 409) { clearPending(); conflict.value = true; reviewing.value = false; confirmed.value = false }
    else if (wasUncertain || error.status === 0 || error.status === 401 || error.status >= 500 || error.status === 408) uncertain.value = true
    else { clearPending(); reviewing.value = false; confirmed.value = false }
  }
}
</script>

<template>
  <UiDialog open title="Sửa sai phiếu thu" description="Giữ phiếu gốc và lưu lý do xử lý. Thao tác này điều chỉnh ghi nhận trong sổ, không thực hiện hoàn tiền." @update:open="value => { if (!value && !client.busy.value) emit('close') }">
    <dl class="record-facts"><div><dt>Phiếu gốc</dt><dd>{{ payment.receipt_number }} · {{ formatMoney(payment.amount) }}</dd></div><div><dt>Hóa đơn</dt><dd>{{ payment.invoice_number }}</dd></div></dl>
    <UiNotice v-if="uncertain" tone="warning" title="Yêu cầu sửa sai đang chờ xác nhận">Yêu cầu trước có thể đã thành công. Giữ nguyên thông tin và gửi lại để đối chiếu, hệ thống không xử lý thêm lần nữa. Yêu cầu được giữ trong phiên trình duyệt khi bạn tải lại trang.</UiNotice>
    <UiNotice v-if="storageError" tone="warning">{{ storageError }}</UiNotice>
    <p v-if="client.error.value" class="form-error" role="alert">{{ client.error.value }}</p>
    <template v-if="conflict"><p class="help-text">Phiếu hoặc số dư đã thay đổi. Tải lại phiếu, kiểm tra lịch sử trước khi tạo yêu cầu mới.</p><UiButton @click="emit('refresh'); emit('close')">Tải lại phiếu để đối chiếu</UiButton></template>
    <form v-else-if="!reviewing" class="form-grid" @submit.prevent="reviewing = true; confirmed = false">
      <label class="select-field">Cách sửa sai<select v-model="form.action" class="ui-select"><option value="replace">Thay bằng phiếu ghi nhận đúng</option><option value="void">Chỉ hủy ghi nhận nhầm</option></select></label>
      <UiNotice v-if="form.action === 'void'" tone="warning">Số tiền phiếu gốc sẽ được loại khỏi tổng đã thu và công nợ sẽ tính lại. Chọn khi khoản này thực sự được ghi nhận nhầm.</UiNotice>
      <template v-else><OwnerField v-model="form.amount" label="Số tiền thực nhận đúng" name="replacement.amount" type="number" min="0.01" step="0.01" required :errors="client.errors.value" /><OwnerField v-model="form.paid_at" label="Thời điểm thực nhận đúng (giờ Việt Nam)" name="replacement.paid_at" type="datetime-local" step="1" required :errors="client.errors.value" /><label class="select-field">Hình thức thực nhận đúng<select v-model="form.payment_method" class="ui-select"><option value="cash">Tiền mặt</option><option value="bank_transfer">Chuyển khoản</option></select></label></template>
      <OwnerField v-model="form.reason" label="Lý do sửa sai" name="reason" required :max-length="2000" :errors="client.errors.value" />
      <UiButton type="submit">Xem lại sửa sai</UiButton>
    </form>
    <template v-else><div class="review-box"><h3>{{ request.action === 'replace' ? 'Hủy phiếu gốc và tạo phiếu thay thế' : 'Hủy ghi nhận nhầm' }}</h3><p>Phiếu gốc {{ payment.receipt_number }} được giữ trong lịch sử và không còn tính vào số đã thu.</p><template v-if="request.replacement"><p>Phiếu đúng: <strong>{{ formatMoney(request.replacement.amount) }}</strong> · {{ request.replacement.payment_method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' }}</p><p>{{ formatDateTime(request.replacement.paid_at) }}</p><p>Hai bước hủy ghi nhận cũ và tạo phiếu đúng được thực hiện cùng nhau.</p></template><p class="correction-reason">Lý do: {{ request.reason }}</p></div><label v-if="!pending" class="billing-confirm"><input v-model="confirmed" type="checkbox" /> Tôi đã đối chiếu thực nhận và kiểm tra nội dung sửa sai.</label><div class="button-row"><UiButton v-if="!pending" variant="secondary" @click="reviewing = false; client.clearError()">Quay lại sửa</UiButton><UiButton :loading="client.busy.value" :disabled="!pending && !confirmed" @click="save">{{ uncertain ? 'Gửi lại yêu cầu sửa sai đang chờ' : 'Xác nhận sửa sai phiếu thu' }}</UiButton></div></template>
    <template #actions><UiButton variant="secondary" :disabled="client.busy.value" @click="emit('close')">Đóng</UiButton></template>
  </UiDialog>
</template>

<style scoped>
.billing-confirm { display: flex; gap: 12px; align-items: flex-start; margin: 20px 0; }
.billing-confirm input { width: 20px; height: 20px; accent-color: var(--color-primary); }
.correction-reason { white-space: pre-wrap; overflow-wrap: anywhere; }
</style>

<script setup lang="ts">
import PdfDownloadButton from '@/components/billing/PdfDownloadButton.vue'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import PaymentCorrectionDialog from '@/components/billing/PaymentCorrectionDialog.vue'
import PaymentReceiptSummary from '@/components/billing/PaymentReceiptSummary.vue'
import { useOwnerApi } from '@/composables/useOwnerApi'
import { useAuthStore } from '@/stores/auth'
import { toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import { paymentCorrectionKey, readPendingPaymentCorrection } from '@/lib/payment-corrections'
import type { Invoice, Payment } from '@/types/billing'

const props = defineProps<{ paymentId: number | null }>(), emit = defineEmits<{ close: []; changed: [invoice: Invoice] }>(), client = useOwnerApi(), auth = useAuthStore()
const payment = ref<Payment | null>(null), loading = ref(false), error = ref(''), correcting = ref(false)
const pending = computed(() => !correcting.value && payment.value ? readPendingPaymentCorrection(paymentCorrectionKey(auth.user?.id, payment.value.id)) : null)
let sequence = 0, alive = true
async function load() {
  const id = props.paymentId, current = ++sequence
  payment.value = null; error.value = ''
  if (!id) return
  loading.value = true
  try { const result = await client.get<{ data: Payment }>(`/payments/${id}`); if (alive && current === sequence) payment.value = result.data }
  catch (caught) { if (alive && current === sequence) error.value = toApiError(caught).message }
  finally { if (alive && current === sequence) loading.value = false }
}
function corrected(invoice: Invoice) { correcting.value = false; void load(); emit('changed', invoice) }
watch(() => props.paymentId, () => { correcting.value = false; void load() }, { immediate: true })
watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', () => { sequence++; payment.value = null; correcting.value = false; emit('close') }, { flush: 'sync' })
onBeforeUnmount(() => { alive = false; sequence++ })
</script>

<template>
  <UiDialog v-if="!correcting" :open="!!paymentId" title="Chi tiết lần thu" @update:open="value => { if (!value) emit('close') }">
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="error" kind="error" :description="error"><UiButton @click="load">Thử lại</UiButton></UiPageState>
    <template v-else-if="payment">
      <PaymentReceiptSummary :payment="payment" show-actor /><PdfDownloadButton :path="`/owner/payments/${payment.id}/pdf`" :filename="`phieu-thu-${payment.id}.pdf`" label="Tải PDF phiếu thu" />
      <dl class="record-facts"><div><dt>Hóa đơn</dt><dd>{{ payment.invoice_number }}</dd></div><div><dt>Phòng / nhóm</dt><dd>{{ payment.room.room_number }} / {{ payment.group.group_code }}</dd></div><div><dt>Tháng hóa đơn</dt><dd>{{ payment.billing_month }}</dd></div><div><dt>Ghi nhận lúc</dt><dd>{{ formatDateTime(payment.recorded_at) }}</dd></div></dl>
      <p class="help-text">Mã lần thu dùng để tra cứu trong hệ thống.</p>
      <div class="button-row"><UiButton v-if="pending || payment.can_correct" variant="secondary" @click="correcting = true">{{ pending ? 'Kiểm tra sửa sai đang chờ' : 'Sửa sai phiếu thu' }}</UiButton><UiButton variant="ghost" @click="load">Tải lại phiếu thu</UiButton></div>
      <RouterLink class="record-link" :to="`/owner/invoices/${payment.invoice_id}`" @click="emit('close')">Mở hóa đơn liên quan</RouterLink>
    </template>
  </UiDialog>
  <PaymentCorrectionDialog v-if="correcting && payment" :payment="payment" @close="correcting = false" @refresh="load" @saved="corrected" />
</template>

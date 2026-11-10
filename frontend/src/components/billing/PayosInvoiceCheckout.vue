<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import { useTenantApi } from '@/composables/useTenantApi'
import type { Invoice } from '@/types/billing'

interface CheckoutState {
  status: 'idle' | 'pending' | 'paid' | 'review' | 'expired' | 'stale'
  checkout_url: string | null
  can_start: boolean
  payment_id: number | null
}

const props = defineProps<{ invoice: Invoice; returning: boolean }>()
const emit = defineEmits<{ confirmed: [] }>()
const client = useTenantApi()
const state = ref<CheckoutState | null>(null)
const unavailable = ref(false)
const endpoint = `/invoices/${props.invoice.id}/payos`
let timer: number | undefined
let disposed = false
let checking = false

function accept(next: CheckoutState) {
  state.value = next
  if (next.payment_id && !props.invoice.payments?.some(payment => payment.id === next.payment_id)) {
    emit('confirmed')
  }
}

function scheduleCheck() {
  window.clearTimeout(timer)
  if (!disposed && document.visibilityState === 'visible' && (state.value?.status === 'pending' || (!state.value && props.returning))) {
    timer = window.setTimeout(() => void check(), 4000)
  }
}

async function check() {
  if (checking || client.busy.value || disposed) return
  checking = true
  try {
    const { data } = await client.get<{ data: CheckoutState }>(endpoint)
    if (!disposed) { unavailable.value = false; accept(data) }
  } catch {
    if (!disposed) unavailable.value = true
  } finally {
    checking = false
    scheduleCheck()
  }
}

async function start() {
  try {
    const { data } = await client.post<{ data: CheckoutState }>(endpoint, {})
    if (disposed) return
    accept(data)
    if (data.checkout_url?.startsWith('https://pay.payos.vn/web/')) window.location.assign(data.checkout_url)
  } catch {
    // Keep the current state visible. The user can retry the same checkout.
  } finally {
    scheduleCheck()
  }
}

function visibilityChanged() {
  if (document.visibilityState === 'visible') void check()
  else window.clearTimeout(timer)
}

onMounted(() => {
  document.addEventListener('visibilitychange', visibilityChanged)
  void check()
})
onBeforeUnmount(() => {
  disposed = true
  window.clearTimeout(timer)
  document.removeEventListener('visibilitychange', visibilityChanged)
})
</script>

<template>
  <div class="payos-checkout">
    <UiNotice v-if="state?.status === 'review'" tone="warning" title="Cần đối chiếu chuyển khoản">
      Chủ trọ cần kiểm tra giao dịch này. Nếu đã chuyển tiền, hãy liên hệ chủ trọ trước khi thanh toán lại.
    </UiNotice>
    <UiNotice v-else-if="state?.status === 'stale'" tone="warning" title="Hóa đơn đã thay đổi">
      Link cũ sẽ hết hạn. Nếu bạn đã dùng link đó để chuyển tiền, hãy liên hệ chủ trọ để đối chiếu.
    </UiNotice>
    <UiNotice v-else-if="state?.status === 'expired'" tone="info" title="Link thanh toán đã hết hạn">
      Nếu bạn chưa chuyển tiền, hãy tạo link mới. Nếu đã chuyển, hãy liên hệ chủ trọ để đối chiếu.
    </UiNotice>
    <UiNotice v-else-if="state?.status === 'pending' || (returning && !state)" tone="info" title="Đang kiểm tra thanh toán">
      Sau khi ngân hàng xác nhận, hóa đơn sẽ tự cập nhật tại đây.
    </UiNotice>
    <p v-if="unavailable" class="help-text" role="status">Chưa kiểm tra được trạng thái payOS. <UiButton variant="ghost" @click="check">Kiểm tra lại</UiButton></p>
    <p v-if="client.error.value" class="form-error" role="alert">{{ client.error.value }}</p>
    <UiButton v-if="state?.can_start" :loading="client.busy.value" @click="start">{{ state.checkout_url ? 'Mở lại trang thanh toán' : 'Thanh toán qua ngân hàng' }}</UiButton>
    <p v-if="state?.can_start && !state.checkout_url" class="help-text">Quét VietQR trên trang payOS để trả số tiền còn thiếu.</p>
  </div>
</template>

<style scoped>
.payos-checkout { margin-bottom: 16px; }
</style>

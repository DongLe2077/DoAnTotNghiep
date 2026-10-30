<script setup lang="ts">
import PdfDownloadButton from '@/components/billing/PdfDownloadButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import { formatMoney } from '@/lib/format'
import { formatDateTime } from '@/lib/notifications'
import type { InvoicePayment } from '@/types/billing'
defineProps<{ payment: InvoicePayment; showActor?: boolean; pdfPath?: string }>()
</script>

<template>
  <div class="receipt-summary">
    <div class="receipt-heading"><h3>{{ payment.receipt_number ?? `PT-${payment.id}` }} · {{ formatMoney(payment.amount) }}</h3><UiBadge :tone="payment.status === 'voided' ? 'neutral' : 'success'">{{ payment.status === 'voided' ? 'Đã hủy ghi nhận' : 'Có hiệu lực' }}</UiBadge></div>
    <p class="help-text">{{ formatDateTime(payment.paid_at) }} · {{ payment.payment_method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' }}</p>
    <p v-if="payment.replaces_payment_id" class="help-text">Phiếu thay thế cho PT-{{ payment.replaces_payment_id }}.</p>
    <div v-if="payment.correction" class="receipt-correction">
      <p>Đã sửa sai lúc {{ formatDateTime(payment.correction.created_at) }}<template v-if="showActor && payment.correction.performed_by"> · {{ payment.correction.performed_by.username }}</template>.</p>
      <p class="receipt-reason">Lý do: {{ payment.correction.reason }}</p>
      <p v-if="payment.correction.replacement_payment_id">Phiếu thay thế: PT-{{ payment.correction.replacement_payment_id }}.</p>
      <p class="help-text">Phiếu gốc được giữ để đối chiếu và không còn cộng vào số đã thu.</p>
    </div>
    <PdfDownloadButton v-if="pdfPath" :path="pdfPath" :filename="`phieu-thu-${payment.id}.pdf`" :label="`Tải PDF phiếu PT-${payment.id}`" />
  </div>
</template>

<style scoped>
.receipt-heading { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 10px; justify-content: space-between; }
.receipt-heading h3 { margin: 0; }
.receipt-correction { margin-top: 12px; border-left: 3px solid var(--color-border); padding-left: 14px; font-size: 14px; }
.receipt-reason { white-space: pre-wrap; overflow-wrap: anywhere; }
</style>

<script setup lang="ts">
import PdfDownloadButton from '@/components/billing/PdfDownloadButton.vue'
import PayosInvoiceCheckout from '@/components/billing/PayosInvoiceCheckout.vue'
import { computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import InvoiceLines from '@/components/billing/InvoiceLines.vue'
import InvoiceHistory from '@/components/billing/InvoiceHistory.vue'
import PaymentReceiptSummary from '@/components/billing/PaymentReceiptSummary.vue'
import { dueDateText, dueStatusLabel } from '@/lib/invoice-controls'
import { useTenantResource } from '@/composables/useTenantApi'
import { formatMoney } from '@/lib/format'
import { formatDateTime } from '@/lib/notifications'
import { tenantHasBalance, tenantPaymentLabel, tenantPaymentTone } from '@/types/tenant'
import { useNotificationsStore } from '@/stores/notifications'
import type { Invoice } from '@/types/billing'
const route = useRoute()
const notifications = useNotificationsStore()
const { data: invoice, loading, error, status, load } = useTenantResource<Invoice>(() => `/invoices/${route.params.id}`)
const hasBalance = computed(() => invoice.value ? tenantHasBalance(invoice.value.payment_status) : false)
const paidReceipts = computed(() => invoice.value?.payment_status === 'paid'
  ? invoice.value.payments?.filter(payment => payment.status !== 'voided') ?? [] : [])
const confirmedPayment = computed(() => paidReceipts.value.filter(payment => payment.source === 'payos').at(-1))
const mainReceipt = computed(() => paidReceipts.value.at(-1))
watch(() => route.params.id, () => void load(), { immediate: true })
watch(confirmedPayment, payment => { if (payment) void notifications.refreshCount(true) })
</script>
<template>
  <section class="management-page tenant-page">
    <RouterLink class="back-link" to="/tenant/invoices">← Hóa đơn của tôi</RouterLink>
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="error" :kind="status === 403 || status === 404 ? 'not-found' : 'error'" :title="status === 403 || status === 404 ? 'Hóa đơn không còn khả dụng' : 'Chưa tải được hóa đơn'" :description="status === 403 || status === 404 ? 'Hóa đơn không tồn tại hoặc chủ trọ đã thu hồi quyền xem của bạn.' : error"><UiButton @click="load">Thử lại</UiButton></UiPageState>
    <template v-else-if="invoice">
      <header class="management-header workflow-header">
        <div><h1>Hóa đơn phòng {{ invoice.room.room_number }}</h1><p>Tháng {{ invoice.billing_month.slice(5) }}/{{ invoice.billing_month.slice(0, 4) }} · {{ invoice.invoice_number }}</p></div>
      </header>

      <div class="detail-sections workflow-sections">
        <article class="record-card workflow-summary">
          <header>
            <div v-if="hasBalance">
              <h2 class="workflow-caption">Còn cần thanh toán</h2>
              <strong class="workflow-amount">{{ formatMoney(invoice.outstanding_amount) }}</strong>
            </div>
            <h2 v-else class="workflow-caption">Tình trạng thanh toán</h2>
            <UiBadge :tone="tenantPaymentTone(invoice.payment_status)">{{ tenantPaymentLabel(invoice.payment_status) }}</UiBadge>
          </header>
          <p v-if="hasBalance" class="workflow-deadline">
            <strong>{{ invoice.due_date ? `Hạn thanh toán ${dueDateText(invoice.due_date)}` : 'Chưa đặt hạn thanh toán' }}</strong>
            <span v-if="invoice.due_status === 'overdue'">{{ dueStatusLabel(invoice) }}</span>
          </p>
          <p v-else-if="invoice.payment_status === 'cancelled'">Chủ trọ đã hủy hóa đơn này. Bạn không cần thanh toán.</p>
          <p v-else-if="invoice.payment_status === 'inconsistent'">Hãy liên hệ chủ trọ để xác nhận số tiền trước khi thanh toán.</p>
          <p v-else><strong class="workflow-amount">{{ formatMoney(invoice.paid_amount) }}</strong></p>
          <p v-if="confirmedPayment" class="payment-confirmation" role="status"><strong>Thanh toán thành công</strong> · Đã nhận {{ formatMoney(confirmedPayment.amount) }} qua payOS.</p>
          <div v-if="(invoice.payos_configured && hasBalance) || mainReceipt" class="workflow-actions">
            <PayosInvoiceCheckout v-if="invoice.payos_configured && hasBalance" :key="invoice.id"
              :invoice="invoice" :returning="route.query.payos === 'return'" @confirmed="load" />
            <PdfDownloadButton v-if="mainReceipt" :path="`/tenant/invoices/${invoice.id}/payments/${mainReceipt.id}/pdf`" :filename="`phieu-thu-${mainReceipt.id}.pdf`" label="Tải phiếu thu" primary />
          </div>
          <details v-if="hasBalance" class="workflow-disclosure workflow-actions" :open="!invoice.payos_configured">
            <summary>Trả trực tiếp cho chủ trọ</summary>
            <div class="workflow-disclosure-body">
              <p class="help-text">Trả tiền mặt hoặc chuyển khoản theo thông tin trong PDF hóa đơn.</p>
              <p class="help-text"><strong>Khi chuyển khoản,</strong> ghi mã <strong>{{ invoice.invoice_number }}</strong> trong nội dung.</p>
            </div>
          </details>
        </article>

        <article class="record-card">
          <h2>Lịch sử thanh toán</h2>
          <p v-if="!invoice.payments?.length" class="help-text">Chưa có khoản thanh toán được ghi nhận.</p>
          <ul v-else class="detail-items">
            <li v-for="payment in invoice.payments" :key="payment.id">
              <PaymentReceiptSummary :payment="payment" :pdf-path="payment.id === mainReceipt?.id ? undefined : `/tenant/invoices/${invoice.id}/payments/${payment.id}/pdf`" />
            </li>
          </ul>
          <div class="button-row">
            <UiButton variant="ghost" :loading="loading" @click="load">Cập nhật thanh toán</UiButton>
          </div>
        </article>

        <details class="workflow-disclosure wide">
          <summary>Chi tiết các khoản và PDF hóa đơn</summary>
          <div class="workflow-disclosure-body">
            <dl class="record-facts">
              <div><dt>Tổng hóa đơn</dt><dd>{{ formatMoney(invoice.total_amount) }}</dd></div>
              <div><dt>Đã thanh toán</dt><dd>{{ formatMoney(invoice.paid_amount) }}</dd></div>
            </dl>
            <InvoiceLines :items="invoice.items ?? []" />
            <p class="help-text">Nhóm {{ invoice.group.group_code }} · Phát hành {{ formatDateTime(invoice.issued_at) }}</p>
            <PdfDownloadButton :path="`/tenant/invoices/${invoice.id}/pdf`" :filename="`hoa-don-${invoice.id}.pdf`" label="Tải PDF hóa đơn" />
          </div>
        </details>
        <details class="workflow-disclosure wide">
          <summary>Lịch sử điều chỉnh</summary>
          <div class="workflow-disclosure-body"><InvoiceHistory :invoice="invoice" /></div>
        </details>
      </div>
    </template>
  </section>
</template>

<style scoped>
.payment-confirmation { color: var(--color-primary); font-size: 14px; }
</style>

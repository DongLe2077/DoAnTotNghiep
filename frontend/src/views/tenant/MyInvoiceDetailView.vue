<script setup lang="ts">
import PdfDownloadButton from '@/components/billing/PdfDownloadButton.vue'
import PayosInvoiceCheckout from '@/components/billing/PayosInvoiceCheckout.vue'
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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
const route = useRoute(), router = useRouter()
const listPath = window.history.state?.tenantInvoiceList
const previousList = typeof listPath === 'string' && window.history.state?.back === listPath && router.resolve(listPath).name === 'tenant-invoices'
  ? listPath : null

function returnToList(event: MouseEvent) {
  if (!previousList || event.button !== 0 || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return
  event.preventDefault()
  router.back()
}
const notifications = useNotificationsStore()
const { data: invoice, loading, error, status, load } = useTenantResource<Invoice>(() => `/invoices/${route.params.id}`)
const hasBalance = computed(() => invoice.value ? tenantHasBalance(invoice.value.payment_status) : false)
const paidReceipts = computed(() => invoice.value?.payment_status === 'paid'
  ? invoice.value.payments?.filter(payment => payment.status !== 'voided') ?? [] : [])
const confirmedPayment = computed(() => paidReceipts.value.filter(payment => payment.source === 'payos').at(-1))
const mainReceipt = computed(() => paidReceipts.value.at(-1))
const invoiceDocuments = ref<HTMLDetailsElement>()

function showInvoiceDocuments() {
  const panel = invoiceDocuments.value
  if (!panel) return
  panel.open = true
  panel.querySelector('summary')?.focus({ preventScroll: true })
  panel.scrollIntoView({ block: 'start' })
}
watch(() => route.params.id, () => void load(), { immediate: true })
watch(confirmedPayment, payment => { if (payment) void notifications.refreshCount(true) })
</script>
<template>
  <section class="management-page tenant-page tenant-workspace tenant-invoice">
    <RouterLink class="back-link" :to="previousList ?? '/tenant/invoices'" @click.capture="returnToList">← Hóa đơn của tôi</RouterLink>
    <UiPageState v-if="loading" kind="loading" skeleton="invoice-detail" title="Đang tải chi tiết hóa đơn…" />
    <UiPageState v-else-if="error" :kind="status === 403 || status === 404 ? 'not-found' : 'error'" :title="status === 403 || status === 404 ? 'Hóa đơn không còn khả dụng' : 'Chưa tải được hóa đơn'" :description="status === 403 || status === 404 ? 'Hóa đơn không tồn tại hoặc chủ trọ đã thu hồi quyền xem của bạn.' : error"><UiButton @click="load">Thử lại</UiButton></UiPageState>
    <template v-else-if="invoice">
      <header class="management-header workflow-header">
        <div>
          <h1>Hóa đơn phòng {{ invoice.room.room_number }}</h1>
          <p>Tháng {{ invoice.billing_month.slice(5) }}/{{ invoice.billing_month.slice(0, 4) }} <span aria-hidden="true">·</span> {{ invoice.invoice_number }}</p>
        </div>
      </header>

      <div class="detail-sections workflow-sections">
        <article class="invoice-summary" aria-label="Tóm tắt thanh toán">
          <div class="invoice-balance tenant-highlight">
            <UiBadge :tone="tenantPaymentTone(invoice.payment_status)">{{ tenantPaymentLabel(invoice.payment_status) }}</UiBadge>
            <h2 class="workflow-caption">{{ hasBalance ? 'Còn cần thanh toán' : 'Tình trạng thanh toán' }}</h2>
            <strong v-if="hasBalance" class="workflow-amount">{{ formatMoney(invoice.outstanding_amount) }}</strong>
            <p v-if="hasBalance" class="workflow-deadline">
              <strong>{{ invoice.due_date ? `Hạn thanh toán ${dueDateText(invoice.due_date)}` : 'Chưa đặt hạn thanh toán' }}</strong>
              <span v-if="invoice.due_status === 'overdue'">{{ dueStatusLabel(invoice) }}</span>
            </p>
            <p v-else-if="invoice.payment_status === 'cancelled'">Chủ trọ đã hủy hóa đơn này. Bạn không cần thanh toán.</p>
            <p v-else-if="invoice.payment_status === 'inconsistent'">Hãy liên hệ chủ trọ để xác nhận số tiền trước khi thanh toán.</p>
            <strong v-else class="workflow-amount">{{ formatMoney(invoice.paid_amount) }}</strong>
            <p v-if="confirmedPayment" class="payment-confirmation" role="status"><span aria-hidden="true">✓ </span><strong>Thanh toán thành công</strong> · Đã nhận {{ formatMoney(confirmedPayment.amount) }} qua payOS.</p>
          </div>

          <section v-if="hasBalance || mainReceipt" class="invoice-payment" aria-labelledby="invoice-payment-title">
            <h2 id="invoice-payment-title">{{ hasBalance ? 'Cách thanh toán' : 'Phiếu thu' }}</h2>
            <PayosInvoiceCheckout v-if="invoice.payos_configured && hasBalance" :key="invoice.id"
              :invoice="invoice" :returning="route.query.payos === 'return'" @confirmed="load" />
            <PdfDownloadButton v-if="mainReceipt" :path="`/tenant/invoices/${invoice.id}/payments/${mainReceipt.id}/pdf`" :filename="`phieu-thu-${mainReceipt.id}.pdf`" label="Tải phiếu thu" primary />
            <details v-if="hasBalance" class="workflow-disclosure" :open="!invoice.payos_configured">
              <summary>Trả trực tiếp cho chủ trọ</summary>
              <div class="workflow-disclosure-body">
                <p class="help-text">Trả tiền mặt hoặc chuyển khoản theo thông tin trong PDF hóa đơn.</p>
                <p class="help-text"><strong>Khi chuyển khoản,</strong> ghi mã <strong>{{ invoice.invoice_number }}</strong> trong nội dung.</p>
                <UiButton variant="secondary" @click="showInvoiceDocuments">Mở phần PDF hóa đơn</UiButton>
              </div>
            </details>
          </section>
        </article>

        <article class="record-card invoice-history">
          <header>
            <h2>Lịch sử thanh toán</h2>
            <UiButton variant="ghost" :loading="loading" @click="load">Cập nhật thanh toán</UiButton>
          </header>
          <p v-if="!invoice.payments?.length" class="help-text">Chưa có khoản thanh toán được ghi nhận.</p>
          <ul v-else class="detail-items">
            <li v-for="payment in invoice.payments" :key="payment.id">
              <PaymentReceiptSummary :payment="payment" :pdf-path="payment.id === mainReceipt?.id ? undefined : `/tenant/invoices/${invoice.id}/payments/${payment.id}/pdf`" />
            </li>
          </ul>
        </article>

        <details ref="invoiceDocuments" class="workflow-disclosure wide invoice-documents">
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
.tenant-invoice { max-width: 1020px; margin-inline: auto; }
.tenant-invoice > .back-link { display: inline-flex; align-items: center; min-height: 44px; margin-bottom: 8px; }
.tenant-invoice .management-header h1 { font-size: clamp(26px, 4vw, 38px); }
.tenant-invoice > .management-header { margin-bottom: 16px; }
.tenant-invoice .management-header p { font-variant-numeric: tabular-nums; }
.tenant-invoice .workflow-sections { grid-template-columns: minmax(0, 1fr); gap: 14px; }
.invoice-summary {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  background: var(--color-surface);
}
.invoice-summary:has(.invoice-payment) { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
.invoice-balance, .invoice-payment { min-width: 0; padding: clamp(16px, 3vw, 28px); }
.invoice-balance {
  border-radius: calc(var(--radius-card) - 1px);
  overflow-wrap: anywhere;
}
.invoice-summary:has(.invoice-payment) .invoice-balance { border-start-end-radius: 0; border-end-end-radius: 0; }
.invoice-balance .workflow-caption { margin: 14px 0 3px; font-size: 14px; }
.invoice-balance .workflow-amount { display: block; font-size: clamp(27px, 5vw, 42px); line-height: 1.3; }
.invoice-balance p { margin: 10px 0 0; font-size: 14px; }
.invoice-payment h2, .invoice-history h2 { margin: 0; font-size: 17px; }
.invoice-payment > :not(h2) { margin-top: 12px; }
.invoice-payment .workflow-disclosure-body p { margin: 0 0 8px; }
.invoice-documents { scroll-margin-top: calc(var(--pwa-notice-height, 0px) + 135px); }
.invoice-history > header { flex-wrap: wrap; align-items: center; gap: 2px 12px; margin: 0 0 4px; }
.invoice-history > header .ui-button { padding-inline: 0; min-height: 44px; }
.payment-confirmation { color: var(--color-action); }

@media (max-width: 680px) {
  .invoice-summary:has(.invoice-payment) { grid-template-columns: minmax(0, 1fr); }
  .invoice-summary:has(.invoice-payment) .invoice-balance {
    border-start-end-radius: calc(var(--radius-card) - 1px);
    border-end-start-radius: 0;
  }
  .invoice-history { padding: 16px; }
  .invoice-payment :deep(.payos-checkout > .ui-button) { width: 100%; }
  .invoice-documents { scroll-margin-top: calc(var(--pwa-notice-height, 0px) + 88px); }
  .tenant-invoice > .back-link { margin-bottom: 4px; }
}
</style>

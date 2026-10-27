<script setup lang="ts">
import { useRoute } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import InvoiceLines from '@/components/billing/InvoiceLines.vue'
import InvoiceHistory from '@/components/billing/InvoiceHistory.vue'
import { dueDateText, dueStatusLabel } from '@/lib/invoice-controls'
import { useTenantResource } from '@/composables/useTenantApi'
import { formatMoney } from '@/lib/format'
import { formatDateTime } from '@/lib/notifications'
import { tenantPaymentLabel as paymentStatusLabel } from '@/types/tenant'
import type { Invoice } from '@/types/billing'
const route = useRoute()
const { data: invoice, loading, error, status, load } = useTenantResource<Invoice>(() => `/invoices/${route.params.id}`)
void load()
</script>
<template><section class="management-page tenant-page"><RouterLink class="back-link" to="/tenant/invoices">← Hóa đơn của tôi</RouterLink><UiPageState v-if="loading" kind="loading" /><UiPageState v-else-if="error" :kind="status === 403 || status === 404 ? 'not-found' : 'error'" :title="status === 403 || status === 404 ? 'Hóa đơn không còn khả dụng' : 'Chưa tải được hóa đơn'" :description="status === 403 || status === 404 ? 'Hóa đơn không tồn tại hoặc chủ trọ đã thu hồi quyền xem của bạn.' : error"><UiButton @click="load">Thử lại</UiButton></UiPageState>
  <template v-else-if="invoice"><header class="management-header"><div><span class="eyebrow">{{ invoice.invoice_number }}</span><h1>Hóa đơn phòng {{ invoice.room.room_number }}</h1><p>Tháng {{ invoice.billing_month.slice(5) }}/{{ invoice.billing_month.slice(0, 4) }} · Nhóm {{ invoice.group.group_code }}</p></div><UiButton variant="secondary" :loading="loading" @click="load">Cập nhật thanh toán</UiButton></header>
    <article class="record-card"><UiBadge :tone="invoice.payment_status === 'paid' ? 'success' : invoice.payment_status === 'inconsistent' ? 'danger' : 'warning'">{{ paymentStatusLabel(invoice.payment_status) }}</UiBadge><dl class="record-facts"><div><dt>Tổng hóa đơn</dt><dd>{{ formatMoney(invoice.total_amount) }}</dd></div><div><dt>Đã thanh toán</dt><dd>{{ formatMoney(invoice.paid_amount) }}</dd></div><div><dt>Còn cần thanh toán</dt><dd class="tenant-total">{{ formatMoney(invoice.outstanding_amount) }}</dd></div><div><dt>Hạn thanh toán</dt><dd>{{ dueDateText(invoice.due_date) }} · {{ dueStatusLabel(invoice) }}</dd></div><div><dt>Phát hành</dt><dd>{{ formatDateTime(invoice.issued_at) }}</dd></div></dl></article>
    <UiNotice v-if="invoice.payment_status === 'cancelled'" tone="warning">Chủ trọ đã hủy hóa đơn này. Bạn không cần thanh toán; các khoản gốc được giữ để đối chiếu.</UiNotice><UiNotice v-else-if="invoice.due_status === 'overdue'" tone="warning">Hóa đơn đã quá hạn {{ invoice.overdue_days }} ngày. Hãy liên hệ chủ trọ nếu cần đối chiếu hoặc gia hạn.</UiNotice><UiNotice v-if="invoice.payment_status === 'inconsistent'" tone="warning">Hóa đơn cần được đối chiếu. Hãy liên hệ chủ trọ để kiểm tra số tiền và các lần ghi nhận.</UiNotice>
    <div class="detail-sections dashboard-section"><InvoiceHistory :invoice="invoice" /><article class="record-card"><h2>Chi tiết các khoản</h2><InvoiceLines :items="invoice.items ?? []" /></article><article class="record-card"><h2>Lịch sử thanh toán</h2><p v-if="!invoice.payments?.length" class="help-text">Chưa có khoản thanh toán được chủ trọ ghi nhận.</p><ul v-else class="detail-items"><li v-for="payment in invoice.payments" :key="payment.id"><h3>{{ formatMoney(payment.amount) }}</h3><dl class="record-facts"><div><dt>Biên nhận</dt><dd>PT-{{ payment.id }}</dd></div><div><dt>Thời điểm nhận tiền</dt><dd>{{ formatDateTime(payment.paid_at) }}</dd></div><div><dt>Hình thức</dt><dd>{{ payment.payment_method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' }}</dd></div></dl></li></ul><p class="help-text">Chỉ hiển thị các khoản đã được chủ trọ ghi nhận. Nếu vừa thanh toán, bấm Cập nhật thanh toán để kiểm tra.</p></article></div><UiNotice tone="info">Nếu cần đối chiếu hoặc chưa thấy khoản đã trả, hãy liên hệ chủ trọ. Giao diện này không có thanh toán trực tuyến hoặc tự xác nhận đã trả tiền.</UiNotice>
  </template></section></template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import { useTenantList } from '@/composables/useTenantApi'
import { dueDateText, dueStatusLabel } from '@/lib/invoice-controls'
import { formatMoney } from '@/lib/format'
import { tenantPaymentLabel as paymentStatusLabel } from '@/types/tenant'
import type { Invoice } from '@/types/billing'
const route = useRoute(), router = useRouter(), dueStatus = ref(''), month = ref(''), paymentStatus = ref('')
const { items, page, lastPage, total, loading, error, load } = useTenantList<Invoice>('/invoices')
function filters() { return { ...(dueStatus.value ? { due_status: dueStatus.value } : {}), ...(month.value ? { billing_month: month.value } : {}), ...(paymentStatus.value ? { payment_status: paymentStatus.value } : {}) } }
function filter() { const next = router.resolve({ path: route.path, query: filters() }); if (next.fullPath === route.fullPath) void load(1, filters()); else void router.replace(next.fullPath) }
watch(() => route.query, query => {
  dueStatus.value = typeof query.due_status === 'string' && ['overdue', 'upcoming', 'not_set'].includes(query.due_status) ? query.due_status : ''; month.value = typeof query.billing_month === 'string' && /^[1-9]\d{3}-(0[1-9]|1[0-2])$/.test(query.billing_month) ? query.billing_month : ''
  paymentStatus.value = typeof query.payment_status === 'string' && ['unpaid', 'partially_paid', 'paid', 'inconsistent', 'cancelled'].includes(query.payment_status) ? query.payment_status : ''
  void load(1, filters())
}, { immediate: true })
</script>
<template><section class="management-page tenant-page"><header class="management-header"><div><span class="eyebrow">Tiền phòng & dịch vụ</span><h1>Hóa đơn của tôi</h1><p>Các hóa đơn chủ trọ đã cấp quyền cho tài khoản của bạn.</p></div><UiButton variant="secondary" :loading="loading" @click="load()">Làm mới hóa đơn</UiButton></header>
  <form class="management-toolbar" @submit.prevent="filter"><label>Hạn thanh toán<select v-model="dueStatus" class="ui-select"><option value="">Tất cả</option><option value="overdue">Quá hạn</option><option value="upcoming">Chưa quá hạn</option><option value="not_set">Chưa đặt hạn</option></select></label><label>Kỳ hóa đơn<input v-model="month" type="month" class="ui-input" /></label><label>Trạng thái<select v-model="paymentStatus" class="ui-select"><option value="">Tất cả</option><option value="unpaid">Chưa thanh toán</option><option value="partially_paid">Thanh toán một phần</option><option value="paid">Đã thanh toán đủ</option><option value="cancelled">Đã hủy</option><option value="inconsistent">Cần đối chiếu</option></select></label><UiButton type="submit" variant="secondary" :loading="loading">Lọc hóa đơn</UiButton></form>
  <UiPageState v-if="loading" kind="loading" /><UiPageState v-else-if="error" kind="error" :description="error"><UiButton @click="load()">Thử lại</UiButton></UiPageState><UiPageState v-else-if="!items.length" title="Chưa có hóa đơn phù hợp" description="Hóa đơn chỉ xuất hiện khi chủ trọ cấp quyền xem cho bạn. Bạn vẫn xem được hóa đơn cũ còn quyền sau khi rời phòng." />
  <div v-else class="management-grid"><article v-for="invoice in items" :key="invoice.id" class="record-card"><header><div><h2>Tháng {{ invoice.billing_month.slice(5) }}/{{ invoice.billing_month.slice(0, 4) }}</h2><span class="help-text">Phòng {{ invoice.room.room_number }} · {{ invoice.invoice_number }}</span></div><UiBadge :tone="invoice.payment_status === 'paid' ? 'success' : invoice.payment_status === 'inconsistent' ? 'danger' : 'warning'">{{ paymentStatusLabel(invoice.payment_status) }}</UiBadge></header><dl class="record-facts"><div><dt>Hạn thanh toán</dt><dd>{{ dueDateText(invoice.due_date) }}<span v-if="dueStatusLabel(invoice)" class="help-text"> · {{ dueStatusLabel(invoice) }}</span></dd></div><div><dt>Tổng hóa đơn</dt><dd>{{ formatMoney(invoice.total_amount) }}</dd></div><div><dt>Đã thanh toán</dt><dd>{{ formatMoney(invoice.paid_amount) }}</dd></div><div><dt>Còn cần thanh toán</dt><dd><strong>{{ formatMoney(invoice.outstanding_amount) }}</strong></dd></div></dl><RouterLink class="record-link" :to="`/tenant/invoices/${invoice.id}`">Xem hóa đơn và lịch sử thanh toán →</RouterLink></article></div>
  <OwnerPager v-if="!error && total" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" /><UiNotice tone="info">Số tiền là của hóa đơn phòng/nhóm, không tự chia thành khoản nợ riêng từng thành viên. Thanh toán được chủ trọ ghi nhận sau khi thực nhận tiền.</UiNotice>
</section></template>

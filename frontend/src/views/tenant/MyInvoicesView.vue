<script setup lang="ts">
import { useLiveRefresh } from '@/composables/useLiveRefresh'
import { computed, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import { useTenantList } from '@/composables/useTenantApi'
import { dueDateText, dueStatusLabel } from '@/lib/invoice-controls'
import { formatMoney } from '@/lib/format'
import { tenantHasBalance, tenantPaymentLabel, tenantPaymentTone } from '@/types/tenant'
import type { Invoice } from '@/types/billing'
const route = useRoute(), router = useRouter(), dueStatus = ref(''), month = ref(''), paymentStatus = ref('')
const hasFilters = ref(false)
const listSection = ref<HTMLElement | null>(null)
const { items, page, lastPage, total, loading, error, load } = useTenantList<Invoice>('/invoices')
const requestedPage = computed(() => {
  const next = typeof route.query.page === 'string' ? Number(route.query.page) : 1
  return Number.isSafeInteger(next) && next > 0 ? next : 1
})
function filters() { return { ...(dueStatus.value ? { due_status: dueStatus.value } : {}), ...(month.value ? { billing_month: month.value } : {}), ...(paymentStatus.value ? { payment_status: paymentStatus.value } : {}) } }
function filter() { const next = router.resolve({ path: route.path, query: filters() }); if (next.fullPath === route.fullPath) void load(1, filters()); else void router.replace(next.fullPath) }
function changePage(next: number) {
  void router.replace({ query: { ...route.query, page: next > 1 ? String(next) : undefined } })
}

const returnLocation = route.fullPath
const savedScroll = window.history.state?.tenantInvoiceScroll
let returnScroll: number | null = savedScroll?.path === returnLocation && typeof savedScroll.y === 'number' && Number.isFinite(savedScroll.y) ? savedScroll.y : null
onBeforeRouteLeave(to => {
  if (to.name === 'tenant-invoice' && listSection.value && window.history.state?.current === route.fullPath) {
    const y = -listSection.value.getBoundingClientRect().top
    window.history.replaceState({ ...window.history.state, tenantInvoiceScroll: { path: route.fullPath, y } }, '')
  }
})
watch(loading, busy => {
  if (!busy && returnScroll !== null && listSection.value && route.fullPath === returnLocation && !error.value) {
    // The connection banner can change the page height above the list after a retry.
    const top = window.scrollY + listSection.value.getBoundingClientRect().top + returnScroll
    window.scrollTo({ top, behavior: 'instant' })
    returnScroll = null
    window.history.replaceState({ ...window.history.state, tenantInvoiceScroll: null }, '')
  }
}, { flush: 'post' })
watch(() => route.query, query => {
  if (route.name !== 'tenant-invoices') return
  dueStatus.value = typeof query.due_status === 'string' && ['overdue', 'upcoming', 'not_set'].includes(query.due_status) ? query.due_status : ''; month.value = typeof query.billing_month === 'string' && /^[1-9]\d{3}-(0[1-9]|1[0-2])$/.test(query.billing_month) ? query.billing_month : ''
  paymentStatus.value = typeof query.payment_status === 'string' && ['unpaid', 'partially_paid', 'paid', 'inconsistent', 'cancelled'].includes(query.payment_status) ? query.payment_status : ''
  hasFilters.value = Boolean(dueStatus.value || month.value || paymentStatus.value)
  void load(requestedPage.value, filters())
}, { immediate: true })
useLiveRefresh('property', () => !loading.value ? load(page.value) : false)
</script>
<template>
  <section ref="listSection" class="management-page tenant-page tenant-workspace tenant-invoice-list">
    <header class="management-header workflow-header">
      <div><span class="tenant-kicker">Thanh toán tiền trọ</span><h1>Hóa đơn của tôi</h1><p>Theo dõi số tiền còn thiếu và lịch sử thanh toán của phòng.</p></div>
      <UiButton variant="secondary" :loading="loading" @click="load(requestedPage)">Làm mới hóa đơn</UiButton>
    </header>
    <details class="workflow-filters" :open="hasFilters">
      <summary>Bộ lọc hóa đơn <span v-if="hasFilters" class="help-text">Đang lọc</span></summary>
      <form class="management-toolbar" @submit.prevent="filter">
        <label>Kỳ hóa đơn<input v-model="month" type="month" class="ui-input" /></label>
        <label>Trạng thái<select v-model="paymentStatus" class="ui-select"><option value="">Tất cả</option><option value="unpaid">Chưa thanh toán</option><option value="partially_paid">Thanh toán một phần</option><option value="paid">Đã thanh toán đủ</option><option value="cancelled">Đã hủy</option><option value="inconsistent">Cần đối chiếu</option></select></label>
        <UiButton type="submit" variant="secondary" :loading="loading">Lọc hóa đơn</UiButton>
        <RouterLink v-if="hasFilters" class="workflow-card-link" :to="route.path">Xóa bộ lọc</RouterLink>
        <details class="workflow-disclosure" :open="!!dueStatus">
          <summary>Bộ lọc thêm</summary>
          <div class="workflow-disclosure-body workflow-extra-fields">
            <label>Hạn thanh toán<select v-model="dueStatus" class="ui-select"><option value="">Tất cả</option><option value="overdue">Quá hạn</option><option value="upcoming">Chưa quá hạn</option><option value="not_set">Chưa đặt hạn</option></select></label>
          </div>
        </details>
      </form>
    </details>
    <UiPageState v-if="loading" kind="loading" skeleton="invoices" title="Đang tải hóa đơn…" />
    <UiPageState v-else-if="error" kind="error" :description="error"><UiButton @click="load(requestedPage)">Tải lại</UiButton></UiPageState>
    <UiPageState v-else-if="!items.length" title="Chưa có hóa đơn phù hợp" :description="hasFilters ? 'Xóa bộ lọc để xem tất cả hóa đơn của bạn.' : 'Hóa đơn sẽ xuất hiện khi chủ trọ cấp quyền xem cho bạn.'" />
    <div v-else class="invoice-list rb7-record-list">
      <article v-for="invoice in items" :key="invoice.id" class="record-card workflow-list-card rb7-record-row">
        <header>
          <div><span class="tenant-kicker">Phòng {{ invoice.room.room_number }}</span><h2>Tháng {{ invoice.billing_month.slice(5) }}/{{ invoice.billing_month.slice(0, 4) }}</h2><span class="help-text">{{ invoice.invoice_number }}</span></div>
          <UiBadge :tone="tenantPaymentTone(invoice.payment_status)">{{ tenantPaymentLabel(invoice.payment_status) }}</UiBadge>
        </header>
        <div class="workflow-list-amount" :class="{ 'amount-due': tenantHasBalance(invoice.payment_status) }">
          <div>
            <span class="workflow-caption">{{ tenantHasBalance(invoice.payment_status) ? 'Còn cần thanh toán' : 'Tổng hóa đơn' }}</span>
            <strong class="workflow-amount">{{ formatMoney(tenantHasBalance(invoice.payment_status) ? invoice.outstanding_amount : invoice.total_amount) }}</strong>
          </div>
          <p v-if="tenantHasBalance(invoice.payment_status)" class="workflow-deadline">
            <strong>{{ invoice.due_date ? `Hạn ${dueDateText(invoice.due_date)}` : 'Chưa đặt hạn' }}</strong>
            <span v-if="invoice.due_status === 'overdue'">{{ dueStatusLabel(invoice) }}</span>
          </p>
        </div>
        <footer><RouterLink class="workflow-card-link" :to="{ name: 'tenant-invoice', params: { id: invoice.id }, state: { tenantInvoiceList: route.fullPath } }">Xem hóa đơn và lịch sử thanh toán →</RouterLink></footer>
      </article>
    </div>
    <OwnerPager v-if="!error && lastPage > 1" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="changePage" />
  </section>
</template>

<style scoped>
.invoice-list { display: grid; gap: 12px; }
.invoice-list .record-card { padding: clamp(14px, 2.5vw, 22px); }
.invoice-list header { align-items: flex-start; }
.workflow-list-amount {
  display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between;
  gap: 8px 16px; padding: 12px 14px; border-radius: 8px; background: var(--color-bg);
}
.workflow-list-amount.amount-due { background: var(--color-primary-soft); }
.workflow-list-amount .workflow-amount { font-size: clamp(23px, 4vw, 30px); }
.workflow-list-amount .workflow-deadline { margin: 0; }
</style>

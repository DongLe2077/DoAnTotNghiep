<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import RemotePicker from '@/components/owner/RemotePicker.vue'
import { useOwnerList } from '@/composables/useOwnerApi'
import { dueDateText, dueStatusLabel } from '@/lib/invoice-controls'
import { formatMoney } from '@/lib/format'
import { paymentStatusLabel } from '@/lib/billing'
import type { Invoice } from '@/types/billing'
const route = useRoute(), router = useRouter(), dueStatus = ref(''), month = ref(''), status = ref(''), rooms = ref<number[]>([]), groups = ref<number[]>([]), pickerOpen = ref(false), occupancyId = ref<number | null>(null)
function positiveId(value: unknown) { if (typeof value !== 'string' || !/^[1-9]\d*$/.test(value)) return null; const id = Number(value); return Number.isSafeInteger(id) ? id : null }
function filters() { return { ...(dueStatus.value ? { due_status: dueStatus.value } : {}), ...(month.value ? { billing_month: month.value } : {}), ...(status.value ? { payment_status: status.value } : {}), ...(rooms.value[0] ? { room_id: rooms.value[0] } : {}), ...(groups.value[0] ? { rental_group_id: groups.value[0] } : {}), ...(occupancyId.value ? { occupancy_id: occupancyId.value } : {}) } }
const { items, loading, loadError, page, lastPage, total, load } = useOwnerList<Invoice>('/invoices')
function filter() { const query: Record<string, string> = {}; for (const [key, value] of Object.entries(filters())) query[key] = String(value); const next = router.resolve({ path: route.path, query }); if (next.fullPath === route.fullPath) void load(1, filters()); else void router.replace(next.fullPath) }
function clearStay() { occupancyId.value = null; filter() }
watch(() => route.query, query => { dueStatus.value = typeof query.due_status === 'string' && ['overdue', 'upcoming', 'not_set'].includes(query.due_status) ? query.due_status : ''; month.value = typeof query.billing_month === 'string' && /^\d{4}-(0[1-9]|1[0-2])$/.test(query.billing_month) ? query.billing_month : ''; status.value = typeof query.payment_status === 'string' && ['unpaid', 'partially_paid', 'paid', 'inconsistent', 'cancelled'].includes(query.payment_status) ? query.payment_status : ''; const room = positiveId(query.room_id), group = positiveId(query.rental_group_id); rooms.value = room ? [room] : []; groups.value = group ? [group] : []; occupancyId.value = positiveId(query.occupancy_id); void load(1, filters()) }, { immediate: true })
</script>
<template>
  <section class="management-page">
    <header class="management-header workflow-header">
      <div><h1>Hóa đơn</h1></div>
      <div class="button-row">
        <RouterLink class="workflow-card-link" to="/owner/payments">Lịch sử thu tiền</RouterLink>
        <RouterLink class="workflow-card-link" to="/owner/invoices/new">+ Lập hóa đơn</RouterLink>
      </div>
    </header>
    <p v-if="occupancyId" class="help-text">Đang lọc theo đợt thuê. <UiButton variant="ghost" @click="clearStay">Bỏ lọc đợt thuê</UiButton></p>
    <form class="management-toolbar" @submit.prevent="filter">
      <label>Tháng hóa đơn<input v-model="month" type="month" class="ui-input" /></label>
      <label>Trạng thái thanh toán
        <select v-model="status" class="ui-select">
          <option value="">Tất cả</option><option value="unpaid">Chưa thu</option><option value="partially_paid">Thu một phần</option>
          <option value="paid">Đã thu đủ</option><option value="cancelled">Đã hủy</option><option value="inconsistent">Cần kiểm tra</option>
        </select>
      </label>
      <UiButton type="submit" variant="secondary" :loading="loading">Lọc hóa đơn</UiButton>
      <details class="workflow-disclosure" @toggle="pickerOpen = ($event.currentTarget as HTMLDetailsElement).open">
        <summary>Bộ lọc thêm<span v-if="route.query.due_status || route.query.room_id || route.query.rental_group_id"> · Đang lọc</span></summary>
        <div class="workflow-disclosure-body workflow-extra-fields">
          <label>Hạn thanh toán<select v-model="dueStatus" class="ui-select"><option value="">Tất cả</option><option value="overdue">Quá hạn</option><option value="upcoming">Chưa quá hạn</option><option value="not_set">Chưa đặt hạn</option></select></label>
          <div v-if="pickerOpen" class="management-grid extra-pickers">
            <RemotePicker v-model="rooms" label="Phòng" path="/rooms" label-key="room_number" />
            <RemotePicker v-model="groups" label="Nhóm thuê" path="/rental-groups" label-key="group_code" />
          </div>
        </div>
      </details>
    </form>
    <UiPageState v-if="loading" kind="loading" skeleton="cards" />
    <UiPageState v-else-if="loadError" kind="error" :description="loadError"><UiButton @click="load()">Thử lại</UiButton></UiPageState>
    <UiPageState v-else-if="!items.length" title="Chưa có hóa đơn phù hợp" description="Thử đổi bộ lọc hoặc lập hóa đơn cho kỳ đã kết thúc." />
    <div v-else class="management-grid workflow-grid">
      <article v-for="invoice in items" :key="invoice.id" class="record-card workflow-list-card">
        <header>
          <div><h2>Phòng {{ invoice.room.room_number }}</h2><span class="help-text">{{ invoice.invoice_number }} · Kỳ {{ invoice.billing_month }}</span></div>
          <UiBadge :tone="invoice.payment_status === 'paid' ? 'success' : invoice.payment_status === 'inconsistent' ? 'danger' : invoice.payment_status === 'cancelled' ? 'neutral' : 'warning'">{{ paymentStatusLabel(invoice.payment_status) }}</UiBadge>
        </header>
        <div class="workflow-list-amount">
          <span class="workflow-caption">{{ invoice.payment_status === 'paid' ? 'Đã thu' : invoice.payment_status === 'cancelled' || invoice.payment_status === 'inconsistent' ? 'Tổng hóa đơn' : 'Còn phải thu' }}</span>
          <strong class="workflow-amount">{{ formatMoney(invoice.payment_status === 'paid' ? invoice.paid_amount : invoice.payment_status === 'cancelled' || invoice.payment_status === 'inconsistent' ? invoice.total_amount : invoice.outstanding_amount) }}</strong>
        </div>
        <p v-if="invoice.payment_status === 'unpaid' || invoice.payment_status === 'partially_paid'" class="workflow-deadline">
          <strong>{{ invoice.due_date ? `Hạn ${dueDateText(invoice.due_date)}` : 'Chưa đặt hạn' }}</strong>
          <span v-if="invoice.due_status === 'overdue'">{{ dueStatusLabel(invoice) }}</span>
        </p>
        <footer><RouterLink class="workflow-card-link" :to="`/owner/invoices/${invoice.id}`">Xem hóa đơn {{ invoice.invoice_number }}</RouterLink></footer>
      </article>
    </div>
    <OwnerPager v-if="!loadError && total" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" />
  </section>
</template>

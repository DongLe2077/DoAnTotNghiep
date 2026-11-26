<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import UiDialog from '@/components/ui/UiDialog.vue'
import OwnerCheckoutRequests from '@/components/owner/OwnerCheckoutRequests.vue'
import FinanceChart from './FinanceChart.vue'
import { localEventTime, useOwnerApi } from '@/composables/useOwnerApi'
import { useLiveRefresh } from '@/composables/useLiveRefresh'
import { formatDate, formatMoney } from '@/lib/format'
import { formatDateTime } from '@/lib/notifications'
import { toApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { Dashboard, OutstandingInvoice } from '@/types/reporting'
import type { OwnerRoomRequest } from '@/types/marketplace'
import type { MaintenanceRequest } from '@/types/maintenance'
import type { PageResult } from '@/types/owner'
import './owner-preview.css'

type WorkItem = {
  key: string; kind: 'visit' | 'invoice' | 'repair'; title: string; context: string
  status: string; details: [string, string][]; destination?: string; requestId?: number
}
const route = useRoute()
const auth = useAuthStore()
const { get, write } = useOwnerApi()
const finance = computed(() => route.name === 'owner-finance-preview')
const property = computed(() => auth.user?.properties?.find(p => p.id === auth.user?.property_id)?.name ?? 'Khu trọ của bạn')
const currentMonth = localEventTime().slice(0, 7)
const month = ref(currentMonth)
const report = ref<Dashboard>()
const upcoming = ref<OutstandingInvoice[]>([])
const overdue = ref<OutstandingInvoice[]>([])
const visits = ref<OwnerRoomRequest[]>([])
const repairs = ref<MaintenanceRequest[]>([])
const loading = ref(true), errors = ref<string[]>([])
const item = ref<WorkItem>(), actionError = ref(''), notice = ref(''), busy = ref(false)
let sequence = 0
onBeforeUnmount(() => { sequence++ })

async function nearest(status: 'upcoming' | 'overdue', token: number) {
  // The existing API orders by billing period, so compare due dates across pages.
  const byDate = (a: OutstandingInvoice, b: OutstandingInvoice) =>
    (a.due_date ?? '').localeCompare(b.due_date ?? '') || a.id - b.id
  let result: OutstandingInvoice[] = []
  let page = 1
  do {
    const data = await get<PageResult<OutstandingInvoice>>('/receivables', { due_status: status, page, per_page: 100 })
    if (token !== sequence) return []
    result = [...result, ...data.data].filter(row => row.due_date).sort(byDate).slice(0, 3)
    if (page >= data.meta.last_page) return result
    page++
  } while (token === sequence)
  return []
}

async function load(background = false) {
  if (!month.value) month.value = currentMonth
  const token = ++sequence
  const showFinance = finance.value
  loading.value = true
  const tasks: Promise<void>[] = []
  const failed: string[] = []
  let accessLost = false
  function add<T>(promise: Promise<T>, set: (value: T) => void, label: string) {
    tasks.push(promise.then(value => { if (token === sequence) set(value) }).catch(error => {
      if (token !== sequence) return
      failed.push(label)
      if ([401, 403, 409].includes(toApiError(error).status)) accessLost = true
    }))
  }
  add(get<{data: Dashboard}>('/dashboard', { month: showFinance ? month.value : currentMonth }), value => { report.value = value.data }, 'Báo cáo')
  add(nearest('upcoming', token), value => { upcoming.value = value }, 'Khoản sắp đến hạn')
  if (!showFinance) {
    add(nearest('overdue', token), value => { overdue.value = value }, 'Khoản quá hạn')
    if (auth.user?.property_id) {
      add(get<PageResult<OwnerRoomRequest>>('/marketplace/requests', {kind:'viewing',status:'pending'}), value => { visits.value = value.data }, 'Lịch xem')
    }
    add(get<PageResult<MaintenanceRequest>>('/maintenance-requests', {status:'submitted',per_page:3}), value => { repairs.value = value.data }, 'Báo hỏng')
  }
  if (!background) errors.value = []
  await Promise.all(tasks)
  if (token === sequence) {
    if (accessLost) clearData()
    errors.value = [...new Set(failed)]
    loading.value = false
  }
}

function clearData() {
  report.value = undefined
  upcoming.value = []; overdue.value = []; visits.value = []; repairs.value = []
  item.value = undefined
}

watch(() => [route.name, auth.user?.id, auth.user?.property_id], () => {
  clearData()
  notice.value = ''
  void load()
}, {immediate:true})
useLiveRefresh('property', () => !busy.value && !loading.value ? load(true) : false)

const due = (row: OutstandingInvoice) => row.due_date ? formatDate(row.due_date) : 'Chưa có hạn'
const work = computed<WorkItem[]>(() => {
  const visitItems: WorkItem[] = visits.value.slice(0, 3).map(row => ({
    key:'visit-'+row.id, kind:'visit', title:row.title, context:row.contact_name+' · '+formatDateTime(row.preferred_at),
    status:'Chờ xác nhận', details:[['Khách',row.contact_name],['Giờ khách chọn',formatDateTime(row.preferred_at)]],
    requestId:row.id,
  }))
  const invoiceItems: WorkItem[] = [...overdue.value, ...upcoming.value.filter(row => row.due_date === localEventTime().slice(0,10))].slice(0, 3).map(row => ({
    key:'invoice-'+row.id, kind:'invoice', title:'Hóa đơn phòng '+row.room.room_number,
    context:'Còn phải thu '+formatMoney(row.outstanding_amount), status:row.due_status === 'overdue' ? 'Quá hạn' : 'Đến hạn hôm nay',
    details:[['Hạn thanh toán',due(row)],['Còn phải thu',formatMoney(row.outstanding_amount)]],
    destination:'/owner/invoices/'+row.id,
  }))
  const repairItems: WorkItem[] = repairs.value.slice(0, 3).map(row => ({
    key:'repair-'+row.id, kind:'repair', title:row.title, context:'Phòng '+row.room.room_number+' · '+formatDateTime(row.submitted_at),
    status:'Chờ tiếp nhận', details:[['Phòng',row.room.room_number],['Gửi lúc',formatDateTime(row.submitted_at)]],
    destination:'/owner/maintenance/'+row.id,
  }))
  return [visitItems, invoiceItems, repairItems].flatMap((_, index) => [visitItems[index],invoiceItems[index],repairItems[index]])
    .filter((entry): entry is WorkItem => entry !== undefined)
})

function open(row: WorkItem) {
  actionError.value = ''
  item.value = row
}
async function confirmVisit() {
  const id = item.value?.requestId
  if (!id || busy.value) return
  busy.value = true
  actionError.value = ''
  try {
    await write('put', '/marketplace/requests/' + id, { action:'confirm' })
    item.value = undefined
    notice.value = 'Đã xác nhận giờ xem phòng.'
    await load(true)
  } catch (error) {
    actionError.value = toApiError(error).message
  } finally { busy.value = false }
}
</script>

<template>
  <section aria-label="Không gian công việc chủ trọ">
      <div :key="route.name?.toString()" :aria-busy="loading">
        <header class="rb7-intro"><div>
          <p class="rb7-property"><RouterLink v-if="auth.user?.properties" class="record-link" to="/properties">{{ property }}</RouterLink><template v-else>{{ property }}</template></p>
          <h1>{{ finance ? 'Tài chính' : 'Việc cần làm' }}</h1>
          <p>{{ finance ? 'Theo dõi tiền đã thu, tiền đã chi và số còn phải thu.' : 'Lịch hẹn, khoản cần thu và báo hỏng cần xử lý.' }}</p>
        </div>
        <label v-if="finance" class="rb7-filter">Tháng báo cáo<input v-model="month" type="month" @change="load()" /></label>
        <p v-else>{{ formatDate(localEventTime().slice(0,10)) }}</p></header>
        <p v-if="notice" role="status" class="rb7-status">{{ notice }}</p>
        <div v-if="errors.length" class="rb7-message" role="alert">Chưa tải được {{ errors.join(', ').toLocaleLowerCase('vi-VN') }}. <button type="button" class="rb7-action" @click="load()">Tải lại</button></div>
        <p v-if="loading && !report" role="status">Đang tải dữ liệu…</p>

        <template v-if="finance">
          <template v-if="report">
            <div class="rb7-metrics">
              <div class="rb7-panel"><span>Thực thu trong kỳ</span><b>{{ formatMoney(report.cash_flow.total_income) }}</b></div>
              <div class="rb7-panel"><span>Thực chi trong kỳ</span><b>{{ formatMoney(report.cash_flow.expense_total) }}</b></div>
              <div class="rb7-panel"><span>Còn phải thu mọi kỳ</span><b>{{ formatMoney(report.receivables.outstanding_amount) }}</b></div>
            </div>
            <FinanceChart :key="report.month" :periods="report.monthly_series" />
          </template>
          <section class="rb7-panel rb7-collect">
            <header><h2>Khoản cần thu gần nhất</h2><RouterLink class="record-link" to="/owner/receivables">Xem công nợ</RouterLink></header>
            <ul v-if="upcoming.length"><li v-for="row in upcoming" :key="row.id">
              <RouterLink class="rb7-invoice-row" :to="'/owner/invoices/'+row.id"><span><b>Phòng {{ row.room.room_number }}</b><small>Hạn {{ due(row) }}</small></span><strong>{{ formatMoney(row.outstanding_amount) }}</strong></RouterLink>
            </li></ul>
            <p v-else-if="!loading && !errors.includes('Khoản sắp đến hạn')">Chưa có khoản sắp đến hạn.</p>
          </section>
        </template>
        <div v-else class="rb7-work">
          <section class="rb7-panel rb7-list" aria-labelledby="rb7-queue">
            <header><h2 id="rb7-queue">Cần xử lý</h2><span>{{ loading && !work.length ? 'Đang tải…' : work.length + ' việc đang hiển thị' }}</span></header>
            <button v-for="row in work" :key="row.key" class="rb7-task" type="button" @click="open(row)">
              <span class="rb7-task-icon" :class="'is-'+row.kind" aria-hidden="true">{{ { visit:'◷',invoice:'₫',repair:'⚒' }[row.kind] }}</span>
              <span><b>{{ row.title }}</b><small>{{ row.context }}</small></span>
              <span class="rb7-pill" :class="'is-'+row.kind">{{ row.status }}</span>
              <span class="rb7-arrow" aria-hidden="true">›</span>
            </button>
            <p v-if="!loading && !work.length && !errors.length" class="rb7-empty">Chưa có việc cần xử lý.</p>
          </section>
          <aside v-if="report" class="rb7-rooms" aria-labelledby="rb7-rooms">
            <h2 id="rb7-rooms">Tình trạng phòng</h2>
            <p class="rb7-big">{{ report.rooms.vacant_count }}</p><p>phòng trống</p>
            <dl><div><dt>Đang có người ở</dt><dd>{{ report.rooms.occupied_count }}</dd></div>
                <div><dt>Đang bảo trì</dt><dd>{{ report.rooms.maintenance_count }}</dd></div>
                <div><dt>Tổng số phòng</dt><dd>{{ report.rooms.total_count }}</dd></div></dl>
          </aside>
        </div>
        <OwnerCheckoutRequests v-if="!finance" />
      </div>
    <UiDialog class="rb7-drawer" :open="Boolean(item)" :title="item?.title ?? 'Chi tiết việc'" @update:open="value => { if (!value && !busy) item = undefined }">
      <dl v-if="item"><div v-for="([label,value]) in item.details" :key="label"><dt>{{ label }}</dt><dd>{{ value }}</dd></div></dl>
      <p v-if="actionError" role="alert" class="rb7-message">{{ actionError }}</p>
      <template #actions>
        <button v-if="item?.requestId" class="rb7-primary" type="button" :disabled="busy" @click="confirmVisit">{{ busy ? 'Đang xác nhận…' : 'Xác nhận giờ xem' }}</button>
        <RouterLink v-else-if="item?.destination" class="rb7-primary" :to="item.destination">Mở chi tiết</RouterLink>
      </template>
    </UiDialog>
  </section>
</template>
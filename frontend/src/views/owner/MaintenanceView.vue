<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import RemotePicker from '@/components/owner/RemotePicker.vue'
import { useOwnerList } from '@/composables/useOwnerApi'
import { formatDateTime } from '@/lib/notifications'
import { maintenanceStatuses, maintenanceStatusLabel, maintenanceStatusTone, type MaintenanceRequest, type MaintenanceStatus } from '@/types/maintenance'

const route = useRoute(), router = useRouter()
const { items, loading, loadError, page, lastPage, total, load } = useOwnerList<MaintenanceRequest>('/maintenance-requests')
const search = ref(''), status = ref(''), from = ref(''), until = ref('')
const room = ref<number[]>([]), reporter = ref<number[]>([]), submittedBy = ref<number | null>(null), filterError = ref('')
let sequence = 0
function positive(value: unknown) { const number = Number(value); return Number.isSafeInteger(number) && number > 0 ? number : null }
const requestedPage = computed(() => positive(route.query.page) ?? 1)
const activeFilters = computed(() => Object.keys(route.query).some(key => key !== 'page'))
function chooseReporter(value: Record<string, unknown>) {
  const account = value.account
  submittedBy.value = account && typeof account === 'object' && 'id' in account ? positive(account.id) : null
}
async function applyFilters() {
  filterError.value = ''
  if (from.value && until.value && until.value < from.value) { filterError.value = 'Ngày kết thúc cần từ ngày bắt đầu trở đi.'; return }
  await router.replace({ path: '/owner/maintenance', query: {
    q: search.value.trim() || undefined, status: status.value || undefined,
    room_id: room.value[0] ? String(room.value[0]) : undefined,
    submitted_by: submittedBy.value ? String(submittedBy.value) : undefined,
    from: from.value ? `${from.value} 00:00:00` : undefined,
    until: until.value ? `${until.value} 23:59:59` : undefined,
  } })
}
async function changePage(next: number) { await router.replace({ query: { ...route.query, page: next > 1 ? String(next) : undefined } }) }
watch(reporter, (value, previous) => { if (!value.length && previous.length) submittedBy.value = null })
watch(() => route.fullPath, async () => {
  const current = ++sequence
  search.value = typeof route.query.q === 'string' ? route.query.q.slice(0, 150) : ''
  status.value = maintenanceStatuses.includes(route.query.status as MaintenanceStatus) ? String(route.query.status) : ''
  from.value = typeof route.query.from === 'string' ? route.query.from.slice(0, 10) : ''
  until.value = typeof route.query.until === 'string' ? route.query.until.slice(0, 10) : ''
  room.value = positive(route.query.room_id) ? [positive(route.query.room_id)!] : []
  submittedBy.value = positive(route.query.submitted_by)
  if (!submittedBy.value) reporter.value = []
  const filters: Record<string, unknown> = {}
  if (search.value.trim()) filters.q = search.value.trim()
  if (status.value) filters.status = status.value
  for (const key of ['room_id', 'submitted_by', 'occupancy_id', 'rental_group_id']) if (positive(route.query[key])) filters[key] = positive(route.query[key])
  for (const key of ['from', 'until']) if (typeof route.query[key] === 'string' && route.query[key]) filters[key] = route.query[key]
  await load(requestedPage.value, filters)
  if (current === sequence && !loadError.value && page.value !== requestedPage.value) await changePage(page.value)
}, { immediate: true })
</script>

<template>
  <section class="management-page">
    <header class="management-header"><div><span class="eyebrow">Chăm sóc nơi ở</span><h1>Yêu cầu sửa chữa</h1><p>Tiếp nhận báo hỏng, xem ảnh và cập nhật kết quả cho người thuê.</p></div><UiButton variant="secondary" :loading="loading" @click="load()">Làm mới danh sách</UiButton></header>
    <form class="maintenance-filters" @submit.prevent="applyFilters">
      <div class="management-toolbar">
        <label>Tìm yêu cầu<input v-model="search" class="ui-input" maxlength="150" placeholder="Tiêu đề, mô tả hoặc nội dung xử lý" /></label>
        <div class="filter-field"><label for="maintenance-filter-status">Trạng thái yêu cầu</label><select id="maintenance-filter-status" v-model="status" class="ui-select"><option value="">Tất cả trạng thái</option><option v-for="maintenanceState in maintenanceStatuses" :key="maintenanceState" :value="maintenanceState">{{ maintenanceStatusLabel(maintenanceState) }}</option></select></div>
        <label>Gửi từ ngày<input v-model="from" type="date" class="ui-input" /></label>
        <label>Gửi đến ngày<input v-model="until" type="date" class="ui-input" :min="from || undefined" /></label>
        <UiButton type="submit" :disabled="loading">Áp dụng bộ lọc</UiButton>
        <UiButton v-if="activeFilters" variant="ghost" :disabled="loading" @click="router.replace('/owner/maintenance')">Xóa bộ lọc</UiButton>
      </div>
      <details class="record-card extra-filters"><summary>Lọc theo phòng và người gửi</summary><div class="management-grid">
        <RemotePicker v-model="room" label="Phòng báo hỏng" path="/rooms" label-key="room_number" />
        <RemotePicker v-model="reporter" label="Người gửi báo hỏng" path="/tenants" label-key="full_name" any-account @choose="chooseReporter" />
      </div><p v-if="submittedBy && !reporter.length" class="help-text">Đang lọc theo người gửi từ đường dẫn. Chọn một người gửi khác hoặc xóa bộ lọc để thay đổi.</p><UiButton type="submit" variant="secondary" :disabled="loading">Áp dụng lựa chọn</UiButton></details>
      <p v-if="filterError" class="form-error" role="alert">{{ filterError }}</p>
    </form>
    <UiPageState v-if="loading" kind="loading" title="Đang tải yêu cầu sửa chữa…" />
    <UiPageState v-else-if="loadError" kind="error" :description="loadError"><UiButton @click="load()">Thử lại</UiButton></UiPageState>
    <UiPageState v-else-if="!items.length" title="Chưa có yêu cầu phù hợp" description="Yêu cầu báo hỏng do người thuê gửi sẽ xuất hiện tại đây. Bạn có thể thay đổi bộ lọc để xem thêm." />
    <template v-else>
      <p class="help-text result-count">{{ total }} yêu cầu{{ activeFilters ? ' phù hợp với bộ lọc' : '' }} · Mới nhất trước</p>
      <div class="management-grid">
        <article v-for="item in items" :key="item.id" class="record-card maintenance-card" :aria-labelledby="`maintenance-title-${item.id}`">
          <header><div><span class="eyebrow">Phòng {{ item.room.room_number }}</span><h2 :id="`maintenance-title-${item.id}`">{{ item.title }}</h2></div><UiBadge :tone="maintenanceStatusTone(item.status)">{{ maintenanceStatusLabel(item.status) }}</UiBadge></header>
          <p class="request-description">{{ item.description }}</p>
          <dl class="record-facts"><div><dt>Người gửi</dt><dd>{{ item.reporter?.full_name ?? 'Tài khoản người thuê' }}</dd></div><div><dt>Thời gian gửi</dt><dd>{{ formatDateTime(item.submitted_at) }}</dd></div><div><dt>Ảnh đính kèm</dt><dd>{{ item.photos.length }} ảnh</dd></div></dl>
          <p v-if="item.resolution" class="resolution-summary"><strong>Cập nhật gần nhất:</strong> {{ item.resolution }}</p>
          <footer><RouterLink class="record-link" :to="`/owner/maintenance/${item.id}`" :aria-label="`Xem yêu cầu ${item.title}`">Xem yêu cầu →</RouterLink></footer>
        </article>
      </div>
      <nav class="owner-pagination" aria-label="Phân trang yêu cầu sửa chữa"><span>Trang {{ page }} / {{ lastPage }} · {{ total }} yêu cầu</span><div class="button-row"><UiButton variant="secondary" :disabled="page <= 1 || loading" @click="changePage(page - 1)">Trang trước</UiButton><UiButton variant="secondary" :disabled="page >= lastPage || loading" @click="changePage(page + 1)">Trang sau</UiButton></div></nav>
    </template>
  </section>
</template>

<style scoped>
.extra-filters { margin: -8px 0 24px; padding: 16px 20px; }
.extra-filters summary { min-height: 32px; align-content: center; color: var(--color-primary); font-size: 14px; cursor: pointer; }
.extra-filters .management-grid { margin: 16px 0; }
.management-toolbar { margin-bottom: 20px; }
.filter-field { display: grid; gap: 8px; font-size: 13px; flex: 1; min-width: 180px; }
.maintenance-card h2 { margin: 8px 0 0; font-size: 18px; }
.maintenance-card header { flex-wrap: wrap; }
.request-description, .resolution-summary { color: var(--color-text-secondary); font-size: 14px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; white-space: pre-line; }
.resolution-summary { padding: 12px; background: var(--color-bg); border-radius: 8px; }
.result-count { margin-bottom: 16px; }
</style>

<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import { useTenantApi, useTenantList } from '@/composables/useTenantApi'
import { toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import { maintenanceStatusLabel, maintenanceStatusTone } from '@/types/maintenance'
import type { EligibleStay, MyMaintenance } from '@/types/tenant'
const route = useRoute(), router = useRouter(), q = ref(''), selectedStatus = ref('')
const hasFilters = ref(false)
const { items, page, lastPage, total, loading, error, load } = useTenantList<MyMaintenance>('/maintenance-requests')
const client = useTenantApi(), eligible = ref<EligibleStay[]>([]), eligibilityError = ref(''), checking = ref(true)
let alive = true, sequence = 0
onBeforeUnmount(() => { alive = false; sequence++ })
async function checkEligibility() {
  const current = ++sequence; checking.value = true; eligible.value = []; eligibilityError.value = ''
  try { const result = await client.get<{ data: EligibleStay[] }>('/maintenance-requests/eligible-occupancies'); if (alive && current === sequence) eligible.value = result.data }
  catch (caught) { if (alive && current === sequence) eligibilityError.value = toApiError(caught).message }
  finally { if (alive && current === sequence) checking.value = false }
}
function filters() { return { ...(q.value.trim() ? { q: q.value.trim() } : {}), ...(selectedStatus.value ? { status: selectedStatus.value } : {}) } }
function filter() { const next = router.resolve({ path: route.path, query: filters() }); if (next.fullPath === route.fullPath) void load(1, filters()); else void router.replace(next.fullPath) }
watch(() => route.query, query => {
  q.value = typeof query.q === 'string' ? query.q.slice(0, 150) : ''
  selectedStatus.value = typeof query.status === 'string' && ['submitted', 'in_progress', 'resolved'].includes(query.status) ? query.status : ''
  hasFilters.value = Boolean(q.value.trim() || selectedStatus.value)
  void load(1, filters())
}, { immediate: true })
void checkEligibility()
</script>
<template>
  <section class="management-page tenant-page">
    <header class="management-header workflow-header">
      <div><h1>Báo hỏng của tôi</h1></div>
      <div><RouterLink v-if="eligible.length" class="tenant-primary-link" to="/tenant/maintenance/new">+ Gửi báo hỏng</RouterLink></div>
    </header>
    <UiNotice v-if="eligibilityError" tone="warning">Chưa kiểm tra được quyền gửi báo hỏng. {{ eligibilityError }} <UiButton variant="ghost" @click="checkEligibility">Kiểm tra lại quyền</UiButton></UiNotice>
    <UiNotice v-else-if="!checking && !eligible.length" tone="info">Nếu phòng có sự cố, nhờ người đại diện đang ở trong phòng gửi yêu cầu cho chủ trọ. Bạn vẫn có thể xem các yêu cầu mình đã gửi.</UiNotice>
    <details class="workflow-filters" :open="hasFilters">
      <summary>Tìm và lọc báo hỏng <span v-if="hasFilters" class="help-text">Đang lọc</span></summary>
      <form class="management-toolbar" @submit.prevent="filter">
        <label>Tìm yêu cầu<input v-model="q" class="ui-input" maxlength="150" type="search" /></label>
        <label>Trạng thái<select v-model="selectedStatus" class="ui-select"><option value="">Tất cả</option><option value="submitted">Chờ tiếp nhận</option><option value="in_progress">Đang xử lý</option><option value="resolved">Đã xử lý</option></select></label>
        <UiButton type="submit" variant="secondary" :loading="loading">Lọc yêu cầu</UiButton>
        <UiButton variant="ghost" :loading="loading || checking" @click="load(); checkEligibility()">Làm mới yêu cầu</UiButton>
        <RouterLink v-if="hasFilters" class="workflow-card-link" :to="route.path">Xóa bộ lọc</RouterLink>
      </form>
    </details>
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="error" kind="error" :description="error"><UiButton @click="load()">Thử lại</UiButton></UiPageState>
    <UiPageState v-else-if="!items.length" :title="hasFilters ? 'Chưa có yêu cầu phù hợp' : 'Bạn chưa gửi báo hỏng nào'" :description="hasFilters ? 'Xóa bộ lọc để xem tất cả báo hỏng của bạn.' : 'Các yêu cầu bạn gửi sẽ xuất hiện tại đây để theo dõi tiến độ.'" />
    <div v-else class="management-grid workflow-grid">
      <article v-for="report in items" :key="report.id" class="record-card workflow-list-card">
        <header>
          <div><h2>{{ report.title }}</h2><span class="help-text">Phòng {{ report.room.room_number }} · Gửi {{ formatDateTime(report.submitted_at) }}</span></div>
          <UiBadge :tone="maintenanceStatusTone(report.status)">{{ maintenanceStatusLabel(report.status) }}</UiBadge>
        </header>
        <p v-if="report.resolution" class="help-text maintenance-excerpt"><strong>Chủ trọ cập nhật</strong> · {{ report.resolution }}</p>
        <footer><RouterLink class="workflow-card-link" :to="`/tenant/maintenance/${report.id}`">Xem tiến độ và ảnh →</RouterLink><span class="help-text">{{ report.photos.length }} ảnh</span></footer>
      </article>
    </div>
    <OwnerPager v-if="!error && lastPage > 1" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" />
  </section>
</template>

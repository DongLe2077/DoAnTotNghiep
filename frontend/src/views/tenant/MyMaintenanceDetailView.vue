<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import MaintenancePhotos from '@/components/maintenance/MaintenancePhotos.vue'
import MaintenanceHistory from '@/components/maintenance/MaintenanceHistory.vue'
import TenantPhotoUpload from '@/components/maintenance/TenantPhotoUpload.vue'
import { useTenantResource } from '@/composables/useTenantApi'
import { formatDateTime } from '@/lib/notifications'
import { maintenanceStatuses, maintenanceStatusLabel, maintenanceStatusTone } from '@/types/maintenance'
import type { MyMaintenance } from '@/types/tenant'
const route = useRoute(), uploaded = ref(false), historyOpen = ref(false)
// Returning from a file chooser must not unmount the uploader and discard selected files.
const { data: report, loading, error, status, load } = useTenantResource<MyMaintenance>(() => `/maintenance-requests/${route.params.id}`, false)
const currentStep = {
  submitted: 'Chủ trọ sẽ tiếp nhận yêu cầu của bạn.',
  in_progress: 'Bạn sẽ nhận được thông báo khi có tiến độ mới.',
  resolved: 'Chủ trọ đã hoàn tất yêu cầu.',
}
async function refreshPhotos() { uploaded.value = true; await load() }
void load()
</script>
<template>
  <section class="management-page tenant-page tenant-workspace tenant-maintenance">
    <RouterLink class="back-link" to="/tenant/maintenance">← Báo hỏng của tôi</RouterLink>
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="error" :kind="status === 403 || status === 404 ? 'not-found' : 'error'" :title="status === 403 || status === 404 ? 'Yêu cầu không còn khả dụng' : 'Chưa tải được yêu cầu'" :description="status === 403 || status === 404 ? 'Bạn chỉ xem được yêu cầu do chính tài khoản mình gửi.' : error"><UiButton @click="load">Thử lại</UiButton></UiPageState>
    <template v-else-if="report">
      <header class="management-header">
        <div>
          <span class="tenant-kicker">Yêu cầu #{{ report.id }}</span>
          <h1>{{ report.title }}</h1>
          <p>Phòng {{ report.room.room_number }} · Gửi lúc {{ formatDateTime(report.submitted_at) }}</p>
        </div>
        <UiButton variant="secondary" :loading="loading" @click="load">Cập nhật tiến độ</UiButton>
      </header>
      <UiNotice v-if="route.query.created === '1'" tone="success">Đã gửi báo hỏng đến chủ trọ.</UiNotice>
      <UiNotice v-if="uploaded" tone="success">Ảnh đã được gửi thành công.</UiNotice>
      <div class="detail-sections workflow-sections tenant-repair-detail">
        <article class="record-card tenant-highlight tenant-repair-progress" aria-labelledby="tenant-progress-heading">
          <header>
            <h2 id="tenant-progress-heading">Tiến độ sửa chữa</h2>
            <UiBadge :tone="maintenanceStatusTone(report.status)" role="status">{{ maintenanceStatusLabel(report.status) }}</UiBadge>
          </header>
          <ol class="tenant-repair-stages" aria-label="Các trạng thái xử lý">
            <li v-for="(stage, index) in maintenanceStatuses" :key="stage" :aria-current="stage === report.status ? 'step' : undefined">
              <span aria-hidden="true">{{ index + 1 }}</span>
              {{ maintenanceStatusLabel(stage) }}
            </li>
          </ol>
          <p class="help-text">Cập nhật gần nhất {{ formatDateTime(report.updated_at) }}</p>
          <div v-if="report.resolution" class="tenant-repair-update">
            <h3>Chủ trọ cập nhật</h3>
            <p class="tenant-description">{{ report.resolution }}</p>
          </div>
          <p v-else class="tenant-description">{{ currentStep[report.status] }}</p>
        </article>
        <article class="record-card tenant-repair-description">
          <h2>Nội dung bạn đã gửi</h2>
          <p class="tenant-description">{{ report.description }}</p>
          <p class="help-text">Nhóm thuê {{ report.group.group_code }}</p>
        </article>
        <div class="wide tenant-repair-photos">
          <MaintenancePhotos :request-id="report.id" :photos="report.photos" audience="tenant" />
          <TenantPhotoUpload :report-id="report.id" :existing="report.photos.length" :resolved="report.status === 'resolved'" @uploaded="refreshPhotos" />
        </div>
        <details class="workflow-disclosure wide" :open="historyOpen" @toggle="historyOpen = ($event.currentTarget as HTMLDetailsElement).open">
          <summary>Xem lịch sử xử lý</summary>
          <div class="workflow-disclosure-body"><MaintenanceHistory v-if="historyOpen" :request-id="report.id" :refresh-key="report.updated_at" audience="tenant" /></div>
        </details>
      </div>
    </template>
  </section>
</template>

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
import { maintenanceStatusLabel, maintenanceStatusTone } from '@/types/maintenance'
import type { MyMaintenance } from '@/types/tenant'
const route = useRoute(), uploaded = ref(false)
// Returning from a file chooser must not unmount the uploader and discard selected files.
const { data: report, loading, error, status, load } = useTenantResource<MyMaintenance>(() => `/maintenance-requests/${route.params.id}`, false)
async function refreshPhotos() { uploaded.value = true; await load() }
void load()
</script>
<template><section class="management-page tenant-page"><RouterLink class="back-link" to="/tenant/maintenance">← Báo hỏng của tôi</RouterLink><UiPageState v-if="loading" kind="loading" /><UiPageState v-else-if="error" :kind="status === 403 || status === 404 ? 'not-found' : 'error'" :title="status === 403 || status === 404 ? 'Yêu cầu không còn khả dụng' : 'Chưa tải được yêu cầu'" :description="status === 403 || status === 404 ? 'Bạn chỉ xem được yêu cầu do chính tài khoản mình gửi.' : error"><UiButton @click="load">Thử lại</UiButton></UiPageState>
  <template v-else-if="report"><header class="management-header"><div><span class="eyebrow">Yêu cầu #{{ report.id }} · Phòng {{ report.room.room_number }}</span><h1>{{ report.title }}</h1><UiBadge :tone="maintenanceStatusTone(report.status)">{{ maintenanceStatusLabel(report.status) }}</UiBadge></div><UiButton variant="secondary" :loading="loading" @click="load">Cập nhật tiến độ</UiButton></header><UiNotice v-if="route.query.created === '1'" tone="success">Yêu cầu đã được gửi đến chủ trọ. Bạn có thể theo dõi tiến độ tại đây.</UiNotice><UiNotice v-if="uploaded" tone="success">Ảnh đã được gửi thành công.</UiNotice>
    <div class="detail-sections"><article class="record-card"><h2>Nội dung bạn đã gửi</h2><p class="tenant-description">{{ report.description }}</p><dl class="record-facts"><div><dt>Phòng khi gửi</dt><dd>{{ report.room.room_number }}</dd></div><div><dt>Nhóm thuê</dt><dd>{{ report.group.group_code }}</dd></div><div><dt>Gửi lúc</dt><dd>{{ formatDateTime(report.submitted_at) }}</dd></div><div><dt>Cập nhật gần nhất</dt><dd>{{ formatDateTime(report.updated_at) }}</dd></div></dl></article><article class="record-card"><h2>Tiến độ và kết quả</h2><p v-if="report.resolution" class="tenant-description">{{ report.resolution }}</p><p v-else class="help-text">Chủ trọ chưa cập nhật nội dung xử lý. Bạn sẽ nhận được thông báo khi có thay đổi.</p><RouterLink class="record-link" to="/notifications">Xem thông báo của bạn →</RouterLink></article><MaintenancePhotos class="wide" :request-id="report.id" :photos="report.photos" audience="tenant" /><TenantPhotoUpload class="wide" :report-id="report.id" :existing="report.photos.length" :resolved="report.status === 'resolved'" @uploaded="refreshPhotos" /></div><UiNotice v-if="report.status === 'resolved'" tone="info">Yêu cầu đã hoàn tất, không bổ sung ảnh hoặc sửa nội dung. Nếu có sự cố mới, bạn có thể gửi yêu cầu khác khi đủ quyền.</UiNotice>
    <MaintenanceHistory :request-id="report.id" audience="tenant" class="tenant-history" />
  </template></section></template>
<style scoped>.tenant-history { margin-top: 24px; }</style>

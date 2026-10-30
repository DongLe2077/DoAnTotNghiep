<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiField from '@/components/ui/UiField.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import MaintenancePhotos from '@/components/maintenance/MaintenancePhotos.vue'
import MaintenanceHistory from '@/components/maintenance/MaintenanceHistory.vue'
import MaintenanceExpenses from '@/components/maintenance/MaintenanceExpenses.vue'
import { useOwnerApi } from '@/composables/useOwnerApi'
import { ApiError, toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { maintenanceStatusLabel, maintenanceStatusTone, type MaintenanceRequest } from '@/types/maintenance'

const route = useRoute(), auth = useAuthStore(), notifications = useNotificationsStore()
const { get, write, busy, error, errors, clearError } = useOwnerApi()
const id = computed(() => Number(route.params.id))
const request = ref<MaintenanceRequest | null>(null), loading = ref(true), loadError = ref(''), loadStatus = ref(0), notice = ref('')
const editing = ref(false), reviewing = ref(false), conflicted = ref(false), clientError = ref('')
const status = ref<'in_progress' | 'resolved'>('in_progress'), resolution = ref(''), version = ref('')
let sequence = 0, alive = true
async function load() {
  const current = ++sequence
  request.value = null; loading.value = true; loadError.value = ''; loadStatus.value = 0
  try {
    if (!Number.isSafeInteger(id.value) || id.value < 1) throw new ApiError(404, 'Không tìm thấy yêu cầu sửa chữa.')
    const result = await get<{ data: MaintenanceRequest }>(`/maintenance-requests/${id.value}`)
    if (alive && current === sequence) request.value = result.data
  } catch (caught) {
    if (alive && current === sequence) { const normalized = toApiError(caught); loadError.value = normalized.message; loadStatus.value = normalized.status }
  } finally { if (alive && current === sequence) loading.value = false }
}
function openEdit() {
  if (!request.value?.can_process) return
  status.value = 'in_progress'; resolution.value = request.value.resolution ?? ''; version.value = request.value.version_token
  editing.value = true; reviewing.value = false; conflicted.value = false; clientError.value = ''; clearError()
}
function closeEdit() { if (!busy.value) { editing.value = false; reviewing.value = false; clearError(); clientError.value = '' } }
function review() {
  clearError(); clientError.value = ''
  resolution.value = resolution.value.trim()
  if (status.value === 'resolved' && !resolution.value) { clientError.value = 'Hãy ghi rõ kết quả trước khi hoàn tất sửa chữa.'; return }
  if (resolution.value.length > 8000) { clientError.value = 'Nội dung xử lý không vượt quá 8.000 ký tự.'; return }
  reviewing.value = true
}
async function save() {
  if (!request.value?.can_process || !reviewing.value || conflicted.value || busy.value) return
  try {
    const result = await write<{ message: string; data: MaintenanceRequest }>('patch', `/maintenance-requests/${id.value}`, { version_token: version.value, status: status.value, resolution: resolution.value || null })
    notice.value = result.message; request.value = result.data; closeEdit()
    void notifications.refreshCount(true)
    if (notifications.items.length) void notifications.refresh()
  } catch (caught) { if (toApiError(caught).status === 409) conflicted.value = true }
}
async function reloadConflict() {
  closeEdit(); notice.value = 'Đã tải lại yêu cầu. Hãy xem cập nhật mới nhất trước khi tiếp tục xử lý.'
  await load()
}
function focusRefresh() { if (!editing.value && !busy.value && !loading.value) void load() }
watch(id, () => { closeEdit(); notice.value = ''; void load() }, { immediate: true })
watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', () => { sequence++; request.value = null; editing.value = false; resolution.value = ''; version.value = ''; notice.value = '' }, { flush: 'sync' })
window.addEventListener('focus', focusRefresh)
onBeforeUnmount(() => { alive = false; sequence++; request.value = null; window.removeEventListener('focus', focusRefresh) })
</script>

<template>
  <section class="management-page">
    <RouterLink class="back-link" to="/owner/maintenance">← Yêu cầu sửa chữa</RouterLink>
    <UiPageState v-if="loading" kind="loading" title="Đang tải yêu cầu sửa chữa…" />
    <UiPageState v-else-if="loadError" :kind="loadStatus === 404 ? 'not-found' : loadStatus === 403 ? 'forbidden' : 'error'" :title="loadStatus === 404 ? 'Không tìm thấy yêu cầu sửa chữa' : undefined" :description="loadError"><UiButton v-if="![403, 404].includes(loadStatus)" @click="load">Thử lại</UiButton></UiPageState>
    <template v-else-if="request">
      <header class="management-header"><div><span class="eyebrow">Yêu cầu #{{ request.id }} · Phòng {{ request.room.room_number }}</span><h1>{{ request.title }}</h1><p>Gửi lúc {{ formatDateTime(request.submitted_at) }}</p><UiBadge :tone="maintenanceStatusTone(request.status)">{{ maintenanceStatusLabel(request.status) }}</UiBadge></div><div class="button-row"><UiButton variant="secondary" :disabled="busy" @click="load">Tải lại yêu cầu</UiButton><UiButton v-if="request.can_process" @click="openEdit">{{ request.status === 'submitted' ? 'Tiếp nhận / xử lý' : 'Cập nhật xử lý' }}</UiButton></div></header>
      <UiNotice v-if="notice" tone="success">{{ notice }}</UiNotice>
      <div class="detail-sections">
        <section class="record-card"><h2>Nội dung báo hỏng</h2><p class="plain-content">{{ request.description }}</p><dl class="record-facts"><div><dt>Người gửi</dt><dd>{{ request.reporter?.full_name ?? 'Tài khoản người thuê' }}</dd></div><div><dt>Điện thoại</dt><dd>{{ request.reporter?.phone_number ?? 'Chưa có thông tin' }}</dd></div><div><dt>Phòng khi gửi</dt><dd>{{ request.room.room_number }}</dd></div><div><dt>Nhóm thuê</dt><dd>{{ request.group.group_code }}</dd></div><div><dt>Cập nhật gần nhất</dt><dd>{{ formatDateTime(request.updated_at) }}</dd></div></dl><RouterLink class="record-link" :to="`/owner/stays/${request.occupancy_id}`">Xem đợt thuê liên quan →</RouterLink></section>
        <section class="record-card"><h2>{{ request.status === 'resolved' ? 'Kết quả sửa chữa' : 'Tiến độ xử lý' }}</h2><p v-if="request.resolution" class="plain-content">{{ request.resolution }}</p><p v-else class="help-text">Chưa có nội dung xử lý. Bạn có thể tiếp nhận và thông báo kế hoạch cho người gửi.</p><p class="help-text">Nội dung cập nhật được gửi đến đúng người báo hỏng qua thông báo trong ứng dụng.</p><p v-if="!request.can_process" class="closed-note">Yêu cầu đã hoàn tất và giữ nguyên kết quả. Nếu sự cố tái phát, người thuê gửi yêu cầu mới.</p><div v-if="request.can_process" class="button-row"><UiButton variant="secondary" @click="openEdit">Ghi tiến độ hoặc hoàn tất</UiButton></div></section>
        <MaintenancePhotos class="wide" :request-id="request.id" :photos="request.photos" />
        <MaintenanceHistory class="wide" :request-id="request.id" :refresh-key="request.version_token" />
        <MaintenanceExpenses class="wide" :request-id="request.id" />
        <section class="record-card wide related-note"><h2>Đối chiếu công việc</h2><p class="help-text">Cập nhật sửa chữa không tự thay đổi trạng thái phòng hoặc ghi chi phí. Các khoản chi thực tế được ghi trong sổ thu chi và liên kết ở phần Chi phí sửa chữa.</p><div class="button-row"><RouterLink class="record-link" to="/owner/rooms">Quản lý phòng →</RouterLink><RouterLink class="record-link" to="/owner/cash">Thu chi →</RouterLink></div></section>
      </div>
      <UiDialog :open="editing" title="Cập nhật sửa chữa" description="Người gửi sẽ thấy trạng thái và nội dung bạn ghi bên dưới." @update:open="value => { if (!value) closeEdit() }">
        <form class="form-grid" @submit.prevent="reviewing ? save() : review()">
          <p v-if="error || clientError" class="form-error" role="alert">{{ error || clientError }}</p>
          <template v-if="conflicted"><UiNotice tone="warning">Yêu cầu đã được cập nhật hoặc có ảnh mới. Hãy tải bản mới nhất để tránh ghi đè thông tin.</UiNotice><UiButton variant="secondary" @click="reloadConflict">Tải bản mới nhất</UiButton></template>
          <fieldset v-if="!reviewing" class="form-grid" :disabled="busy || conflicted">
            <UiField id="maintenance-status" label="Trạng thái xử lý" :error="errors.status?.[0]" required><select id="maintenance-status" v-model="status" class="ui-select"><option value="in_progress">Đang xử lý</option><option value="resolved">Hoàn tất sửa chữa</option></select></UiField>
            <UiField id="maintenance-resolution" :label="status === 'resolved' ? 'Kết quả sửa chữa' : 'Nội dung xử lý'" :required="status === 'resolved'" :error="errors.resolution?.[0]" hint="Tối đa 8.000 ký tự. Nội dung được chia sẻ với người gửi." v-slot="field"><textarea id="maintenance-resolution" v-model="resolution" class="ui-input" rows="5" maxlength="8000" :required="status === 'resolved'" :aria-describedby="field.describedBy" :aria-invalid="field.invalid || undefined" /></UiField>
          </fieldset>
          <div v-else class="review-box"><h3>Xem lại cập nhật sửa chữa</h3><p><strong>{{ request.title }}</strong> · Phòng {{ request.room.room_number }}</p><p>Trạng thái mới: <strong>{{ status === 'resolved' ? 'Đã xử lý' : 'Đang xử lý' }}</strong></p><p class="plain-content">{{ resolution || 'Chưa có nội dung xử lý.' }}</p><p v-if="status === 'resolved'">Sau khi hoàn tất, kết quả sẽ được giữ lại và không thể sửa tiếp.</p><p v-for="(messages, key) in errors" :key="key" class="field-error" role="alert">{{ messages[0] }}</p></div>
          <div v-if="!conflicted" class="button-row"><UiButton v-if="reviewing" variant="secondary" :disabled="busy" @click="reviewing = false; clearError()">Chỉnh lại</UiButton><UiButton type="submit" :loading="busy">{{ reviewing ? 'Xác nhận cập nhật' : 'Xem lại cập nhật' }}</UiButton></div>
        </form>
        <template #actions><UiButton variant="secondary" :disabled="busy" @click="closeEdit">Đóng</UiButton></template>
      </UiDialog>
    </template>
  </section>
</template>

<style scoped>
.plain-content { white-space: pre-wrap; overflow-wrap: anywhere; font-size: 15px; line-height: 1.9; }
.closed-note { border-left: 3px solid var(--color-primary); padding: 12px 16px; margin: 20px 0 0; background: var(--color-primary-soft); font-size: 14px; line-height: 1.8; }
.related-note h2 { font-size: 17px; margin-bottom: 8px; }
.related-note .button-row { gap: 24px; }
</style>

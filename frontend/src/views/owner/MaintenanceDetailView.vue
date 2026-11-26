<script setup lang="ts">
import ScheduledDeleteButton from '@/components/owner/ScheduledDeleteButton.vue'
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
const savedUpdate = ref(false)
const descriptionOpen = ref<boolean | null>(null), historyOpen = ref(false), expensesOpen = ref(false)
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
    notice.value = result.message; request.value = result.data; savedUpdate.value = true; closeEdit()
    void notifications.refreshCount(true)
    if (notifications.items.length) void notifications.refresh()
  } catch (caught) { if (toApiError(caught).status === 409) conflicted.value = true }
}
async function reloadConflict() {
  closeEdit(); notice.value = 'Đã tải lại yêu cầu. Hãy xem cập nhật mới nhất trước khi tiếp tục xử lý.'
  await load()
}
function focusRefresh() { if (!editing.value && !busy.value && !loading.value) void load() }
watch(id, () => { closeEdit(); notice.value = ''; savedUpdate.value = false; void load() }, { immediate: true })
watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', () => { sequence++; request.value = null; editing.value = false; resolution.value = ''; version.value = ''; notice.value = ''; savedUpdate.value = false }, { flush: 'sync' })
window.addEventListener('focus', focusRefresh)
onBeforeUnmount(() => { alive = false; sequence++; request.value = null; window.removeEventListener('focus', focusRefresh) })
</script>

<template>
  <section class="management-page">
    <RouterLink class="back-link" :to="{ path: '/owner/maintenance', state: { savedMaintenanceId: savedUpdate ? id : null } }">← Yêu cầu sửa chữa</RouterLink>
    <UiPageState v-if="loading" kind="loading" title="Đang tải yêu cầu sửa chữa…" />
    <UiPageState v-else-if="loadError" :kind="loadStatus === 404 ? 'not-found' : loadStatus === 403 ? 'forbidden' : 'error'" :title="loadStatus === 404 ? 'Không tìm thấy yêu cầu sửa chữa' : undefined" :description="loadError"><UiButton v-if="![403, 404].includes(loadStatus)" @click="load">Tải lại</UiButton></UiPageState>
    <template v-else-if="request">
      <header class="management-header workflow-header"><div><h1>{{ request.title }}</h1><p>Phòng {{ request.room.room_number }} · Yêu cầu #{{ request.id }}</p></div><ScheduledDeleteButton kind="maintenance" :id="request.id" :label="request.title" button-label="Xóa báo hỏng" /></header>
      <UiNotice v-if="notice" tone="success">{{ notice }}</UiNotice>
      <div class="detail-sections workflow-sections">
        <section class="record-card workflow-summary" :class="{ 'recently-saved': savedUpdate }">
          <header><h2>{{ request.status === 'resolved' ? 'Kết quả sửa chữa' : 'Tiến độ xử lý' }}</h2><UiBadge :tone="maintenanceStatusTone(request.status)">{{ maintenanceStatusLabel(request.status) }}</UiBadge></header>
          <p class="help-text">Cập nhật gần nhất {{ formatDateTime(request.updated_at) }}</p>
          <div v-if="request.resolution" class="workflow-update"><p class="plain-content">{{ request.resolution }}</p></div>
          <div class="button-row workflow-actions">
            <UiButton v-if="request.can_process" @click="openEdit">{{ request.status === 'submitted' ? 'Tiếp nhận / xử lý' : 'Cập nhật xử lý' }}</UiButton>
            <UiButton variant="ghost" :disabled="busy" @click="load">Tải lại yêu cầu</UiButton>
          </div>
        </section>
        <MaintenancePhotos :request-id="request.id" :photos="request.photos" />
        <details class="workflow-disclosure wide" :open="descriptionOpen ?? request.status === 'submitted'" @toggle="descriptionOpen = ($event.currentTarget as HTMLDetailsElement).open">
          <summary>Nội dung báo hỏng và người gửi</summary>
          <div class="workflow-disclosure-body">
            <p class="plain-content">{{ request.description }}</p>
            <dl class="record-facts">
              <div><dt>Người gửi</dt><dd>{{ request.reporter?.full_name ?? 'Tài khoản người thuê' }}</dd></div>
              <div><dt>Điện thoại</dt><dd>{{ request.reporter?.phone_number ?? 'Chưa có thông tin' }}</dd></div>
              <div><dt>Nhóm thuê</dt><dd>{{ request.group.group_code }}</dd></div>
              <div><dt>Gửi lúc</dt><dd>{{ formatDateTime(request.submitted_at) }}</dd></div>
            </dl>
            <RouterLink class="workflow-card-link" :to="`/owner/stays/${request.occupancy_id}`">Xem đợt thuê liên quan →</RouterLink>
          </div>
        </details>
        <details class="workflow-disclosure wide" :open="historyOpen" @toggle="historyOpen = ($event.currentTarget as HTMLDetailsElement).open">
          <summary>Xem lịch sử xử lý</summary>
          <div class="workflow-disclosure-body"><MaintenanceHistory v-if="historyOpen" :request-id="request.id" :refresh-key="request.version_token" /></div>
        </details>
        <MaintenanceExpenses v-model:open="expensesOpen" class="wide" :request-id="request.id" />
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
.workflow-summary h2 { margin-bottom: 0; }
.workflow-disclosure-body > .plain-content:first-child { margin-top: 0; }
</style>

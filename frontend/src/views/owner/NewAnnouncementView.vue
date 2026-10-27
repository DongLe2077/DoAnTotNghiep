<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import RemotePicker from '@/components/owner/RemotePicker.vue'
import { useOwnerApi } from '@/composables/useOwnerApi'
import { useAuthStore } from '@/stores/auth'
import { toApiError } from '@/lib/api'
import { announcementDraft, pendingAnnouncement } from '@/lib/announcements'
import type { Announcement, AnnouncementPayload, AnnouncementPreview } from '@/types/announcements'
const client = useOwnerApi(), auth = useAuthStore(), router = useRouter(), ownerId = auth.user?.id
const key = `rental:pending-announcement:${ownerId}`, pending = ref<AnnouncementPayload | null>(pendingAnnouncement(key))
const title = ref(pending.value?.title ?? ''), body = ref(pending.value?.body ?? ''), audience = ref<'all' | 'rooms'>(pending.value?.audience ?? 'all'), rooms = ref<number[]>(pending.value?.room_ids ?? [])
const preview = ref<AnnouncementPreview | null>(null), confirmed = ref(false), storageError = ref('')
let alive = true, sequence = 0
onBeforeUnmount(() => { alive = false; sequence++ })
watch([title, body, audience, () => JSON.stringify(rooms.value)], () => { sequence++; preview.value = null; confirmed.value = false; client.clearError() })
const canPreview = computed(() => title.value.trim() && body.value.trim() && (audience.value === 'all' || rooms.value.length))
async function review() {
  if (!canPreview.value || pending.value) return
  const current = ++sequence; preview.value = null; confirmed.value = false
  try { const result = await client.write<{ data: AnnouncementPreview }>('post', '/announcements/preview', announcementDraft(title.value, body.value, audience.value, rooms.value)); if (alive && current === sequence) preview.value = result.data } catch { /* Keep the draft for correction. */ }
}
function clearPending() { pending.value = null; try { sessionStorage.removeItem(key) } catch { /* No automatic resend. */ } }
async function send() {
  if (client.busy.value || (!pending.value && (!preview.value?.can_send || !confirmed.value))) return
  if (!pending.value) {
    pending.value = { ...announcementDraft(title.value, body.value, audience.value, rooms.value), preview_token: preview.value!.preview_token, idempotency_key: crypto.randomUUID() }
    try { sessionStorage.setItem(key, JSON.stringify(pending.value)) } catch { storageError.value = 'Chưa lưu được yêu cầu trong trình duyệt. Giữ trang mở nếu mất kết nối.' }
  }
  try {
    const result = await client.write<{ data: Announcement; replayed: boolean }>('post', '/announcements', { ...pending.value })
    clearPending()
    if (alive) await router.push(`/owner/announcements/${result.data.id}?sent=1`)
  } catch (caught) {
    const error = toApiError(caught)
    if ([409, 419, 422].includes(error.status)) { clearPending(); preview.value = null; confirmed.value = false }
    // Unknown/expired-session results retain exactly the reviewed request for manual reconciliation.
  }
}
</script>
<template><section class="management-page"><RouterLink class="back-link" to="/owner/announcements">← Thông báo chung</RouterLink><header class="management-header"><div><span class="eyebrow">Soạn → xem người nhận → gửi</span><h1>Soạn thông báo chung</h1><p>Người nhận là người đang ở có tài khoản hoạt động, gồm cả thành viên không phải đại diện.</p></div></header>
  <UiNotice v-if="pending" tone="warning" title="Lượt gửi đang chờ xác nhận">Yêu cầu có thể đã được ghi nhận. Giữ nguyên nội dung và gửi lại để kiểm tra; hệ thống không tạo thêm thông báo cho lượt gửi này. Sau khi đóng toàn bộ phiên trình duyệt, hãy kiểm tra danh sách đã gửi trước khi tạo lượt mới.</UiNotice>
  <UiNotice v-if="storageError" tone="warning">{{ storageError }}</UiNotice><p v-if="client.error.value" class="form-error" role="alert">{{ client.error.value }}</p>
  <form class="record-card form-grid" @submit.prevent="review"><fieldset class="form-grid" :disabled="client.busy.value || !!pending"><OwnerField v-model="title" label="Tiêu đề thông báo" name="title" required :max-length="160" :errors="client.errors.value" /><label>Nội dung thông báo<textarea v-model="body" class="ui-input announcement-editor" required maxlength="8000" rows="8" placeholder="Ví dụ: lịch kiểm tra hệ thống nước, thời gian thực hiện và hướng dẫn cho người thuê." /><span class="help-text">{{ body.length }}/8000 ký tự · Nội dung hiển thị dưới dạng văn bản.</span><span v-if="client.errors.value.body" class="field-error">{{ client.errors.value.body[0] }}</span></label><label>Gửi đến<select v-model="audience" class="ui-select"><option value="all">Toàn bộ người thuê đang ở</option><option value="rooms">Người đang ở các phòng đã chọn</option></select></label><RemotePicker v-if="audience === 'rooms'" v-model="rooms" multiple label="Phòng nhận thông báo" path="/rooms" label-key="room_number" :error="client.errors.value.room_ids?.[0]" /></fieldset><UiButton v-if="!pending" type="submit" :loading="client.busy.value" :disabled="!canPreview">Xem trước và người nhận</UiButton></form>
  <section v-if="preview || pending" class="record-card dashboard-section"><h2>{{ pending ? 'Nội dung lượt gửi đang chờ' : 'Kiểm tra trước khi gửi' }}</h2><h3>{{ pending?.title ?? preview?.title }}</h3><p class="announcement-body">{{ pending?.body ?? preview?.body }}</p>
    <template v-if="preview && !pending"><p><strong>{{ preview.recipient_count }} người nhận</strong> · {{ preview.audience === 'all' ? 'Toàn bộ người đang ở có tài khoản hoạt động' : `Phòng ${preview.rooms.map(item => item.room_number).join(', ')}` }}</p><UiNotice v-if="!preview.can_send" tone="warning">Chưa có người nhận hợp lệ. Kiểm tra phòng đang thuê và tài khoản người thuê trước khi gửi.</UiNotice><ul v-else class="announcement-recipients detail-items"><li v-for="recipient in preview.recipients" :key="recipient.user_id"><strong>{{ recipient.full_name }}</strong><span class="help-text"> · {{ recipient.username }} · Phòng {{ recipient.room_numbers.join(', ') }}</span></li></ul><p class="help-text">Phòng trống, người chưa có tài khoản hoặc tài khoản đã khóa không nhận thông báo. Danh sách được chốt lúc gửi; người vào ở sau không nhận thông báo cũ.</p><label v-if="preview.can_send" class="announcement-confirm"><input v-model="confirmed" type="checkbox" /> Tôi đã kiểm tra nội dung và người nhận.</label></template>
    <UiButton :loading="client.busy.value" :disabled="!pending && (!preview?.can_send || !confirmed)" @click="send">{{ pending ? 'Gửi lại lượt đang chờ' : 'Xác nhận gửi thông báo' }}</UiButton><p class="help-text">Thông báo đã gửi được giữ nguyên để đối chiếu. Nếu cần sửa nội dung, hãy gửi thông báo đính chính mới.</p>
  </section>
</section></template>
<style scoped>.announcement-editor{resize:vertical;min-height:180px}.announcement-body{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.8}.announcement-recipients{max-height:320px;overflow-y:auto;margin:16px 0}.announcement-confirm{display:flex;gap:12px;margin:20px 0;font-size:14px}.announcement-confirm input{width:20px;height:20px;flex-shrink:0;accent-color:var(--color-primary)}</style>

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, useId } from 'vue'
import { useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiField from '@/components/ui/UiField.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import TenantPhotoPicker from '@/components/maintenance/TenantPhotoPicker.vue'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import { useTenantApi } from '@/composables/useTenantApi'
import { photoFormData, photoSelectionError } from '@/lib/tenant-photos'
import { toApiError } from '@/lib/api'
import type { EligibleStay, MyMaintenance } from '@/types/tenant'
const router = useRouter(), client = useTenantApi(), descriptionId = useId()
const eligible = ref<EligibleStay[]>([]), checking = ref(true), eligibilityError = ref(''), review = ref(false), files = ref<File[]>([])
const form = reactive({ occupancy_id: '', title: '', description: '' })
const frozen = ref<{ occupancy_id: string; title: string; description: string } | null>(null)
const saved = ref<MyMaintenance | null>(null), pendingPhotos = ref<File[] | null>(null), localError = ref('')
const completed = ref(false)
useUnsavedChanges(computed(() => !completed.value && (saved.value ? !!(files.value.length || pendingPhotos.value?.length) : !!(form.title.trim() || form.description.trim() || files.value.length))))
const location = computed(() => eligible.value.find(item => String(item.occupancy_id) === form.occupancy_id))
const messages = computed(() => Object.values(client.errors.value).flat())
let alive = true
onBeforeUnmount(() => { alive = false })
async function loadEligibility() {
  checking.value = true; eligible.value = []; eligibilityError.value = ''
  try { const result = await client.get<{ data: EligibleStay[] }>('/maintenance-requests/eligible-occupancies'); if (alive) { eligible.value = result.data; form.occupancy_id = result.data[0] ? String(result.data[0].occupancy_id) : '' } }
  catch (caught) { if (alive) eligibilityError.value = toApiError(caught).message }
  finally { if (alive) checking.value = false }
}
function prepare() {
  localError.value = photoSelectionError(files.value)
  if (!location.value || !form.title.trim() || !form.description.trim()) localError.value ||= 'Chọn phòng, nhập tiêu đề và mô tả sự cố.'
  if (!localError.value) { client.clearError(); review.value = true }
}
async function upload() {
  if (!saved.value) return
  if (!files.value.length && !pendingPhotos.value) { completed.value = true; await router.push(`/tenant/maintenance/${saved.value.id}?created=1`); return }
  pendingPhotos.value ??= [...files.value]
  try { await client.post(`/maintenance-requests/${saved.value.id}/photos`, photoFormData(pendingPhotos.value)); files.value = []; pendingPhotos.value = null; completed.value = true; await router.push(`/tenant/maintenance/${saved.value.id}?created=1`) }
  catch (caught) { if (toApiError(caught).status === 422) pendingPhotos.value = null }
}
async function send() {
  if (client.busy.value || saved.value) return
  frozen.value ??= { occupancy_id: form.occupancy_id, title: form.title.trim(), description: form.description.trim() }
  try {
    const intent = frozen.value
    const result = await client.post<{ data: MyMaintenance; replayed: boolean }>(`/occupancies/${intent.occupancy_id}/maintenance-requests`, { title: intent.title, description: intent.description })
    saved.value = result.data
    await upload()
  } catch (caught) { if (toApiError(caught).status === 422) { frozen.value = null; review.value = false } }
}
void loadEligibility()
</script>
<template><section class="management-page tenant-page tenant-form"><RouterLink class="back-link" to="/tenant/maintenance">← Báo hỏng của tôi</RouterLink><header class="management-header"><div><span class="eyebrow">Mô tả rõ để chủ trọ hỗ trợ nhanh hơn</span><h1>Gửi báo hỏng</h1><p>Yêu cầu được gửi cho chủ trọ; ảnh được lưu riêng tư.</p></div></header>
  <UiPageState v-if="checking" kind="loading" /><UiPageState v-else-if="eligibilityError" kind="error" :description="eligibilityError"><UiButton @click="loadEligibility">Thử lại</UiButton></UiPageState><UiPageState v-else-if="!eligible.length" kind="forbidden" title="Bạn chưa có quyền gửi báo hỏng mới" description="Chỉ đại diện hiện tại đang cư trú được gửi yêu cầu. Bạn vẫn xem được các yêu cầu đã gửi trước đây." />
  <template v-else><p v-if="client.error.value" class="form-error" role="alert">{{ client.error.value }}</p><p v-if="localError" class="form-error" role="alert">{{ localError }}</p><ul v-if="messages.length" class="field-error"><li v-for="message in messages" :key="message">{{ message }}</li></ul>
    <article v-if="saved" class="record-card form-grid"><UiNotice tone="success" title="Báo hỏng đã được gửi">Yêu cầu #{{ saved.id }} đã được ghi nhận. Chỉ cần hoàn tất ảnh; không gửi lại nội dung báo hỏng.</UiNotice><UiNotice v-if="pendingPhotos" tone="warning">Ảnh trước có thể đã được nhận. Gửi lại đúng các ảnh đang chờ để kiểm tra, hệ thống không lưu ảnh trùng.</UiNotice><TenantPhotoPicker v-model="files" :existing="saved.photos.length" :disabled="client.busy.value || !!pendingPhotos" /><div class="button-row"><UiButton :loading="client.busy.value" @click="upload">{{ files.length ? 'Gửi lại ảnh đã chọn' : 'Hoàn tất, xem yêu cầu' }}</UiButton><RouterLink class="record-link" :to="`/tenant/maintenance/${saved.id}?created=1`">Xem yêu cầu đã gửi →</RouterLink></div></article>
    <article v-else-if="review" class="record-card form-grid"><h2>Xem lại yêu cầu</h2><UiNotice v-if="frozen" tone="warning">Yêu cầu trước có thể đã được ghi nhận. Hãy kiểm tra danh sách trước khi gửi lại. Nội dung trùng chỉ được gộp khi yêu cầu cũ chưa hoàn tất.</UiNotice><dl class="record-facts"><div><dt>Phòng</dt><dd>{{ location?.room.room_number }}</dd></div><div><dt>Tiêu đề</dt><dd>{{ form.title }}</dd></div></dl><p class="tenant-description">{{ form.description }}</p><p class="help-text">{{ files.length }} ảnh được gửi sau khi tạo yêu cầu thành công.</p><div class="button-row"><UiButton v-if="!frozen" variant="secondary" @click="review = false">Quay lại sửa</UiButton><UiButton :loading="client.busy.value" @click="send">{{ frozen ? 'Gửi lại yêu cầu đang chờ' : 'Xác nhận gửi báo hỏng' }}</UiButton></div></article>
    <form v-else class="record-card form-grid" @submit.prevent="prepare"><fieldset :disabled="client.busy.value"><label class="select-field">Phòng báo hỏng<select v-model="form.occupancy_id" class="ui-select" required><option v-for="item in eligible" :key="item.occupancy_id" :value="String(item.occupancy_id)">Phòng {{ item.room.room_number }} · Nhóm {{ item.group.group_code }}</option></select></label><OwnerField v-model="form.title" name="title" label="Tiêu đề sự cố" required :max-length="150" :errors="client.errors.value" hint="Ví dụ: Vòi nước bếp bị rò." /><UiField :id="descriptionId" label="Mô tả sự cố" required :error="client.errors.value.description?.[0]" hint="Cho biết vị trí, dấu hiệu và thời điểm phát hiện." v-slot="{ describedBy, invalid }"><textarea :id="descriptionId" v-model="form.description" class="ui-input" rows="5" maxlength="8000" required :aria-describedby="describedBy" :aria-invalid="invalid" /></UiField><TenantPhotoPicker v-model="files" /></fieldset><UiButton type="submit">Xem lại trước khi gửi</UiButton></form>
  </template></section></template>

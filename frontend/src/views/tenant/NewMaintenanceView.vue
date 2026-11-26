<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, useId, watch } from 'vue'
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
const stepTitle = ref<HTMLHeadingElement | null>(null)
watch(review, () => stepTitle.value?.focus(), { flush: 'post' })
useUnsavedChanges(computed(() => !completed.value && (saved.value ? !!(files.value.length || pendingPhotos.value?.length) : !!(form.title.trim() || form.description.trim() || files.value.length))))
const location = computed(() => eligible.value.find(item => String(item.occupancy_id) === form.occupancy_id))
const messages = computed(() => Object.values(client.errors.value).flat())
let alive = true
onBeforeUnmount(() => { alive = false })
async function loadEligibility() {
  checking.value = true; eligible.value = []; eligibilityError.value = ''
  try {
    const { data } = await client.get<{ data: EligibleStay[] }>('/maintenance-requests/eligible-occupancies')
    if (!alive) return
    eligible.value = data
    if (data.length === 1) form.occupancy_id = String(data[0]!.occupancy_id)
    else if (!data.some(item => String(item.occupancy_id) === form.occupancy_id)) form.occupancy_id = ''
  }
  catch (caught) { if (alive) eligibilityError.value = toApiError(caught).message }
  finally { if (alive) checking.value = false }
}
function prepare() {
  localError.value = photoSelectionError(files.value)
  if (!location.value || !form.title.trim() || !form.description.trim()) localError.value ||= 'Chọn phòng, nhập tiêu đề và mô tả sự cố.'
  if (!localError.value) { client.clearError(); review.value = true }
}
async function upload() {
  if (!saved.value || client.busy.value) return
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
<template>
  <section class="management-page tenant-page tenant-workspace tenant-maintenance tenant-form">
    <RouterLink class="back-link" to="/tenant/maintenance">← Báo hỏng của tôi</RouterLink>
    <header class="management-header">
      <div>
        <span class="tenant-kicker">Hỗ trợ nơi ở</span>
        <h1>Gửi báo hỏng</h1>
        <p>Mô tả sự cố để chủ trọ biết cần sửa gì và ở đâu.</p>
      </div>
    </header>
    <UiPageState v-if="checking" kind="loading" skeleton="report-form" title="Đang tải phòng có thể báo hỏng…" />
    <UiPageState v-else-if="eligibilityError" kind="error" :description="eligibilityError">
      <UiButton @click="loadEligibility">Tải lại</UiButton>
    </UiPageState>
    <UiPageState v-else-if="!eligible.length" kind="forbidden" title="Bạn chưa có quyền gửi báo hỏng mới" description="Chỉ đại diện hiện tại đang cư trú được gửi yêu cầu. Bạn vẫn xem được các yêu cầu đã gửi trước đây." />
    <template v-else>
      <ol v-if="!saved" class="workflow-steps" aria-label="Các bước gửi báo hỏng">
        <li :aria-current="!review ? 'step' : undefined">1. Mô tả sự cố</li>
        <li :aria-current="review ? 'step' : undefined">2. Xem lại và gửi</li>
      </ol>
      <h2 v-if="!saved" ref="stepTitle" class="workflow-form-title tenant-maintenance-heading" tabindex="-1">{{ review ? 'Xem lại yêu cầu' : 'Thông tin sự cố' }}</h2>
      <p v-if="client.error.value" class="form-error" role="alert">{{ client.error.value }}</p>
      <p v-if="localError" class="form-error" role="alert">{{ localError }}</p>
      <ul v-if="messages.length" class="field-error">
        <li v-for="message in messages" :key="message">{{ message }}</li>
      </ul>
      <article v-if="saved" class="record-card form-grid tenant-report-form">
        <UiNotice tone="success" title="Báo hỏng đã được gửi">{{ client.busy.value ? 'Đang gửi ảnh đính kèm…' : `Chủ trọ có thể xem yêu cầu #${saved.id}. Bạn có thể hoàn tất phần ảnh bên dưới.` }}</UiNotice>
        <UiNotice v-if="pendingPhotos && !client.busy.value" tone="warning">Chưa xác nhận được lần gửi ảnh trước. Gửi lại các ảnh đang chờ để hệ thống kiểm tra và tránh lưu trùng.</UiNotice>
        <TenantPhotoPicker v-model="files" class="tenant-report-photo-field" :existing="saved.photos.length" :disabled="client.busy.value || !!pendingPhotos" />
        <div class="button-row tenant-report-actions">
          <UiButton :loading="client.busy.value" @click="upload">{{ client.busy.value ? 'Đang gửi ảnh…' : files.length ? 'Gửi lại ảnh đã chọn' : 'Hoàn tất, xem yêu cầu' }}</UiButton>
          <RouterLink v-if="files.length && !client.busy.value" class="record-link" :to="`/tenant/maintenance/${saved.id}?created=1`">Xem yêu cầu đã gửi →</RouterLink>
        </div>
      </article>
      <article v-else-if="review" class="record-card form-grid tenant-report-form">
        <UiNotice v-if="frozen && !client.busy.value" tone="warning">Yêu cầu trước có thể đã được ghi nhận. Hãy kiểm tra danh sách trước khi gửi lại. Nội dung trùng chỉ được gộp khi yêu cầu cũ chưa hoàn tất.</UiNotice>
        <dl class="record-facts tenant-report-room">
          <div><dt>Phòng</dt><dd>{{ location?.room.room_number }}</dd></div>
          <div><dt>Tiêu đề</dt><dd>{{ form.title }}</dd></div>
        </dl>
        <div>
          <h3>Mô tả sự cố</h3>
          <p class="tenant-description">{{ form.description }}</p>
        </div>
        <div class="tenant-report-photo-field">
          <h3>{{ files.length ? `${files.length} ảnh đính kèm` : 'Không đính kèm ảnh' }}</h3>
          <ul v-if="files.length" class="tenant-report-file-list">
            <li v-for="(file, index) in files" :key="index">{{ file.name }}</li>
          </ul>
        </div>
        <div class="button-row tenant-report-actions">
          <UiButton v-if="!frozen" variant="secondary" @click="review = false">Quay lại sửa</UiButton>
          <UiButton :loading="client.busy.value" @click="send">{{ client.busy.value ? 'Đang gửi báo hỏng…' : frozen ? 'Gửi lại yêu cầu đang chờ' : 'Xác nhận gửi báo hỏng' }}</UiButton>
        </div>
      </article>
      <form v-else class="record-card form-grid tenant-report-form" @submit.prevent="prepare">
        <fieldset :disabled="client.busy.value">
          <dl v-if="eligible.length === 1 && location" class="record-facts tenant-report-room">
            <div><dt>Phòng báo hỏng</dt><dd><strong>Phòng {{ location.room.room_number }}</strong></dd></div>
          </dl>
          <label v-else class="select-field" for="report-room">
            <span>Phòng báo hỏng</span>
            <select id="report-room" v-model="form.occupancy_id" class="ui-select" required>
              <option value="" disabled>Chọn phòng</option>
              <option v-for="item in eligible" :key="item.occupancy_id" :value="String(item.occupancy_id)">Phòng {{ item.room.room_number }} · Nhóm {{ item.group.group_code }}</option>
            </select>
          </label>
          <OwnerField v-model="form.title" name="title" label="Tiêu đề sự cố" required :max-length="150" :errors="client.errors.value" hint="Ví dụ: Vòi nước bếp bị rò." />
          <UiField :id="descriptionId" label="Mô tả sự cố" required :error="client.errors.value.description?.[0]" hint="Cho biết vị trí và tình trạng sự cố." v-slot="{ describedBy, invalid }">
            <textarea :id="descriptionId" v-model="form.description" class="ui-input" rows="4" maxlength="8000" required :aria-describedby="describedBy" :aria-invalid="invalid" />
          </UiField>
          <TenantPhotoPicker v-model="files" class="tenant-report-photo-field" />
        </fieldset>
        <div class="button-row tenant-report-actions">
          <UiButton type="submit">Xem lại trước khi gửi</UiButton>
        </div>
      </form>
    </template>
  </section>
</template>

<style scoped>
@media (max-width: 600px) {
  .tenant-report-actions {
    position: sticky;
    bottom: calc(70px + env(safe-area-inset-bottom, 0px));
    z-index: 1;
    margin: 0 -16px -16px;
    padding: 10px 16px;
    border-top: 1px solid var(--color-border);
    border-radius: 0 0 var(--radius-card) var(--radius-card);
    background: var(--color-surface);
  }
  :global(.keyboard-open) .tenant-report-actions { position: static; }
}
</style>

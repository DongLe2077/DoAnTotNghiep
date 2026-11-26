<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import ScheduledDeletionPurgeDialog from '@/components/owner/ScheduledDeletionPurgeDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import { useOwnerApi } from '@/composables/useOwnerApi'
import { toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'

type Impact = { label: string; count: number }[]
type Preview = { label: string; impact: Impact; confirmation: string; archives_monthly_totals?: boolean }
type Pending = { id: number; purge_at: string; problem: string | null; cleaning_photos: boolean }
const props = withDefaults(defineProps<{
  kind: string; id: number; label: string; buttonLabel?: string; variant?: 'ghost' | 'danger' | 'secondary'
}>(), { buttonLabel: 'Xóa', variant: 'danger' })
const client = useOwnerApi()
const router = useRouter()
const purgeJobId = ref<number | null>(null)
const pending = ref<Pending | null>(null)
const preview = ref<Preview | null>(null)
const dialog = ref(false)
const loading = ref(false)
const error = ref('')
let revision = 0
const target = () => `/scheduled-deletions/${props.kind}/${props.id}`

async function fetchPending(path: string) {
  const current = ++revision
  dialog.value = false
  purgeJobId.value = null
  pending.value = null
  preview.value = null
  try {
    const result = await client.get<{ data: Pending | null }>(path)
    if (current === revision) pending.value = result.data
  } catch { /* Opening the confirmation retries the lookup. */ }
}
watch(target, fetchPending, { immediate: true })
function syncPending(event: Event) {
  if (event instanceof CustomEvent && event.detail === target() && !dialog.value) void fetchPending(target())
}
onMounted(() => window.addEventListener('scheduled-deletion-change', syncPending))
onBeforeUnmount(() => window.removeEventListener('scheduled-deletion-change', syncPending))

async function open() {
  const current = ++revision
  dialog.value = true
  loading.value = true
  preview.value = null
  error.value = ''
  try {
    const result = await client.get<{ data: Pending | null }>(target())
    if (current !== revision) return
    pending.value = result.data
    if (result.data) return
    const check = await client.get<{ data: Preview }>(target() + '/preview')
    if (current === revision) preview.value = check.data
  } catch (caught) {
    if (current === revision) error.value = toApiError(caught).message
  } finally {
    if (current === revision) loading.value = false
  }
}

function close() {
  if (client.busy.value) return
  revision++
  dialog.value = false
}

async function schedule() {
  if (!preview.value || client.busy.value) return
  error.value = ''
  try {
    const result = await client.write<{ data: Pending }>('post', target(), { confirmation: preview.value.confirmation })
    pending.value = result.data
    preview.value = null
    window.dispatchEvent(new CustomEvent('scheduled-deletion-change', { detail: target() }))
  } catch (caught) {
    error.value = toApiError(caught).message
    if (toApiError(caught).status === 409) preview.value = null
  }
}

function openPurge() {
  if (!pending.value || pending.value.cleaning_photos || pending.value.problem || client.busy.value) return
  close()
  purgeJobId.value = pending.value.id
}

function onPurged() {
  purgeJobId.value = null
  pending.value = null
  void router.replace({ path: '/owner/pending-deletions', state: { deletionConfirmed: true } })
}

async function cancel() {
  if (!pending.value || client.busy.value) return
  error.value = ''
  try {
    await client.write('delete', '/scheduled-deletions/' + pending.value.id)
    pending.value = null
    close()
    window.dispatchEvent(new CustomEvent('scheduled-deletion-change', { detail: target() }))
  } catch (caught) { error.value = toApiError(caught).message }
}
</script>

<template>
  <UiButton
    :variant="pending ? 'secondary' : variant"
    :aria-label="pending ? `Chờ xóa ${label}` : `${buttonLabel} ${label}`"
    @click="open"
  >{{ pending ? 'Chờ xóa' : buttonLabel }}</UiButton>
  <Teleport to="body">
  <UiDialog :open="dialog" class="rb7-theme" :title="pending ? 'Lịch xóa dữ liệu' : 'Xác nhận lên lịch xóa'" @update:open="value => { if (!value && dialog) close() }">
    <p><strong>{{ label }}</strong></p>
    <p v-if="loading" role="status">Đang kiểm tra dữ liệu liên quan…</p>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <template v-if="!loading && pending">
      <p v-if="pending.cleaning_photos">Dữ liệu đã được xóa. Hệ thống đang dọn ảnh.</p>
      <p v-else-if="pending.problem">{{ pending.problem }}</p>
      <p v-else>Chờ xóa đến {{ formatDateTime(pending.purge_at) }}. Bạn có thể hủy xóa để giữ lại dữ liệu.</p>
    </template>
    <template v-else-if="!loading && preview">
      <p>Các mục dưới đây sẽ bị xóa vĩnh viễn sau 3 ngày. Bạn có thể hủy xóa trong thời gian chờ.</p>
      <p v-if="preview.archives_monthly_totals" class="help-text">Hệ thống giữ số liệu tổng hợp theo tháng để đối chiếu sau khi xóa.</p>
      <ul><li v-for="item in preview.impact" :key="item.label">{{ item.label }} · {{ item.count }}</li></ul>
      <p v-if="preview.impact.some(item => item.label === 'Phiếu thu')" class="help-text">Xóa phiếu thu trong RentBase không hoàn tiền đã chuyển qua ngân hàng.</p>
    </template>
    <template #actions>
      <RouterLink class="record-link" to="/owner/pending-deletions" @click="close">Danh sách chờ xóa</RouterLink>
      <UiButton variant="secondary" :disabled="client.busy.value" @click="close">Đóng</UiButton>
      <UiButton v-if="!loading && pending && !pending.cleaning_photos" :loading="client.busy.value" @click="cancel">Hủy xóa</UiButton>
      <UiButton v-if="!loading && pending && !pending.cleaning_photos && !pending.problem" variant="danger" :disabled="client.busy.value" @click="openPurge">Xóa vĩnh viễn ngay</UiButton>
      <UiButton v-if="!loading && !pending && preview" variant="danger" :loading="client.busy.value" @click="schedule">Xác nhận xóa sau 3 ngày</UiButton>
    </template>
  </UiDialog>
  </Teleport>
  <ScheduledDeletionPurgeDialog :job-id="purgeJobId" @close="purgeJobId = null" @deleted="onPurged" />
</template>

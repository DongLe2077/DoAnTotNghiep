<script setup lang="ts">
import { ref, watch } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import { useOwnerApi } from '@/composables/useOwnerApi'
import { toApiError } from '@/lib/api'

type Preview = {
  label: string
  impact: { label: string; count: number }[]
  confirmation: string
  archives_monthly_totals: boolean
}

const props = defineProps<{ jobId: number | null }>()
const emit = defineEmits<{ close: []; deleted: [label: string] }>()
const { get, write, busy } = useOwnerApi()
const preview = ref<Preview | null>(null)
const loading = ref(false)
const error = ref('')
let revision = 0
const path = (jobId: number) => '/scheduled-deletions/' + jobId + '/purge'

async function load(jobId: number | null) {
  const current = ++revision
  preview.value = null
  error.value = ''
  loading.value = jobId !== null
  if (jobId === null) return
  try {
    const result = await get<{ data: Preview }>(path(jobId) + '/preview')
    if (current === revision) preview.value = result.data
  } catch (caught) {
    if (current === revision) error.value = toApiError(caught).message
  } finally {
    if (current === revision) loading.value = false
  }
}

watch(() => props.jobId, load, { immediate: true })

function close() {
  if (!busy.value) emit('close')
}

async function purge() {
  const jobId = props.jobId
  const confirmation = preview.value
  if (jobId === null || !confirmation || busy.value) return
  const current = revision
  error.value = ''
  try {
    await write('post', path(jobId), { confirmation: confirmation.confirmation })
    if (current === revision) emit('deleted', confirmation.label)
  } catch (caught) {
    if (current !== revision) return
    const failure = toApiError(caught)
    error.value = failure.message
    if (failure.status === 404 || failure.status === 409) preview.value = null
  }
}
</script>

<template>
  <Teleport to="body">
    <UiDialog
      :open="jobId !== null"
      class="rb7-theme"
      title="Xóa vĩnh viễn ngay"
      @update:open="value => { if (!value) close() }"
    >
      <p v-if="loading" role="status">Đang kiểm tra phạm vi xóa…</p>
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      <template v-if="preview">
        <p><strong>{{ preview.label }}</strong></p>
        <p>Các mục dưới đây sẽ bị xóa ngay. Sau khi xác nhận, bạn không thể khôi phục dữ liệu.</p>
        <ul>
          <li v-for="item in preview.impact" :key="item.label">{{ item.label }} · {{ item.count }}</li>
        </ul>
        <p v-if="preview.archives_monthly_totals" class="help-text">
          Hệ thống giữ số liệu tổng hợp theo tháng để đối chiếu sau khi xóa.
        </p>
        <p v-if="preview.impact.some(item => item.label === 'Phiếu thu')" class="help-text">
          Xóa phiếu thu trong RentBase không hoàn tiền đã chuyển qua ngân hàng.
        </p>
      </template>
      <template #actions>
        <UiButton variant="secondary" :disabled="busy" @click="close">Quay lại</UiButton>
        <UiButton v-if="preview" variant="danger" :loading="busy" @click="purge">Xác nhận xóa vĩnh viễn</UiButton>
        <UiButton v-else-if="error" variant="secondary" @click="load(jobId)">Kiểm tra lại</UiButton>
      </template>
    </UiDialog>
  </Teleport>
</template>

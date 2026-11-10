<script setup lang="ts">
import { computed, ref } from 'vue'
import TenantPhotoPicker from './TenantPhotoPicker.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import { useTenantApi } from '@/composables/useTenantApi'
import { photoFormData } from '@/lib/tenant-photos'
import { toApiError } from '@/lib/api'
import type { MaintenancePhoto } from '@/types/maintenance'
const props = defineProps<{ reportId: number; existing: number; resolved: boolean }>()
const emit = defineEmits<{ uploaded: [] }>()
const files = ref<File[]>([]), pending = ref<File[] | null>(null)
const client = useTenantApi()
const messages = computed(() => Object.values(client.errors.value).flat())
async function send() {
  if (client.busy.value || props.resolved || !files.value.length) return
  pending.value ??= [...files.value]
  try { await client.post<{ data: MaintenancePhoto[] }>(`/maintenance-requests/${props.reportId}/photos`, photoFormData(pending.value)); pending.value = null; files.value = []; emit('uploaded') }
  catch (caught) { if (toApiError(caught).status === 422) pending.value = null }
}
</script>
<template>
  <details v-if="!resolved && (existing < 5 || pending)" class="workflow-disclosure tenant-photo-upload">
    <summary>Bổ sung ảnh sự cố<span v-if="pending && !client.busy.value"> · Ảnh đang chờ</span></summary>
    <div class="workflow-disclosure-body">
      <p v-if="client.error.value" class="form-error" role="alert">{{ client.error.value }}</p>
      <ul v-if="messages.length" class="field-error"><li v-for="message in messages" :key="message">{{ message }}</li></ul>
      <UiNotice v-if="pending && !client.busy.value" tone="warning">Chưa xác nhận được lần gửi trước. Gửi lại đúng các ảnh đang chờ để tránh ảnh trùng.</UiNotice>
      <TenantPhotoPicker v-model="files" :existing="existing" :disabled="client.busy.value || !!pending" />
      <div class="button-row workflow-actions"><UiButton :loading="client.busy.value" :disabled="!files.length" @click="send">{{ pending ? 'Gửi lại các ảnh đang chờ' : 'Gửi ảnh đã chọn' }}</UiButton></div>
    </div>
  </details>
</template>

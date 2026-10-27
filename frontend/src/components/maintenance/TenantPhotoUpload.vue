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
const files = ref<File[]>([]), pending = ref<File[] | null>(null), success = ref(false)
const client = useTenantApi()
const messages = computed(() => Object.values(client.errors.value).flat())
async function send() {
  if (client.busy.value || props.resolved || !files.value.length) return
  pending.value ??= [...files.value]; success.value = false
  try { await client.post<{ data: MaintenancePhoto[] }>(`/maintenance-requests/${props.reportId}/photos`, photoFormData(pending.value)); pending.value = null; files.value = []; success.value = true; emit('uploaded') }
  catch (caught) { if (toApiError(caught).status === 422) pending.value = null }
}
</script>
<template><section v-if="!resolved && (existing < 5 || pending)" class="record-card tenant-photo-upload"><h2>Bổ sung ảnh sự cố</h2><UiNotice v-if="success" tone="success">Đã gửi ảnh. Danh sách ảnh đã được cập nhật.</UiNotice><p v-if="client.error.value" class="form-error" role="alert">{{ client.error.value }}</p><ul v-if="messages.length" class="field-error"><li v-for="message in messages" :key="message">{{ message }}</li></ul><UiNotice v-if="pending && !client.busy.value" tone="warning">Kết quả gửi ảnh trước chưa được xác định. Bấm gửi lại các ảnh đang chờ; hệ thống nhận diện ảnh trùng để không lưu thêm bản sao.</UiNotice><TenantPhotoPicker v-model="files" :existing="existing" :disabled="client.busy.value || !!pending" /><div class="button-row" style="margin-top: 16px"><UiButton :loading="client.busy.value" :disabled="!files.length" @click="send">{{ pending ? 'Gửi lại các ảnh đang chờ' : `Gửi ${files.length || ''} ảnh đã chọn` }}</UiButton></div><p class="help-text">Chỉ bổ sung ảnh cho yêu cầu này, không tạo lại báo hỏng.</p></section></template>

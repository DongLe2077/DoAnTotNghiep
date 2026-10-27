<script setup lang="ts">
import { reactive, watch } from 'vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import OwnerField from './OwnerField.vue'
import { useOwnerApi } from '@/composables/useOwnerApi'
import type { Tenant } from '@/types/owner'
const props = defineProps<{ open: boolean; tenant?: Tenant | null }>()
const emit = defineEmits<{ close: []; saved: [tenant: Tenant] }>()
const { write, busy, error, errors, clearError } = useOwnerApi()
const form = reactive({ full_name: '', phone_number: '', identity_number: '' })
watch(() => props.open, open => { if (open) { clearError(); Object.assign(form, { full_name: props.tenant?.full_name ?? '', phone_number: props.tenant?.phone_number ?? '', identity_number: props.tenant?.identity_number ?? '' }) } })
function close() { if (!busy.value) { form.identity_number = ''; emit('close') } }
async function save() {
  try {
    const result = await write<{ data: Tenant }>(props.tenant ? 'put' : 'post', props.tenant ? `/tenants/${props.tenant.id}` : '/tenants', { ...form, identity_number: form.identity_number || null })
    emit('saved', result.data); close()
  } catch { /* Preserve input for correction, with API field errors. */ }
}
</script>
<template><UiDialog :open="open" :title="tenant ? 'Sửa hồ sơ người thuê' : 'Thêm người thuê'" @update:open="value => { if (!value) close() }"><form class="form-grid" @submit.prevent="save"><p v-if="error" class="form-error" role="alert">{{ error }}</p><fieldset :disabled="busy" class="form-grid"><OwnerField v-model="form.full_name" label="Họ và tên" name="full_name" :errors="errors" :max-length="150" required /><OwnerField v-model="form.phone_number" label="Số điện thoại" name="phone_number" :errors="errors" type="tel" :max-length="20" required /><OwnerField v-model="form.identity_number" label="Số CCCD / giấy tờ tùy thân" name="identity_number" :errors="errors" :max-length="30" hint="Không bắt buộc. Thông tin chỉ dùng trong khu vực quản lý." /></fieldset><p class="help-text">Lưu hồ sơ không tự tạo tài khoản đăng nhập. Bạn có thể tạo tài khoản sau trong chi tiết người thuê.</p><UiButton type="submit" :loading="busy">Lưu hồ sơ</UiButton></form><template #actions><UiButton variant="secondary" :disabled="busy" @click="close">Đóng</UiButton></template></UiDialog></template>

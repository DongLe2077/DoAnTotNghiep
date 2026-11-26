<script setup lang="ts">
import { ref } from 'vue'
import { api, prepareCsrf, toApiError } from '@/lib/api'
import UiButton from '@/components/ui/UiButton.vue'
import type { MembershipAccount } from '@/types/property-onboarding'
const props = defineProps<{ tenantId: number }>()
const emit = defineEmits<{ linked: [] }>()
const members = ref<MembershipAccount[]>([])
const selected = ref<number | ''>('')
const open = ref(false)
const busy = ref(false)
const error = ref('')
async function show() {
  busy.value = true; error.value = ''
  try {
    const response = await api.get<{ data: typeof members.value }>('/properties/members')
    members.value = response.data.data.filter(member => member.tenant_id === null)
    selected.value = ''
    open.value = true
  } catch (e) { error.value = toApiError(e).message }
  finally { busy.value = false }
}
async function link() {
  if (!selected.value || busy.value) return
  busy.value = true; error.value = ''
  try {
    await prepareCsrf()
    await api.put('/properties/tenant-link', { user_id: Number(selected.value), tenant_id: props.tenantId })
    open.value = false; emit('linked')
  } catch (e) { error.value = toApiError(e).message }
  finally { busy.value = false }
}
</script>
<template>
  <div>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <UiButton v-if="!open" variant="secondary" :loading="busy" @click="show">Liên kết thành viên đã duyệt</UiButton>
    <form v-else class="form-grid" @submit.prevent="link">
      <p class="help-text">Đối chiếu họ tên và tên đăng nhập trực tiếp với người thuê. Số điện thoại do người dùng tự nhập.</p>
      <label>Thành viên<select v-model="selected" class="ui-select" :disabled="busy" required><option value="">Chọn tài khoản</option><option v-for="member in members" :key="member.id" :value="member.id">{{ member.full_name ? member.full_name + ' · ' : '' }}{{ member.username }}{{ member.contact_phone ? ' · ' + member.contact_phone : '' }}</option></select></label>
      <p v-if="!members.length" class="help-text">Chưa có thành viên đã duyệt chưa liên kết hồ sơ.</p>
      <div class="button-row"><UiButton type="submit" :loading="busy" :disabled="!selected">Xác nhận liên kết</UiButton><UiButton variant="secondary" :disabled="busy" @click="open = false">Hủy</UiButton></div>
    </form>
  </div>
</template>

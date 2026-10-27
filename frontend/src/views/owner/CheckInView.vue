<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import RemotePicker from '@/components/owner/RemotePicker.vue'
import { eventPayload, localEventTime, useOwnerApi } from '@/composables/useOwnerApi'
import { formatMoney } from '@/lib/format'
import { formatDateTime } from '@/lib/notifications'
import type { Group, Occupancy, Tenant } from '@/types/owner'
const router = useRouter(), { write, busy, error, errors, clearError } = useOwnerApi()
const step = ref(1), roomIds = ref<number[]>([]), groupIds = ref<number[]>([]), tenantIds = ref<number[]>([]), representative = ref(''), startedAt = ref(localEventTime())
const room = ref<Record<string, unknown> | null>(null), group = ref<Record<string, unknown> | null>(null), selectedTenants = reactive(new Map<number, Tenant>())
const groupOpen = ref(false), groupCode = ref(''), groupVersion = ref(0), clientError = ref('')
const members = computed(() => tenantIds.value.map(id => selectedTenants.get(id)).filter((value): value is Tenant => !!value))
const representatives = computed(() => members.value.filter(tenant => tenant.account?.is_active))
function chooseTenant(value: Record<string, unknown>) { selectedTenants.set(Number(value.id), value as unknown as Tenant) }
function next() {
  clientError.value = ''; clearError()
  if (step.value === 1 && (!roomIds.value[0] || !groupIds.value[0])) { clientError.value = 'Hãy chọn phòng và nhóm thuê trước khi tiếp tục.'; return }
  if (step.value === 2 && !tenantIds.value.length) { clientError.value = 'Hãy chọn ít nhất một người thuê.'; return }
  if (step.value === 2 && tenantIds.value.length > Number(room.value?.capacity ?? 0)) { clientError.value = 'Số thành viên vượt sức chứa phòng đã chọn.'; return }
  if (representative.value && !representatives.value.some(item => item.id === Number(representative.value))) representative.value = ''
  step.value++
}
async function createGroup() {
  try { const result = await write<{ data: Group }>('post', '/rental-groups', { group_code: groupCode.value }); groupIds.value = [result.data.id]; group.value = { ...result.data }; groupOpen.value = false; groupVersion.value++; groupCode.value = '' } catch { /* Server errors remain visible. */ }
}
async function submit() {
  try {
    const result = await write<{ data: Occupancy }>('post', '/occupancies', { room_id: roomIds.value[0], rental_group_id: groupIds.value[0], tenant_ids: [...tenantIds.value], started_at: eventPayload(startedAt.value), ...(representative.value ? { representative_tenant_id: Number(representative.value) } : {}) })
    await router.replace({ path: `/owner/stays/${result.data.id}`, query: { received: '1' } })
  } catch { /* No automatic retry. The review stays open. */ }
}
</script>
<template><section class="management-page"><RouterLink class="back-link" to="/owner/stays">← Quá trình thuê</RouterLink><header class="management-header"><div><span class="eyebrow">Bước {{ step }} / 3</span><h1>Nhận phòng</h1><p>{{ step === 1 ? 'Chọn nơi ở và nhóm thuê.' : step === 2 ? 'Chọn thành viên và người đại diện khi đã có tài khoản.' : 'Kiểm tra thông tin trước khi xác nhận.' }}</p></div></header><form class="record-card form-grid" @submit.prevent="step < 3 ? next() : submit()"><p v-if="error || clientError" class="form-error" role="alert">{{ error || clientError }}</p><fieldset :disabled="busy" class="form-grid"><template v-if="step === 1"><RemotePicker v-model="roomIds" label="Phòng trống" path="/rooms" label-key="room_number" :filters="{ operational_status: 'available', occupancy_status: 'vacant' }" :error="errors.room_id?.[0]" @choose="room = $event" /><p class="help-text">Phòng trống vẫn cần được kiểm tra chưa có đợt thuê đang mở khi lưu.</p><RemotePicker :key="groupVersion" v-model="groupIds" label="Nhóm thuê" path="/rental-groups" label-key="group_code" :initial="group && groupIds.length ? [{ id: Number(group.id), label: String(group.group_code) }] : []" :error="errors.rental_group_id?.[0]" @choose="group = $event" /><UiButton variant="secondary" @click="clearError(); groupOpen = true">Tạo nhóm mới</UiButton><OwnerField v-model="startedAt" label="Thời điểm nhận phòng (giờ Việt Nam)" name="started_at" :errors="errors" type="datetime-local" step="1" :max="localEventTime()" required /></template><template v-else-if="step === 2"><RemotePicker v-model="tenantIds" label="Thành viên nhận phòng" path="/tenants" label-key="full_name" :filters="{ is_residing: 0 }" multiple :error="errors.tenant_ids?.[0]" @choose="chooseTenant" /><p class="help-text">Đã chọn {{ tenantIds.length }} / {{ room?.capacity }} người. Có thể nhận phòng cho một người.</p><label>Người đại diện<select v-model="representative" class="ui-select"><option value="">Chưa chọn — bổ sung sau</option><option v-for="tenant in representatives" :key="tenant.id" :value="String(tenant.id)">{{ tenant.full_name }}</option></select></label><p class="help-text">Đại diện cần có tài khoản đang hoạt động. Hồ sơ chưa có tài khoản vẫn có thể là thành viên.</p></template><template v-else><div class="review-box"><h2>Xem lại nhận phòng</h2><dl class="record-facts"><div><dt>Phòng</dt><dd>{{ room?.room_number }}</dd></div><div><dt>Nhóm</dt><dd>{{ group?.group_code }}</dd></div><div><dt>Giá toàn phòng / tháng</dt><dd>{{ formatMoney(String(room?.default_monthly_rent ?? '')) }}</dd></div><div><dt>Nhận phòng</dt><dd>{{ formatDateTime(eventPayload(startedAt)) }}</dd></div><div><dt>Thành viên</dt><dd>{{ members.map(item => item.full_name).join(', ') }}</dd></div><div><dt>Đại diện</dt><dd>{{ representatives.find(item => item.id === Number(representative))?.full_name ?? 'Bổ sung sau' }}</dd></div></dl><p>Giá thuê được chốt theo giá phòng hiện tại trên hệ thống tại thời điểm lưu. Sau khi nhận phòng, bạn chọn và áp dụng các dịch vụ trong chi tiết đợt thuê.</p></div><p v-for="(messages, key) in errors" :key="key" class="field-error" role="alert">{{ messages[0] }}</p></template></fieldset><div class="button-row"><UiButton v-if="step > 1" variant="secondary" :disabled="busy" @click="step--; clearError(); clientError = ''">Quay lại</UiButton><UiButton type="submit" :loading="busy">{{ step < 3 ? 'Tiếp tục' : 'Xác nhận nhận phòng' }}</UiButton></div></form><UiDialog :open="groupOpen" title="Tạo nhóm thuê" @update:open="value => { if (!busy) groupOpen = value }"><form class="form-grid" @submit.prevent="createGroup"><p v-if="error" class="form-error" role="alert">{{ error }}</p><OwnerField v-model="groupCode" label="Mã nhóm" name="group_code" :errors="errors" :max-length="40" required hint="Có thể dùng mã dễ nhớ, ví dụ NHOM-AN. Nhóm có thể gồm một người." /><UiButton type="submit" :loading="busy">Lưu nhóm mới</UiButton></form><template #actions><UiButton variant="secondary" :disabled="busy" @click="groupOpen = false; clearError()">Đóng</UiButton></template></UiDialog></section></template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import { useUnsavedChanges } from '@/composables/useUnsavedChanges'
import { useOwnerApi } from '@/composables/useOwnerApi'
import { toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import { useAuthStore } from '@/stores/auth'
import { propertyFields, type PropertyField, type RentalProperty } from '@/types/property'

const client = useOwnerApi(), auth = useAuthStore(), profile = ref<RentalProperty | null>(null)
const labels: Record<PropertyField, string> = { name: 'Tên nhà trọ', address: 'Địa chỉ nhà trọ', contact_name: 'Người liên hệ', phone: 'Điện thoại liên hệ', email: 'Email liên hệ', bank_name: 'Ngân hàng', bank_account_number: 'Số tài khoản', bank_account_name: 'Tên chủ tài khoản', notes: 'Ghi chú hiển thị trên chứng từ' }
const form = ref<Record<PropertyField, string>>({ name: '', address: '', contact_name: '', phone: '', email: '', bank_name: '', bank_account_number: '', bank_account_name: '', notes: '' })
const loading = ref(true), loadError = ref(''), notice = ref(''), reviewing = ref(false), confirmed = ref(false), conflict = ref(false), clientError = ref('')
const baseline = ref('')
useUnsavedChanges(computed(() => !!profile.value && !!baseline.value && JSON.stringify(form.value) !== baseline.value))
let sequence = 0, alive = true
async function load() {
  const current = ++sequence; loading.value = true; loadError.value = ''; profile.value = null; reviewing.value = false; client.clearError(); conflict.value = false; clientError.value = ''
  try { const result = await client.get<{ data: RentalProperty }>('/property'); if (alive && current === sequence) { profile.value = result.data; for (const field of propertyFields) form.value[field] = result.data[field] ?? ''; baseline.value = JSON.stringify(form.value) } }
  catch (caught) { if (alive && current === sequence) loadError.value = toApiError(caught).message }
  finally { if (alive && current === sequence) loading.value = false }
}
function review() {
  client.clearError(); clientError.value = ''; confirmed.value = false
  for (const field of propertyFields) form.value[field] = form.value[field].trim()
  if (!form.value.name || !form.value.address) { clientError.value = 'Nhập tên và địa chỉ nhà trọ trước khi xem lại.'; return }
  const bank = [form.value.bank_name, form.value.bank_account_number, form.value.bank_account_name]
  if (bank.some(Boolean) && !bank.every(Boolean)) { clientError.value = 'Thông tin nhận tiền cần đủ ngân hàng, số tài khoản và tên chủ tài khoản.'; return }
  reviewing.value = true
}
async function save() {
  if (!profile.value || !confirmed.value || client.busy.value || conflict.value) return
  try {
    const values = Object.fromEntries(propertyFields.map(field => [field, form.value[field] || null]))
    const result = await client.write<{ message: string; data: RentalProperty }>('put', '/property', { ...values, version_token: profile.value.version_token })
    profile.value = result.data; for (const field of propertyFields) form.value[field] = result.data[field] ?? ''; baseline.value = JSON.stringify(form.value); notice.value = result.message; reviewing.value = false
  } catch (caught) { if (toApiError(caught).status === 409) conflict.value = true; else { reviewing.value = false; confirmed.value = false } }
}
watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', () => { sequence++; profile.value = null; for (const field of propertyFields) form.value[field] = ''; reviewing.value = false; notice.value = '' }, { flush: 'sync' })
onBeforeUnmount(() => { alive = false; sequence++ })
void load()
</script>

<template>
  <section class="management-page property-page">
    <header class="management-header"><div><span class="eyebrow">Thông tin dùng chung trên chứng từ</span><h1>Thông tin nhà trọ</h1><p>Cấu hình thông tin liên hệ và nhận tiền để hiển thị trên hóa đơn, phiếu thu PDF.</p></div></header>
    <UiNotice v-if="notice" tone="success">{{ notice }}</UiNotice>
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="loadError" kind="error" :description="loadError"><UiButton @click="load">Thử lại</UiButton></UiPageState>
    <template v-else-if="profile">
      <UiNotice v-if="!profile.configured" tone="info">Chưa cấu hình thông tin nhà trọ. Điền thông tin thực tế để các chứng từ xuất tiếp theo đầy đủ hơn.</UiNotice>
      <p class="help-text">Thông tin bên dưới được chia sẻ trên PDF của người có quyền xem. Email này là địa chỉ liên hệ trên chứng từ.</p>
      <form class="property-form" @submit.prevent="review">
        <p v-if="clientError || client.error.value" class="form-error" role="alert">{{ clientError || client.error.value }}</p>
        <fieldset :disabled="client.busy.value || conflict" class="record-card form-grid">
          <h2>Nhà trọ và liên hệ</h2>
          <OwnerField v-model="form.name" :label="labels.name" name="name" required :max-length="150" :errors="client.errors.value" />
          <OwnerField v-model="form.address" :label="labels.address" name="address" required :max-length="500" :errors="client.errors.value" />
          <OwnerField v-model="form.contact_name" :label="labels.contact_name" name="contact_name" :max-length="150" :errors="client.errors.value" />
          <div class="form-grid two-col"><OwnerField v-model="form.phone" :label="labels.phone" name="phone" type="tel" :max-length="30" :errors="client.errors.value" /><OwnerField v-model="form.email" :label="labels.email" name="email" type="email" :max-length="254" :errors="client.errors.value" /></div>
        </fieldset>
        <fieldset :disabled="client.busy.value || conflict" class="record-card form-grid"><h2>Thông tin nhận tiền</h2><p class="help-text">Có thể để trống cả ba mục. Khi nhập, hãy điền đủ và kiểm tra đúng tài khoản trước khi lưu.</p><OwnerField v-model="form.bank_name" :label="labels.bank_name" name="bank_name" :max-length="150" :errors="client.errors.value" /><OwnerField v-model="form.bank_account_number" :label="labels.bank_account_number" name="bank_account_number" :max-length="50" :errors="client.errors.value" /><OwnerField v-model="form.bank_account_name" :label="labels.bank_account_name" name="bank_account_name" :max-length="150" :errors="client.errors.value" /><OwnerField v-model="form.notes" :label="labels.notes" name="notes" :max-length="2000" :errors="client.errors.value" hint="Ví dụ giờ liên hệ hoặc hướng dẫn đối chiếu chuyển khoản." /></fieldset>
        <div class="button-row"><UiButton type="submit" :disabled="client.busy.value || conflict">Xem lại thông tin</UiButton><UiButton variant="secondary" :disabled="client.busy.value" @click="load">Tải thông tin mới nhất</UiButton></div>
        <p v-if="profile.updated_at" class="help-text">Cập nhật {{ formatDateTime(profile.updated_at) }}. Thông tin áp dụng cho các PDF xuất tiếp theo.</p>
      </form>
      <UiDialog :open="reviewing" title="Xem lại thông tin nhà trọ" @update:open="value => { if (!client.busy.value) reviewing = value }">
        <p v-if="client.error.value" class="form-error" role="alert">{{ client.error.value }}</p>
        <template v-if="conflict"><UiNotice tone="warning">Thông tin đã thay đổi ở nơi khác. Hãy tải lại và đối chiếu trước khi lưu.</UiNotice><UiButton @click="load">Tải bản mới để đối chiếu</UiButton></template>
        <template v-else><dl class="record-facts"><div v-for="field in propertyFields" :key="field"><dt>{{ labels[field] }}</dt><dd>{{ form[field] || 'Chưa cung cấp' }}</dd></div></dl><p class="help-text">Người có quyền xem chứng từ sẽ thấy thông tin này. Tệp đã tải trước đó giữ nội dung tại thời điểm xuất.</p><label class="property-confirm"><input v-model="confirmed" type="checkbox" /> Tôi đã kiểm tra thông tin liên hệ và tài khoản nhận tiền.</label></template>
        <template #actions><UiButton variant="secondary" :disabled="client.busy.value" @click="reviewing = false">Chỉnh lại</UiButton><UiButton v-if="!conflict" :disabled="!confirmed" :loading="client.busy.value" @click="save">Xác nhận lưu thông tin</UiButton></template>
      </UiDialog>
    </template>
  </section>
</template>

<style scoped>.property-page { max-width: 1000px; }.property-form { display: grid; gap: 24px; margin-top: 24px; }.property-form fieldset { min-width: 0; }.property-form h2 { font-size: 19px; }.property-confirm { display: flex; gap: 12px; align-items: flex-start; margin-top: 24px; }.property-confirm input { width: 20px; height: 20px; flex-shrink: 0; accent-color: var(--color-primary); }.record-facts dd { overflow-wrap: anywhere; white-space: pre-wrap; }</style>

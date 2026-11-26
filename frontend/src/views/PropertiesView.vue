<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import JoinPropertyPanel from '@/components/tenancy/JoinPropertyPanel.vue'
import UiButton from '@/components/ui/UiButton.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import { api, prepareCsrf, toApiError } from '@/lib/api'
import { selectProperty } from '@/lib/property-selection'
import { useAuthStore } from '@/stores/auth'

type Property = { id: number; name: string; address: string; role: string }

const auth = useAuthStore()
const canCreate = computed(() => auth.user?.can_create_property === true)
const properties = ref<Property[]>([])
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const propertyError = ref('')
const fields = ref<Record<string, string[]>>({})
const form = reactive({ name: '', address: '', contact_name: '', phone: '', email: '' })
const photo = ref<File | null>(null)
const creating = ref(false)

async function load() {
  loading.value = true
  propertyError.value = ''
  try {
    properties.value = (await api.get<{ data: Property[] }>('/properties')).data.data
  } catch (e) {
    propertyError.value = toApiError(e).message
  } finally {
    loading.value = false
  }
}

async function select(property: Property) {
  if (busy.value) return
  busy.value = true
  error.value = ''
  try {
    await prepareCsrf()
    await api.put('/properties/selected', { property_id: property.id })
    selectProperty(property.id)
    window.location.assign(property.role === 'owner' ? '/owner' : '/tenant/workspace')
  } catch (e) {
    error.value = toApiError(e).message
    busy.value = false
  }
}

async function create() {
  if (!canCreate.value || busy.value) return
  if (photo.value && photo.value.size > 3 * 1024 * 1024) {
    fields.value = { photo: ['Chọn ảnh có dung lượng không quá 3 MB.'] }
    return
  }

  busy.value = true
  error.value = ''
  fields.value = {}
  const payload = new FormData()
  for (const [key, value] of Object.entries(form)) {
    if (value.trim()) payload.append(key, value.trim())
  }
  if (photo.value) payload.append('photo', photo.value)

  try {
    await prepareCsrf()
    const property = (await api.post<{ data: Property }>('/properties', payload)).data.data
    Object.assign(form, { name: '', address: '', contact_name: '', phone: '', email: '' })
    photo.value = null
    creating.value = false
    await load()
    if (propertyError.value) properties.value.push(property)
  } catch (e) {
    const failure = toApiError(e)
    error.value = failure.message
    fields.value = failure.errors
  } finally {
    busy.value = false
  }
}

async function logout() {
  try {
    await auth.logout()
    window.location.assign('/login')
  } catch (e) {
    error.value = toApiError(e).message
  }
}

onMounted(load)
</script>

<template>
  <main class="property-page rb7-theme">
    <header class="property-header">
      <img src="/icons/rentbase-mark.svg" width="44" height="44" alt="" />
      <strong>RentBase</strong>
      <UiButton variant="secondary" :disabled="busy" @click="logout">Đăng xuất</UiButton>
    </header>
    <nav class="button-row property-links" v-if="auth.user?.properties" aria-label="Tìm phòng trọ">
      <RouterLink class="record-link" to="/notifications">Thông báo</RouterLink>
      <RouterLink class="record-link" to="/find-rooms">Tìm phòng trọ</RouterLink>
      <RouterLink class="record-link" to="/my-room-requests">Yêu cầu của tôi</RouterLink>
    </nav>
    <h1>Khu trọ của bạn</h1>
    <p class="help-text">{{ canCreate ? 'Chọn khu đã được duyệt, nhập mã mời hoặc tạo khu mới.' : 'Chọn khu đã được duyệt hoặc nhập mã mời của chủ trọ.' }}</p>
    <p v-if="error && !creating" class="form-error" role="alert">{{ error }}</p>
    <p v-if="propertyError" class="form-error" role="alert">{{ propertyError }}</p>
    <p v-else-if="loading && !properties.length" role="status">Đang tải danh sách khu trọ…</p>
    <div v-if="properties.length" class="property-grid">
      <button v-for="property in properties" :key="property.id" type="button" class="record-card property-card" :disabled="busy" @click="select(property)">
        <span class="help-text">{{ property.role === 'owner' ? 'Bạn là chủ trọ' : 'Bạn là thành viên' }}</span>
        <strong>{{ property.name }}</strong>
        <span>{{ property.address }}</span>
        <span class="record-link property-action">Vào khu trọ →</span>
      </button>
    </div>

    <section v-if="canCreate" class="record-card form-grid">
      <div><h2>Tôi quản lý nhà trọ</h2><p class="help-text">Tạo khu của bạn để thêm phòng và mời người thuê.</p></div>
      <UiButton v-if="!creating" :disabled="busy" @click="creating = true">Tạo khu trọ</UiButton>
      <form v-else class="form-grid" @submit.prevent="create">
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
        <fieldset class="form-grid" :disabled="busy">
          <OwnerField v-model="form.name" label="Tên khu trọ" name="name" required :max-length="150" :errors="fields" />
          <OwnerField v-model="form.address" label="Địa chỉ" name="address" required :max-length="500" :errors="fields" />
          <OwnerField v-model="form.contact_name" label="Người liên hệ (không bắt buộc)" name="contact_name" :max-length="150" :errors="fields" />
          <OwnerField v-model="form.phone" label="Điện thoại liên hệ (không bắt buộc)" name="phone" type="tel" :max-length="30" :errors="fields" />
          <OwnerField v-model="form.email" label="Email liên hệ (không bắt buộc)" name="email" type="email" :max-length="254" :errors="fields" />
          <label>Ảnh khu trọ (không bắt buộc)
            <input type="file" class="ui-input" accept="image/jpeg,image/png,image/webp" @change="photo = ($event.target as HTMLInputElement).files?.[0] ?? null" />
            <span class="help-text">JPG, PNG hoặc WebP, tối đa 3 MB.</span>
            <span v-if="fields.photo" class="field-error">{{ fields.photo[0] }}</span>
          </label>
        </fieldset>
        <div class="button-row">
          <UiButton type="submit" :loading="busy">Lưu khu trọ</UiButton>
          <UiButton variant="secondary" :disabled="busy" @click="creating = false; photo = null; error = ''">Hủy</UiButton>
        </div>
      </form>
    </section>
    <JoinPropertyPanel v-if="auth.user?.properties" @refreshed="load" />
  </main>
</template>

<style scoped>
.property-page { display: grid; gap: 16px; width: min(100%, 960px); margin: auto; padding: clamp(16px, 4vw, 36px); }
.property-header { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.property-header button { margin-left: auto; }
.property-links { margin: 16px 0; }
h1 { margin: 0; font-size: clamp(30px, 6vw, 42px); }
h2 { margin: 0; }
.property-page > p { margin: 0; }
.property-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 265px), 1fr)); gap: 12px; }
.property-card { display: flex; flex-direction: column; align-items: start; gap: 8px; text-align: left; color: var(--color-text); }
.property-card strong { font-size: 22px; overflow-wrap: anywhere; }
.property-card span { overflow-wrap: anywhere; }
.property-action { margin-top: 4px; }
.property-card:hover .property-action { border-color: var(--color-primary); background: var(--color-primary-soft); }
section > button { justify-self: start; }
fieldset { min-width: 0; margin: 0; padding: 0; border: 0; }
fieldset label { display: grid; gap: 6px; }
</style>

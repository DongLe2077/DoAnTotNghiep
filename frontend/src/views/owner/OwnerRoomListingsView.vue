<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import RemotePicker from '@/components/owner/RemotePicker.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import { api, prepareCsrf, toApiError } from '@/lib/api'
import type { OwnerListing } from '@/types/marketplace'
import type { Paged } from '@/types/property-onboarding'

const endpoint = '/owner/marketplace/listings'
const results = ref<Paged<OwnerListing>>()
const editing = ref<OwnerListing | 'new' | null>(null)
const roomIds = ref<number[]>([])
const photo = ref<File>()
const fileInput = ref<HTMLInputElement>()
const loading = ref(false)
const busy = ref(false)
const error = ref('')
const success = ref('')
const blank = { title: '', area: '', area_m2: '', description: '', amenities: '', fee_notes: '', published: false, sharing: false, remove_photo: false }
const form = reactive({ ...blank })

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    results.value = (await api.get<Paged<OwnerListing>>(endpoint, { params: { page } })).data
  } catch (e) {
    error.value = toApiError(e).message
  } finally {
    loading.value = false
  }
}

function edit(row: OwnerListing | 'new') {
  editing.value = row
  roomIds.value = []
  photo.value = undefined
  if (fileInput.value) fileInput.value.value = ''
  error.value = ''
  success.value = ''
  Object.assign(form, blank)
  if (row !== 'new') Object.assign(form, {
    title: row.title, area: row.area, area_m2: row.area_m2 ?? '', description: row.description,
    amenities: row.amenities ?? '', fee_notes: row.fee_notes ?? '', published: row.published, sharing: row.sharing,
  })
}

async function save() {
  const row = editing.value
  if (!row || busy.value) return
  if (row === 'new' && !roomIds.value[0]) {
    error.value = 'Hãy chọn phòng cần đăng tin.'
    return
  }
  const data = new FormData()
  for (const [name, value] of Object.entries(form)) {
    data.set(name, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
  }
  if (row === 'new') data.set('room_id', String(roomIds.value[0]))
  if (photo.value) data.set('photo', photo.value)
  await mutate(async () => {
    await api.post(row === 'new' ? endpoint : endpoint + '/' + row.id, data)
    editing.value = null
    success.value = 'Đã lưu tin phòng.'
  }, row === 'new' ? 1 : results.value?.meta.current_page)
}

async function hide(row: OwnerListing) {
  if (!window.confirm('Ẩn tin phòng này khỏi trang tìm phòng?')) return
  await mutate(async () => {
    await api.delete(endpoint + '/' + row.id)
    success.value = 'Đã ẩn tin phòng.'
  }, results.value?.meta.current_page)
}

async function mutate(operation: () => Promise<void>, page = 1) {
  if (busy.value) return
  busy.value = true
  error.value = ''
  success.value = ''
  try {
    await prepareCsrf()
    await operation()
    await load(page)
  } catch (e) {
    const failure = toApiError(e)
    error.value = Object.values(failure.errors).flat().join(' ') || failure.message
  } finally {
    busy.value = false
  }
}

onMounted(() => load())
</script>

<template>
  <div>
    <div class="management-header">
      <div><h1>Tin phòng cho thuê</h1><p>Đăng các phòng đang nhận người thuê lên trang tìm phòng.</p></div>
      <div class="button-row"><RouterLink class="record-link" to="/find-rooms">Xem trang tìm phòng</RouterLink><UiButton :disabled="busy" @click="edit('new')">Thêm tin phòng</UiButton></div>
    </div>
    <p v-if="success" role="status">{{ success }}</p>
    <p v-if="error && !editing" class="form-error" role="alert">{{ error }}</p>
    <form v-if="editing" class="record-card listing-editor form-grid" @submit.prevent="save">
      <h2>{{ editing === 'new' ? 'Tin phòng mới' : 'Sửa tin · ' + editing.room_number }}</h2>
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      <RemotePicker v-if="editing === 'new'" v-model="roomIds" label="Chọn phòng" path="/rooms" label-key="room_number" />
      <div class="listing-fields">
        <label>Tiêu đề<input v-model.trim="form.title" class="ui-input" maxlength="120" required /></label>
        <label>Khu vực<input v-model.trim="form.area" class="ui-input" maxlength="150" required /></label>
        <label>Diện tích (m²)<input v-model="form.area_m2" class="ui-input" type="number" min="1" max="99999" step=".01" /></label>
        <label>Ảnh bìa (tối đa 1 MB)<input ref="fileInput" class="ui-input" type="file" accept="image/jpeg,image/png,image/webp" @change="photo = ($event.target as HTMLInputElement).files?.[0]" /></label>
      </div>
      <label>Mô tả<textarea v-model.trim="form.description" class="ui-input" rows="3" maxlength="3000" required /></label>
      <div class="listing-fields">
        <label>Tiện nghi<textarea v-model.trim="form.amenities" class="ui-input" rows="2" maxlength="500" /></label>
        <label>Phí dịch vụ<textarea v-model.trim="form.fee_notes" class="ui-input" rows="2" maxlength="500" /></label>
      </div>
      <label v-if="editing !== 'new' && editing.has_photo && !photo"><input v-model="form.remove_photo" type="checkbox" /> Gỡ ảnh hiện tại</label>
      <label><input v-model="form.sharing" type="checkbox" /> Cho thuê chỗ còn trống trong phòng đang có người ở</label>
      <label><input v-model="form.published" type="checkbox" /> Công khai khi phòng còn chỗ</label>
      <div class="button-row"><UiButton type="submit" :loading="busy">Lưu tin</UiButton><UiButton variant="secondary" :disabled="busy" @click="editing = null; error = ''">Đóng</UiButton></div>
    </form>
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="!error && !results?.data.length" title="Chưa có tin phòng" />
    <div v-else class="listing-grid">
      <article v-for="row in results?.data" :key="row.id" class="record-card listing-editor">
        <h2>{{ row.title }}</h2>
        <p>{{ row.room_number ? 'Phòng ' + row.room_number : 'Phòng đã xóa' }} · {{ row.area }}</p>
        <p>{{ row.accepting ? 'Đang nhận khách · Còn ' + row.spots + ' chỗ' : row.published ? 'Tạm ngừng nhận khách' : 'Đang ẩn' }}</p>
        <div class="button-row">
          <UiButton v-if="row.room_id" variant="secondary" :disabled="busy" @click="edit(row)">Sửa tin</UiButton>
          <UiButton v-if="row.published" variant="secondary" :disabled="busy" @click="hide(row)">Ẩn tin</UiButton>
          <RouterLink class="record-link" v-if="row.accepting" :to="'/find-rooms/' + row.id">Xem tin →</RouterLink>
        </div>
      </article>
    </div>
    <OwnerPager v-if="results && results.meta.last_page > 1" :page="results.meta.current_page" :last-page="results.meta.last_page" :total="results.meta.total" :loading="loading" @change="load" />
  </div>
</template>

<style scoped>
.listing-editor { margin-bottom: 16px; padding: clamp(16px, 3vw, 24px); min-width: 0; overflow-wrap: anywhere; }
.listing-editor h2 { margin: 0; }
.listing-grid, .listing-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 330px), 1fr)); gap: 16px; }
.listing-editor label { min-width: 0; }
.listing-editor .ui-input { display: block; width: 100%; margin-top: 4px; }
</style>

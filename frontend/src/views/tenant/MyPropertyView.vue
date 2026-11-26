<script setup lang="ts">
import { useLiveRefresh } from '@/composables/useLiveRefresh'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import { api, toApiError } from '@/lib/api'

interface Workspace {
  name: string
  address: string
  contact_name: string | null
  phone: string | null
  email: string | null
  has_photo: boolean
  rental_status: 'unlinked' | 'no_current_stay' | 'current'
}

const workspace = ref<Workspace | null>(null)
const loading = ref(false)
const error = ref('')
const photo = ref('')
let request: AbortController | undefined

async function load() {
  request?.abort()
  const current = new AbortController()
  request = current
  loading.value = true
  error.value = ''
  workspace.value = null
  if (photo.value) URL.revokeObjectURL(photo.value)
  photo.value = ''
  try {
    const response = await api.get<{ data: Workspace }>('/tenant/workspace', { signal: current.signal })
    if (current.signal.aborted) return
    workspace.value = response.data.data
    if (workspace.value.has_photo) {
      const image = await api.get<Blob>('/properties/photo', { signal: current.signal, responseType: 'blob' })
      if (!current.signal.aborted) photo.value = URL.createObjectURL(image.data)
    }
  } catch (e) {
    if (!current.signal.aborted && !workspace.value) error.value = toApiError(e).message
  } finally {
    if (!current.signal.aborted) loading.value = false
  }
}

onMounted(load)
onBeforeUnmount(() => { request?.abort(); if (photo.value) URL.revokeObjectURL(photo.value) })
useLiveRefresh('property', () => !loading.value ? load() : false)
</script>

<template>
  <section class="management-page tenant-page">
    <header class="management-header">
      <div><h1>Khu trọ của tôi</h1><p>Thông tin khu trọ, liên hệ và tình trạng thuê của bạn.</p></div>
      <UiButton variant="secondary" :loading="loading" @click="load">Làm mới</UiButton>
    </header>
    <UiPageState v-if="loading && !workspace" kind="loading" />
    <UiPageState v-else-if="error" kind="error" :description="error"><RouterLink class="record-link" to="/properties">Chọn khu trọ</RouterLink></UiPageState>
    <div v-else-if="workspace" class="workspace-grid">
      <article class="record-card">
        <img v-if="photo" :src="photo" :alt="'Ảnh ' + workspace.name" />
        <h2>{{ workspace.name }}</h2>
        <p>{{ workspace.address }}</p>
        <dl v-if="workspace.contact_name || workspace.phone || workspace.email" class="record-facts">
          <div v-if="workspace.contact_name"><dt>Người liên hệ</dt><dd>{{ workspace.contact_name }}</dd></div>
          <div v-if="workspace.phone"><dt>Điện thoại</dt><dd>{{ workspace.phone }}</dd></div>
          <div v-if="workspace.email"><dt>Email</dt><dd>{{ workspace.email }}</dd></div>
        </dl>
        <RouterLink class="record-link" to="/notifications">Thông báo khu trọ →</RouterLink>
      </article>
      <article class="record-card">
        <h2>{{ workspace.rental_status === 'current' ? 'Bạn đang thuê phòng' : 'Chưa có phòng thuê' }}</h2>
        <UiBadge tone="success">Đã được duyệt vào khu</UiBadge>
        <p v-if="workspace.rental_status === 'unlinked'">Liên hệ chủ trọ để xác nhận hồ sơ người thuê. Thông tin phòng sẽ xuất hiện sau khi bạn được ghi nhận vào ở.</p>
        <p v-else-if="workspace.rental_status === 'no_current_stay'">Hồ sơ đã được liên kết. Chủ trọ sẽ xác nhận phòng và thời điểm vào ở của bạn.</p>
        <RouterLink v-else class="record-link" to="/tenant">Xem phòng của tôi →</RouterLink>
      </article>
    </div>
  </section>
</template>

<style scoped>
.workspace-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); align-items: start; gap: 16px; }
.workspace-grid img { display: block; width: 100%; max-height: 250px; object-fit: cover; border-radius: var(--radius-control); margin-bottom: 18px; }
.workspace-grid p { overflow-wrap: anywhere; }
</style>

<script setup lang="ts">
import { useLiveRefresh } from '@/composables/useLiveRefresh'
import { onMounted, ref } from 'vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import { api, prepareCsrf, toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import { openRequest, requestLabels, type RoomRequest } from '@/types/marketplace'
import type { Paged } from '@/types/property-onboarding'

const results = ref<Paged<RoomRequest>>()
const loading = ref(false)
const busy = ref(false)
const error = ref('')

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    results.value = (await api.get<Paged<RoomRequest>>('/marketplace/requests', { params: { page } })).data
  } catch (e) {
    error.value = toApiError(e).message
  } finally {
    loading.value = false
  }
}

async function act(row: RoomRequest, action: 'accept_time' | 'cancel') {
  if (busy.value || (action === 'cancel' && !window.confirm('Bạn muốn hủy yêu cầu này?'))) return
  busy.value = true
  error.value = ''
  try {
    await prepareCsrf()
    await api.put('/marketplace/requests/' + row.id, { action })
    await load(results.value?.meta.current_page)
  } catch (e) {
    error.value = toApiError(e).message
  } finally {
    busy.value = false
  }
}

onMounted(() => load())
useLiveRefresh('user', () => !busy.value && !loading.value ? load(results.value?.meta.current_page) : false)
</script>

<template>
  <main class="request-page rb7-theme">
    <nav class="button-row" aria-label="Điều hướng">
      <RouterLink class="record-link" to="/find-rooms">← Tìm phòng</RouterLink>
      <RouterLink class="record-link" to="/properties">Khu trọ của tôi</RouterLink>
    </nav>
    <div class="management-header">
      <div><h1>Yêu cầu của tôi</h1><p>Theo dõi lịch xem và hồ sơ thuê đã gửi.</p></div>
      <UiButton variant="secondary" :disabled="busy" :loading="loading" @click="load(results?.meta.current_page)">Làm mới</UiButton>
    </div>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="!error && !results?.data.length" title="Chưa gửi yêu cầu nào" />
    <div v-else class="request-grid">
      <article v-for="row in results?.data" :key="row.id" class="record-card">
        <p class="request-kind">{{ row.kind === 'viewing' ? 'Lịch xem phòng' : 'Yêu cầu thuê' }} · {{ row.people }} người</p>
        <h2>{{ row.title }}</h2>
        <p><strong>{{ requestLabels[row.status] }}</strong></p>
        <p>{{ row.kind === 'viewing' ? 'Giờ bạn chọn' : 'Dự kiến nhận phòng' }} · {{ formatDateTime(row.preferred_at) }}</p>
        <p v-if="row.appointment_at">{{ row.status === 'proposed' ? 'Giờ chủ trọ đề xuất' : 'Giờ hẹn' }} · {{ formatDateTime(row.appointment_at) }}</p>
        <p v-if="row.owner_note">{{ row.owner_note }}</p>
        <p v-if="row.moved_in_at">Nhận phòng lúc {{ formatDateTime(row.moved_in_at) }}</p>
        <div class="button-row">
          <UiButton v-if="row.status === 'proposed'" :disabled="busy" @click="act(row, 'accept_time')">Đồng ý giờ mới</UiButton>
          <UiButton v-if="openRequest(row.status)" variant="secondary" :disabled="busy" @click="act(row, 'cancel')">{{ row.kind === 'rent' ? 'Rút yêu cầu' : 'Hủy lịch xem' }}</UiButton>
          <RouterLink class="record-link" v-if="row.status === 'viewed'" :to="'/find-rooms/' + row.listing_id + '?request=rent'">Gửi yêu cầu thuê →</RouterLink>
        </div>
      </article>
    </div>
    <OwnerPager v-if="results && results.meta.last_page > 1" :page="results.meta.current_page" :last-page="results.meta.last_page" :total="results.meta.total" :loading="loading" @change="load" />
  </main>
</template>

<style scoped>
.request-page { width: min(100%, 1100px); margin-inline: auto; padding: clamp(16px, 4vw, 36px); }
.request-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 360px), 1fr)); gap: 16px; }
.request-grid article { min-width: 0; padding: 20px; overflow-wrap: anywhere; }
.request-grid h2 { margin: 0; }
.request-kind { margin: 0 0 6px; color: var(--color-text-muted); }
</style>

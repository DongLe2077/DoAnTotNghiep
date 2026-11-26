<script setup lang="ts">
import { useLiveRefresh } from '@/composables/useLiveRefresh'
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import { api, prepareCsrf, toApiError } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import { getRoleHome } from '@/router/navigation'
import { marketTimestamp, type RequestKind, type RoomListing } from '@/types/marketplace'
import type { Paged } from '@/types/property-onboarding'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const id = computed(() => Number(route.params.id) || null)
const listing = ref<RoomListing | null>(null)
const results = ref<Paged<RoomListing> | null>(null)
const filters = reactive({ area: '', min_price: '', max_price: '', people: '' })
const activeFilters = ref<Record<string, string>>({})
const showAll = ref(false)
const filtersOpenByDefault = window.matchMedia('(min-width: 721px)').matches
const suggestionLimit = 4
const hasFilters = computed(() => Object.keys(activeFilters.value).length > 0)
const showingSuggestions = computed(() => !hasFilters.value && !showAll.value)
const visibleRooms = computed(() => {
  const rooms = results.value?.data ?? []
  return showingSuggestions.value ? rooms.slice(0, suggestionLimit) : rooms
})
const form = reactive({ preferred_at: '', people: 1, contact_name: '', contact_phone: '', note: '' })
const kind = ref<RequestKind | null>(null)
const loading = ref(false)
const busy = ref(false)
const pageError = ref('')
const error = ref('')
let loadVersion = 0

const workspace = computed(() => auth.user?.needs_property ? '/properties' : auth.user?.properties && auth.user.role === 'tenant' ? '/tenant/workspace' : getRoleHome(auth.user?.role ?? 'tenant'))

function draftKey(listingId: number, requestKind: RequestKind) {
  return 'rentbase.market.draft.' + listingId + '.' + requestKind
}

function restoreDraft() {
  const requestKind = route.query.request
  if (!id.value || (requestKind !== 'viewing' && requestKind !== 'rent')) return
  kind.value = requestKind
  try {
    const key = draftKey(id.value, requestKind)
    const saved = JSON.parse(sessionStorage.getItem(key) ?? 'null')
    sessionStorage.removeItem(key)
    if (saved && Date.now() < saved.until) Object.assign(form, saved.form)
  } catch { /* Browser storage may be disabled. */ }
}

async function load(page = 1) {
  const version = ++loadVersion
  pageError.value = ''
  loading.value = true
  try {
    if (id.value) {
      const response = await api.get<{ data: RoomListing }>('/marketplace/listings/' + id.value)
      if (version === loadVersion) listing.value = response.data.data
    } else {
      const response = await api.get<Paged<RoomListing>>('/marketplace/listings', {
        params: { ...activeFilters.value, page },
      })
      if (version === loadVersion) results.value = response.data
    }
  } catch (e) {
    if (version === loadVersion) {
      const failure = toApiError(e)
      if (route.name === 'market-home' && failure.status === 404) {
        await router.replace(auth.user ? workspace.value : '/login')
      } else pageError.value = failure.message
    }
  } finally {
    if (version === loadVersion) loading.value = false
  }
}

function applyFilters() {
  activeFilters.value = Object.fromEntries(
    Object.entries(filters).map(([key, value]) => [key, String(value).trim()]).filter(([, value]) => value !== ''),
  )
  showAll.value = false
  void load()
}

function clearFilters() {
  Object.assign(filters, { area: '', min_price: '', max_price: '', people: '' })
  applyFilters()
}

function openForm(requestKind: RequestKind) {
  kind.value = requestKind
  error.value = ''
}

async function submit() {
  if (!listing.value || !kind.value || busy.value) return
  const requestKind = kind.value
  if (!auth.user) {
    try {
      sessionStorage.setItem(draftKey(listing.value.id, requestKind), JSON.stringify({ form: { ...form }, until: Date.now() + 30 * 60_000 }))
    } catch { /* Return to the listing even without a local draft. */ }
    await router.push({ name: 'login', query: { redirect: '/find-rooms/' + listing.value.id + '?request=' + requestKind } })
    return
  }
  busy.value = true
  error.value = ''
  try {
    await prepareCsrf()
    await api.post('/marketplace/listings/' + listing.value.id + '/requests', {
      ...form, kind: requestKind, preferred_at: marketTimestamp(form.preferred_at),
      contact_phone: form.contact_phone.replace(/\s/g, ''),
    })
    await router.push('/my-room-requests')
  } catch (e) {
    const failure = toApiError(e)
    error.value = Object.values(failure.errors).flat().join(' ') || failure.message
  } finally {
    busy.value = false
  }
}

watch(() => route.fullPath, () => {
  listing.value = null
  kind.value = null
  error.value = ''
  Object.assign(form, { preferred_at: '', people: 1, contact_name: '', contact_phone: '', note: '' })
  restoreDraft()
  void load()
}, { immediate: true })
useLiveRefresh('market', () => !busy.value && !loading.value ? load(results.value?.meta.current_page) : false)
</script>

<template>
  <main class="market-page rb7-theme">
    <header class="market-header">
      <RouterLink to="/find-rooms" class="market-brand"><img src="/icons/rentbase-mark.svg" width="44" height="44" alt="" /><strong>RentBase</strong></RouterLink>
      <nav aria-label="Tìm phòng">
        <template v-if="auth.user"><RouterLink class="record-link" to="/my-room-requests">Yêu cầu của tôi</RouterLink><RouterLink class="record-link" :to="workspace">Khu trọ của tôi</RouterLink></template>
        <RouterLink class="record-link" v-else to="/login">Đăng nhập</RouterLink>
      </nav>
    </header>

    <template v-if="!id">
      <div class="management-header"><div><h1>Tìm phòng trọ</h1><p>Xem trực tiếp các phòng đang nhận khách và đặt lịch với chủ trọ.</p></div></div>
      <div class="rb7-market-layout">
      <details :open="filtersOpenByDefault" class="record-card rb7-market-filters">
        <summary>Lọc phòng</summary>
      <form class="market-filter" @submit.prevent="applyFilters">
        <label>Khu vực<input v-model="filters.area" class="ui-input" placeholder="Tên phường hoặc quận" maxlength="150" /></label>
        <label>Giá từ (đ/tháng)<input v-model="filters.min_price" class="ui-input" type="number" inputmode="numeric" min="0" /></label>
        <label>Giá đến (đ/tháng)<input v-model="filters.max_price" class="ui-input" type="number" inputmode="numeric" min="0" /></label>
        <label>Số người<input v-model="filters.people" class="ui-input" type="number" inputmode="numeric" min="1" max="50" /></label>
        <UiButton type="submit" :loading="loading">Tìm phòng</UiButton>
        <UiButton v-if="hasFilters" type="button" variant="secondary" :disabled="loading" @click="clearFilters">Bỏ lọc</UiButton>
      </form>
      </details>
      <section aria-labelledby="market-results-title" :aria-busy="loading">
      <h2 id="market-results-title" class="market-results-title">{{ showingSuggestions ? 'Gợi ý phòng trọ' : hasFilters ? 'Kết quả tìm phòng' : 'Tất cả phòng đang nhận khách' }}</h2>
      <p v-if="!loading && !pageError && !showingSuggestions && results?.meta.total" class="help-text rb7-market-count">{{ results.meta.total }} {{ hasFilters ? 'phòng phù hợp' : 'phòng đang nhận khách' }}</p>
      <UiPageState v-if="loading" kind="loading" heading-level="h3" />
      <UiPageState v-else-if="pageError" kind="error" heading-level="h3" :description="pageError"><UiButton @click="load()">Tải lại</UiButton></UiPageState>
      <UiPageState v-else-if="!visibleRooms.length && hasFilters" heading-level="h3" title="Chưa tìm thấy phòng" description="Thử đổi khu vực, giá hoặc số người." />
      <UiPageState v-else-if="!visibleRooms.length" heading-level="h3" title="Chưa có phòng đang nhận khách" description="Bạn có thể quay lại khi có tin phòng mới." />
      <div v-else class="market-grid">
        <article v-for="room in visibleRooms" :key="room.id" class="record-card room-card">
          <img v-if="room.has_photo" :src="'/api/marketplace/listings/' + room.id + '/photo'" :alt="room.title" loading="lazy" />
          <div class="room-card-content">
            <small>{{ room.property_name }} · {{ room.area }}</small>
            <h3>{{ room.title }}</h3>
            <p v-if="room.room_number" class="help-text">Số phòng <strong>{{ room.room_number }}</strong></p>
            <p class="market-price">{{ formatMoney(room.monthly_rent ?? '0') }} <small>/ tháng</small></p>
            <p>{{ room.occupied ? 'Ở ghép · ' : 'Phòng trống · ' }}Còn {{ room.spots }} chỗ <template v-if="room.area_m2">· {{ room.area_m2 }} m²</template></p>
            <RouterLink class="record-link" :to="'/find-rooms/' + room.id">Xem phòng →</RouterLink>
          </div>
        </article>
      </div>
      <UiButton v-if="showingSuggestions && !loading && !pageError && (results?.meta.total ?? 0) > suggestionLimit" class="market-show-all" variant="secondary" @click="showAll = true">Xem tất cả phòng</UiButton>
      <OwnerPager v-if="!showingSuggestions && results && results.meta.last_page > 1" :page="results.meta.current_page" :last-page="results.meta.last_page" :total="results.meta.total" :loading="loading" @change="load" />
      </section>
      </div>
    </template>

    <template v-else>
      <RouterLink class="record-link" to="/find-rooms">← Tất cả phòng</RouterLink>
      <UiPageState v-if="loading" kind="loading" />
      <UiPageState v-else-if="pageError" kind="error" :description="pageError">
        <RouterLink class="record-link" to="/find-rooms">Xem các phòng khác</RouterLink>
      </UiPageState>
      <template v-else-if="listing">
        <header class="management-header"><div><h1>{{ listing.title }}</h1><p>{{ listing.property_name }} · {{ listing.area }}</p></div></header>
        <div class="market-detail">
          <article class="record-card">
            <img v-if="listing.has_photo" :src="'/api/marketplace/listings/' + listing.id + '/photo'" :alt="listing.title" />
            <h2>Thông tin phòng</h2>
            <p class="room-description">{{ listing.description }}</p>
            <dl class="record-facts">
              <div v-if="listing.room_number"><dt>Số phòng</dt><dd>{{ listing.room_number }}</dd></div>
              <div v-if="listing.area_m2"><dt>Diện tích</dt><dd>{{ listing.area_m2 }} m²</dd></div>
              <div><dt>Sức chứa</dt><dd>{{ listing.capacity }} người, còn {{ listing.spots }} chỗ</dd></div>
              <div v-if="listing.amenities"><dt>Tiện ích</dt><dd>{{ listing.amenities }}</dd></div>
              <div><dt>Phí dịch vụ</dt><dd>{{ listing.fee_notes || 'Trao đổi với chủ trọ khi xem phòng' }}</dd></div>
            </dl>
          </article>
          <section class="record-card request-card">
            <p class="market-price">{{ formatMoney(listing.monthly_rent ?? '0') }} <small>/ tháng, cả phòng</small></p>
            <div class="button-row">
              <UiButton :disabled="busy" @click="openForm('viewing')">Đặt lịch xem phòng</UiButton>
              <UiButton :disabled="busy" variant="secondary" @click="openForm('rent')">Gửi yêu cầu thuê</UiButton>
            </div>
            <form v-if="kind" class="form-grid" @submit.prevent="submit">
              <h2>{{ kind === 'viewing' ? 'Chọn giờ xem phòng' : 'Thông tin thuê dự kiến' }}</h2>
              <p class="help-text">Gửi yêu cầu để chủ trọ liên hệ. Phòng chỉ được ghi nhận cho thuê khi chủ trọ xác nhận nhận phòng.</p>
              <label>{{ kind === 'viewing' ? 'Ngày giờ muốn xem' : 'Ngày giờ muốn vào ở' }} (giờ Việt Nam)
                <input v-model="form.preferred_at" class="ui-input" type="datetime-local" required :disabled="busy" />
              </label>
              <label>Số người dự kiến<input v-model.number="form.people" class="ui-input" type="number" inputmode="numeric" min="1" :max="listing.spots" required :disabled="busy" /></label>
              <label>Họ và tên liên hệ<input v-model="form.contact_name" class="ui-input" autocomplete="name" maxlength="150" required :disabled="busy" /></label>
              <label>Số điện thoại<input v-model="form.contact_phone" class="ui-input" type="tel" autocomplete="tel" maxlength="20" required :disabled="busy" /></label>
              <label>Ghi chú (không bắt buộc)<textarea v-model="form.note" class="ui-input" maxlength="500" rows="3" :disabled="busy" /></label>
              <p v-if="error" class="form-error" role="alert">{{ error }} <RouterLink class="record-link" to="/my-room-requests">Xem yêu cầu của tôi</RouterLink></p>
              <UiButton type="submit" :loading="busy">{{ auth.user ? 'Gửi yêu cầu' : 'Đăng nhập để tiếp tục' }}</UiButton>
            </form>
          </section>
        </div>
      </template>
    </template>
  </main>
</template>

<style scoped>
.market-page { display: grid; gap: 20px; width: min(100%, 1160px); margin: auto; padding: clamp(16px, 4vw, 36px); }
.market-header, .market-header nav, .market-brand { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
.market-header { justify-content: space-between; }
.market-brand { text-decoration: none; }
.market-header a { overflow-wrap: anywhere; }
.market-filter, .market-grid, .market-detail { display: grid; gap: 14px; }
.market-filter { grid-template-columns: repeat(auto-fit, minmax(min(100%, 145px), 1fr)); align-items: end; }
.market-filter label, .request-card label { display: grid; gap: 6px; min-width: 0; }
.market-grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 265px), 1fr)); }
.room-card { padding: 0; overflow: hidden; }
.room-card-content { padding: 18px; }
.market-results-title { font-size: clamp(20px, 2.5vw, 24px); margin: 0 0 12px; }
.market-show-all { margin-top: 14px; }
.room-card-content h3 { font-size: 1.15rem; margin: 6px 0; overflow-wrap: anywhere; }
.market-page article img { display: block; width: 100%; max-height: 320px; aspect-ratio: 16 / 9; object-fit: cover; }
.market-price { font-size: 22px; font-weight: 750; color: var(--color-primary); }
.market-price small { font-size: 13px; font-weight: 500; }
.room-description { white-space: pre-wrap; overflow-wrap: anywhere; }
.market-detail { align-items: start; }
.request-card form { margin-top: 18px; }
@media (min-width: 820px) { .market-detail { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); } }
</style>

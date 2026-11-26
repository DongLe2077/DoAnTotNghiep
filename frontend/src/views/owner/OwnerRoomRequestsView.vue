<script setup lang="ts">
import { useLiveRefresh } from '@/composables/useLiveRefresh'
import { computed, ref, watch } from 'vue'
import RemotePicker from '@/components/owner/RemotePicker.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import { api, prepareCsrf, toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import { marketTimestamp, openRequest, requestLabels, vietnamNow, type OwnerRoomRequest, type RequestKind } from '@/types/marketplace'
import type { Paged } from '@/types/property-onboarding'

const endpoint = '/owner/marketplace/requests'
const results = ref<Paged<OwnerRoomRequest>>()
const kind = ref<RequestKind>('viewing')
const status = ref('')
const loading = ref(false)
const busy = ref(false)
const error = ref('')
const success = ref('')
const selected = ref<OwnerRoomRequest>()
const action = ref('')
const note = ref('')
const appointment = ref('')
const principal = ref<number[]>([])
const companions = ref<number[]>([])
const approve = ref(false)
let loadVersion = 0

const statuses = computed(() => kind.value === 'viewing'
  ? ['pending', 'confirmed', 'proposed', 'viewed', 'cancelled', 'no_show']
  : ['new', 'discussing', 'accepted', 'rejected', 'withdrawn', 'moved_in'])

function actions(row: OwnerRoomRequest): Record<string, string> {
  if (!openRequest(row.status)) return {}
  if (row.kind === 'rent') return {
    ...(row.status === 'new' ? { discuss: 'Đang trao đổi' } : {}),
    ...(row.status === 'accepted' ? { move_in: 'Xác nhận nhận phòng' } : { accept: 'Chấp thuận' }),
    reject: 'Từ chối',
  }
  return {
    ...(row.status === 'pending' ? { confirm: 'Xác nhận giờ khách chọn' } : {}),
    propose: 'Đề xuất giờ khác',
    ...(row.status === 'confirmed' ? { viewed: 'Đã đến xem', no_show: 'Không đến' } : {}),
    cancel: 'Hủy lịch',
  }
}

async function load(page = 1) {
  const version = ++loadVersion
  loading.value = true
  error.value = ''
  try {
    const response = await api.get<Paged<OwnerRoomRequest>>(endpoint, { params: { kind: kind.value, status: status.value || undefined, page } })
    if (version === loadVersion) results.value = response.data
  } catch (e) {
    if (version === loadVersion) error.value = toApiError(e).message
  } finally {
    if (version === loadVersion) loading.value = false
  }
}

function begin(row: OwnerRoomRequest, next: string) {
  selected.value = row
  action.value = next
  note.value = row.owner_note ?? ''
  appointment.value = next === 'move_in' ? vietnamNow() : ''
  principal.value = []
  companions.value = []
  approve.value = false
  success.value = ''
  error.value = ''
}

async function submit() {
  const row = selected.value
  if (!row || busy.value) return
  busy.value = true
  error.value = ''
  try {
    await prepareCsrf()
    if (action.value === 'move_in') {
      await api.post(endpoint + '/' + row.id + '/move-in', {
        started_at: marketTimestamp(appointment.value), approve_membership: approve.value,
        tenant_id: principal.value[0] ?? null, companion_ids: companions.value,
      })
    } else {
      await api.put(endpoint + '/' + row.id, {
        action: action.value, owner_note: note.value || null,
        appointment_at: action.value === 'propose' ? marketTimestamp(appointment.value) : null,
      })
    }
    success.value = action.value === 'move_in' ? 'Đã ghi nhận nhận phòng.' : 'Đã cập nhật yêu cầu.'
    selected.value = undefined
    await load(results.value?.meta.current_page)
  } catch (e) {
    const failure = toApiError(e)
    error.value = Object.values(failure.errors).flat().join(' ') || failure.message
  } finally {
    busy.value = false
  }
}

function switchKind(next: RequestKind) {
  selected.value = undefined
  success.value = ''
  status.value = ''
  kind.value = next
}

watch([kind, status], () => load(), { immediate: true })
useLiveRefresh('property', () => !busy.value && !loading.value ? load(results.value?.meta.current_page) : false)
</script>

<template>
  <div>
    <div class="management-header"><div><h1>Lịch xem và yêu cầu thuê</h1><p>Xác nhận lịch hẹn và ghi nhận khách thực tế vào ở.</p></div></div>
    <div class="button-row request-bar">
      <UiButton :variant="kind === 'viewing' ? 'primary' : 'secondary'" :aria-pressed="kind === 'viewing'" :disabled="busy" @click="switchKind('viewing')">Lịch xem</UiButton>
      <UiButton :variant="kind === 'rent' ? 'primary' : 'secondary'" :aria-pressed="kind === 'rent'" :disabled="busy" @click="switchKind('rent')">Yêu cầu thuê</UiButton>
      <label>Trạng thái
        <select v-model="status" class="ui-input" :disabled="busy" @change="selected = undefined">
          <option value="">Tất cả</option><option v-for="value in statuses" :key="value" :value="value">{{ requestLabels[value] }}</option>
        </select>
      </label>
    </div>

    <p v-if="success" role="status">{{ success }}</p>
    <p v-if="error && !selected" class="form-error" role="alert">{{ error }}</p>
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="!error && !results?.data.length" title="Chưa có yêu cầu" />
    <div v-else class="request-list">
      <article v-for="row in results?.data" :key="row.id" class="record-card request-item">
        <p class="request-status">{{ requestLabels[row.status] }}</p>
        <h2>{{ row.title }}</h2>
        <p>{{ row.contact_name }} · {{ row.contact_phone }} · {{ row.people }} người</p>
        <p>{{ row.kind === 'viewing' ? 'Giờ khách chọn' : 'Dự kiến nhận phòng' }} · {{ formatDateTime(row.preferred_at) }}</p>
        <p v-if="row.appointment_at">Giờ hẹn / đề xuất · {{ formatDateTime(row.appointment_at) }}</p>
        <p v-if="row.note">Khách nhắn · {{ row.note }}</p>
        <p v-if="row.owner_note">Phản hồi · {{ row.owner_note }}</p>
        <RouterLink class="record-link" v-if="row.occupancy_id" :to="'/owner/stays/' + row.occupancy_id">Xem đợt thuê →</RouterLink>

        <form v-if="selected?.id === row.id" :key="action" class="form-grid request-form" @submit.prevent="submit">
          <h3>{{ actions(row)[action] }}</h3>
          <p v-if="error" class="form-error" role="alert">{{ error }}</p>
          <template v-if="action === 'move_in'">
            <p>Xác nhận sau khi khách đã đến nhận phòng. Hệ thống sẽ kiểm tra lại số chỗ và cập nhật cư trú.</p>
            <label>Thời điểm nhận phòng<input v-model="appointment" class="ui-input" type="datetime-local" required /></label>
            <label v-if="!row.is_member"><input v-model="approve" type="checkbox" required /> Duyệt tài khoản này vào khu trọ khi nhận phòng</label>
            <div v-if="row.tenant_id" class="record-card">
              <p>Hồ sơ được dùng tự động</p>
              <RouterLink class="record-link" :to="'/owner/tenants/' + row.tenant_id">
                {{ row.tenant_name || 'Xem hồ sơ đã liên kết' }}
              </RouterLink>
            </div>
            <template v-else>
              <p>Tài khoản này chưa liên kết hồ sơ trong khu. Khi ghi nhận nhận phòng, hệ thống sẽ tạo hồ sơ từ thông tin khách gửi.</p>
              <details>
                <summary>Chọn hồ sơ có sẵn nếu bạn đã tạo cho khách</summary>
                <RemotePicker v-model="principal" path="/tenants" label-key="full_name" label="Hồ sơ có sẵn của khách (tùy chọn)" />
              </details>
            </template>
            <RemotePicker v-if="row.people > 1" v-model="companions" path="/tenants" label-key="full_name" :label="'Chọn ' + (row.people - 1) + ' hồ sơ người đi cùng'" multiple />
          </template>
          <template v-else>
            <label v-if="action === 'propose'">Giờ xem đề xuất<input v-model="appointment" class="ui-input" type="datetime-local" required /></label>
            <label>Phản hồi cho khách<textarea v-model.trim="note" class="ui-input" rows="2" maxlength="500" /></label>
          </template>
          <div class="button-row">
            <UiButton type="submit" :loading="busy">{{ action === 'move_in' ? 'Ghi nhận nhận phòng' : 'Lưu phản hồi' }}</UiButton>
            <UiButton variant="secondary" :disabled="busy" @click="selected = undefined; error = ''">Đóng</UiButton>
          </div>
        </form>
        <div v-else class="button-row">
          <UiButton v-for="(label, key) in actions(row)" :key="key" :variant="key === 'move_in' ? 'primary' : 'secondary'" :disabled="busy" @click="begin(row, key)">{{ label }}</UiButton>
        </div>
      </article>
    </div>
    <OwnerPager v-if="results && results.meta.last_page > 1" :page="results.meta.current_page" :last-page="results.meta.last_page" :total="results.meta.total" :loading="loading || busy" @change="load" />
  </div>
</template>

<style scoped>
.request-bar { margin-bottom: 18px; align-items: end; }
.request-bar select { display: block; margin-top: 4px; }
.request-list { display: grid; gap: 14px; }
.request-item { padding: clamp(16px, 4vw, 24px); min-width: 0; overflow-wrap: anywhere; }
.request-item h2, .request-form h3, .request-form p { margin: 0; }
.request-status { margin: 0 0 6px; font-weight: 650; }
.request-form { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-border); }
.request-form .ui-input { display: block; width: 100%; margin-top: 4px; }
</style>

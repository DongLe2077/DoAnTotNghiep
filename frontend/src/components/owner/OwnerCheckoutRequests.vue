<script setup lang="ts">
import { ref, useId, watch } from 'vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import { useLiveRefresh } from '@/composables/useLiveRefresh'
import { eventPayload, localEventTime, useOwnerApi, useOwnerList } from '@/composables/useOwnerApi'
import { toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import { checkoutStatus, type CheckoutApplication } from '@/types/checkout'

const endpoint = '/checkout-requests'
const { items, loading, loadError, page, lastPage, total, load } = useOwnerList<CheckoutApplication>(endpoint)
const { write, busy } = useOwnerApi()
const filter = ref<'pending' | 'history'>('pending')
const selected = ref<CheckoutApplication>()
const action = ref<'confirm' | 'reject'>('confirm')
const endedAt = ref('')
const reason = ref('')
const error = ref('')
const notice = ref('')
const formId = useId()

watch(filter, status => load(1, { status }), { immediate: true })
useLiveRefresh('property', () => !busy.value && !loading.value ? load() : false)

function open(row: CheckoutApplication, next: 'confirm' | 'reject') {
  selected.value = row
  action.value = next
  endedAt.value = localEventTime()
  reason.value = ''
  error.value = ''
  notice.value = ''
}

async function submit() {
  const row = selected.value
  if (!row || busy.value) return
  const next = action.value
  if (next === 'reject' && !reason.value.trim()) {
    error.value = 'Hãy nhập lý do từ chối.'
    return
  }

  error.value = ''
  try {
    await write('post', endpoint + '/' + row.id + '/' + next,
      next === 'confirm' ? { ended_at: eventPayload(endedAt.value) } : { reason: reason.value.trim() })
    selected.value = undefined
    notice.value = next === 'confirm' ? 'Đã xác nhận trả phòng.' : 'Đã từ chối yêu cầu trả phòng.'
    await load()
  } catch (caught) {
    const failure = toApiError(caught)
    error.value = Object.values(failure.errors).flat().join(' ') || failure.message
    if ([404, 409].includes(failure.status)) await load()
  }
}
</script>

<template>
  <section class="rb7-panel checkout-panel" aria-labelledby="owner-checkout-heading">
    <header>
      <h2 id="owner-checkout-heading">Yêu cầu trả phòng</h2>
      <div class="button-row" aria-label="Trạng thái yêu cầu" role="group">
        <UiButton :variant="filter === 'pending' ? 'primary' : 'secondary'" :aria-pressed="filter === 'pending'" @click="filter = 'pending'">Chờ xử lý</UiButton>
        <UiButton :variant="filter === 'history' ? 'primary' : 'secondary'" :aria-pressed="filter === 'history'" @click="filter = 'history'">Lịch sử</UiButton>
      </div>
    </header>

    <UiNotice v-if="notice" tone="success">{{ notice }}</UiNotice>
    <UiNotice v-if="loadError" tone="danger">
      <p>{{ loadError }}</p>
      <UiButton variant="secondary" @click="load()">Tải lại</UiButton>
    </UiNotice>
    <p v-else-if="loading" role="status">Đang tải yêu cầu…</p>
    <p v-else-if="!items.length" class="help-text">{{ filter === 'pending' ? 'Chưa có yêu cầu đang chờ.' : 'Chưa có yêu cầu đã xử lý.' }}</p>
    <ul v-else class="checkout-list">
      <li v-for="row in items" :key="row.id">
        <div class="checkout-row-title">
          <RouterLink class="record-link" :to="'/owner/stays/' + row.occupancy_id">Phòng {{ row.room_number }}</RouterLink>
          <UiBadge :tone="row.status === 'pending' ? 'warning' : row.status === 'confirmed' ? 'success' : 'neutral'">{{ checkoutStatus[row.status] }}</UiBadge>
        </div>
        <p>{{ row.requester_name }} · {{ formatDateTime(row.requested_at) }}</p>
        <p v-if="row.rejection_reason">Lý do từ chối · {{ row.rejection_reason }}</p>
        <p v-if="row.ended_at && row.status === 'confirmed'">Thời điểm trả phòng · {{ formatDateTime(row.ended_at) }}</p>
        <div v-if="row.status === 'pending'" class="button-row">
          <UiButton @click="open(row, 'confirm')">Xác nhận trả phòng</UiButton>
          <UiButton variant="secondary" @click="open(row, 'reject')">Từ chối</UiButton>
        </div>
      </li>
    </ul>
    <OwnerPager v-if="lastPage > 1" :page="page" :last-page="lastPage" :total="total" :loading="loading || busy" @change="load" />

    <UiDialog :open="Boolean(selected)" :title="action === 'confirm' ? 'Xác nhận trả phòng' : 'Từ chối yêu cầu trả phòng'" @update:open="value => { if (!value && !busy) selected = undefined }">
      <form v-if="selected" :id="formId" class="form-grid" @submit.prevent="submit">
        <strong>Phòng {{ selected.room_number }} · {{ selected.requester_name }}</strong>
        <p v-if="action === 'confirm'">Chỉ xác nhận sau khi nhận bàn giao phòng. Thao tác sẽ kết thúc đợt thuê và giữ lại lịch sử cư trú, hóa đơn và công nợ.</p>
        <label v-if="action === 'confirm'">
          Thời điểm trả phòng thực tế (giờ Việt Nam)
          <input v-model="endedAt" class="ui-input" type="datetime-local" step="1" :max="localEventTime()" required :disabled="busy" />
        </label>
        <label v-else>
          Lý do từ chối
          <textarea v-model="reason" class="ui-input" maxlength="500" rows="3" required :disabled="busy" />
        </label>
        <UiNotice v-if="error" tone="danger">{{ error }}</UiNotice>
      </form>
      <template #actions>
        <UiButton variant="secondary" :disabled="busy" @click="selected = undefined">Hủy</UiButton>
        <UiButton type="submit" :form="formId" :loading="busy">{{ action === 'confirm' ? 'Xác nhận' : 'Gửi lý do từ chối' }}</UiButton>
      </template>
    </UiDialog>
  </section>
</template>

<style scoped>
.checkout-panel { margin-top: 24px; padding: clamp(16px, 4vw, 24px); }
.checkout-panel > header, .checkout-row-title { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 16px; }
.checkout-list { margin: 16px 0 0; padding: 0; list-style: none; }
.checkout-list li { border-top: 1px solid var(--rb7-line); padding: 14px 0; overflow-wrap: anywhere; }
.checkout-list li:last-child { padding-bottom: 0; }
.checkout-list p { margin: 6px 0; color: var(--color-text-secondary); }
.checkout-list .button-row { margin-top: 12px; }
.form-grid label { display: grid; gap: 6px; }
.form-grid .ui-input { width: 100%; min-width: 0; }
</style>

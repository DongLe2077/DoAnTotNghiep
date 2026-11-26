<script setup lang="ts">
import { computed, ref } from 'vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import { useLiveRefresh } from '@/composables/useLiveRefresh'
import { useTenantApi, useTenantList } from '@/composables/useTenantApi'
import { toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import { checkoutStatus, type CheckoutApplication } from '@/types/checkout'
import type { MyStay } from '@/types/tenant'

const props = defineProps<{ stay: MyStay | null }>()
const endpoint = '/checkout-requests'
const { items, loading, error, page, lastPage, total, load } = useTenantList<CheckoutApplication>(endpoint)
const { post, remove, busy } = useTenantApi()
const change = ref<'submit' | 'withdraw'>()
const actionError = ref('')
const notice = ref('')
const representative = computed(() => props.stay?.is_current_representative ?? false)
const pending = computed(() => items.value.find(row => row.occupancy_id === props.stay?.occupancy_id && row.status === 'pending'))

void load()
useLiveRefresh('property', () => !busy.value && !loading.value ? load() : false)

function open(action: 'submit' | 'withdraw') {
  change.value = action
  actionError.value = ''
  notice.value = ''
}

async function submit() {
  const action = change.value
  const occupancy = props.stay?.occupancy_id
  const id = pending.value?.id
  if (!representative.value || !occupancy || !action || busy.value || (action === 'withdraw' && !id)) return

  actionError.value = ''
  try {
    if (action === 'withdraw') await remove(endpoint + '/' + id)
    else await post('/occupancies/' + occupancy + endpoint, {})
    change.value = undefined
    notice.value = action === 'withdraw' ? 'Đã rút yêu cầu trả phòng.' : 'Đã gửi yêu cầu trả phòng cho chủ trọ.'
    await load(1)
  } catch (caught) {
    const failure = toApiError(caught)
    actionError.value = failure.message
    if ([404, 409].includes(failure.status)) await load(1)
  }
}
</script>

<template>
  <section v-if="representative || total > 0 || notice" class="record-card checkout-panel" aria-labelledby="my-checkout-heading">
    <h2 id="my-checkout-heading">Yêu cầu trả phòng</h2>
    <UiNotice v-if="notice" tone="success">{{ notice }}</UiNotice>
    <UiNotice v-if="error" tone="danger">
      <p>{{ error }}</p>
      <UiButton variant="secondary" @click="load()">Tải lại</UiButton>
    </UiNotice>
    <p v-else-if="loading" role="status">Đang tải yêu cầu…</p>
    <template v-else>
      <div v-if="representative && page === 1" class="button-row">
        <UiButton v-if="pending" variant="secondary" @click="open('withdraw')">Rút yêu cầu trả phòng</UiButton>
        <UiButton v-else @click="open('submit')">Gửi yêu cầu trả phòng</UiButton>
      </div>
      <ul v-if="items.length" class="checkout-list">
        <li v-for="row in items" :key="row.id">
          <div>
            <strong>Phòng {{ row.room_number }}</strong>
            <UiBadge :tone="row.status === 'pending' ? 'warning' : row.status === 'confirmed' ? 'success' : 'neutral'">{{ checkoutStatus[row.status] }}</UiBadge>
          </div>
          <p>Gửi lúc {{ formatDateTime(row.requested_at) }}</p>
          <p v-if="row.rejection_reason">Lý do từ chối · {{ row.rejection_reason }}</p>
          <p v-if="row.ended_at && row.status === 'confirmed'">Thời điểm trả phòng · {{ formatDateTime(row.ended_at) }}</p>
        </li>
      </ul>
      <OwnerPager v-if="lastPage > 1" :page="page" :last-page="lastPage" :total="total" :loading="loading || busy" @change="load" />
    </template>

    <UiDialog :open="Boolean(change)" :title="change === 'withdraw' ? 'Rút yêu cầu trả phòng' : 'Gửi yêu cầu trả phòng'" @update:open="value => { if (!value && !busy) change = undefined }">
      <p v-if="change === 'withdraw'">Chủ trọ sẽ không còn thấy yêu cầu này trong danh sách chờ xử lý.</p>
      <p v-else>Chủ trọ sẽ tiếp nhận yêu cầu. Đợt thuê chỉ kết thúc khi chủ trọ xác nhận thời điểm trả phòng thực tế.</p>
      <UiNotice v-if="actionError" tone="danger">{{ actionError }}</UiNotice>
      <template #actions>
        <UiButton variant="secondary" :disabled="busy" @click="change = undefined">Hủy</UiButton>
        <UiButton :loading="busy" :disabled="!representative || (change === 'withdraw' && !pending)" @click="submit">{{ change === 'withdraw' ? 'Xác nhận rút' : 'Gửi yêu cầu' }}</UiButton>
      </template>
    </UiDialog>
  </section>
</template>

<style scoped>
.checkout-panel { margin-top: 16px; }
.checkout-panel > * + * { margin-top: 12px; }
.checkout-list { margin-bottom: 0; padding: 0; list-style: none; }
.checkout-list li { border-top: 1px solid var(--color-border); padding: 12px 0; overflow-wrap: anywhere; }
.checkout-list li:last-child { padding-bottom: 0; }
.checkout-list li > div { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
.checkout-list p { margin: 6px 0 0; color: var(--color-text-secondary); }
</style>

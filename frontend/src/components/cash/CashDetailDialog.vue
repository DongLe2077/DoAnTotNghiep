<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import CashHistoryDelta from './CashHistoryDelta.vue'
import { eventPayload, localEventTime, useOwnerApi } from '@/composables/useOwnerApi'
import { toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import { cashActionLabel, cashMoney, cashTypeLabel, type CashHistory, type CashTransaction, type CashType } from '@/types/cash'
import type { PageResult } from '@/types/owner'

const props = defineProps<{ open: boolean; id: number | null }>()
const emit = defineEmits<{ close: []; changed: [] }>()
const client = useOwnerApi(), current = ref<CashTransaction | null>(null), histories = ref<CashHistory[]>([])
const loading = ref(false), error = ref(''), notice = ref(''), conflict = ref(false), uncertain = ref(false)
const page = ref(1), lastPage = ref(1), total = ref(0)
const mode = ref<'view' | 'edit' | 'delete' | 'revert'>('view'), review = ref(false)
const intendedHistoryId = ref<number | null>(null)
const form = reactive({ transaction_type: 'expense' as CashType, amount: '', occurred_at: '', description: '', reason: '' })
let sequence = 0, alive = true
const latest = computed(() => page.value === 1 ? histories.value[0] : null)
const canRevert = computed(() => Boolean(latest.value && current.value && latest.value.revision === current.value.revision))
const modeLabel = computed(() => ({ view: 'Chi tiết và nhật ký thu chi', edit: 'Sửa khoản thu chi', delete: 'Xóa khoản thu chi', revert: 'Hoàn tác thao tác mới nhất' })[mode.value])
const desired = computed(() => {
  if (!current.value) return null
  if (mode.value === 'edit') return { ...current.value, transaction_type: form.transaction_type, amount: form.amount, occurred_at: eventPayload(form.occurred_at), description: form.description }
  if (mode.value === 'delete' || mode.value === 'revert' && !latest.value?.before) return { ...current.value, deleted_at: localEventTime() }
  return latest.value?.before ?? current.value
})
async function load(nextPage = page.value) {
  if (!props.open || !props.id) return
  const seq = ++sequence; loading.value = true; error.value = ''; histories.value = []; current.value = null
  try {
    const result = await client.get<PageResult<CashHistory> & { current: CashTransaction }>(`/cash-transactions/${props.id}/history`, { page: nextPage, per_page: 10 })
    if (!alive || seq !== sequence || !props.open) return
    if (nextPage > result.meta.last_page) { await load(result.meta.last_page); return }
    histories.value = result.data; current.value = result.current
    page.value = result.meta.current_page; lastPage.value = result.meta.last_page; total.value = result.meta.total
  } catch (caught) { if (alive && seq === sequence && props.open) error.value = toApiError(caught).message }
  finally { if (alive && seq === sequence) loading.value = false }
}
watch(() => [props.open, props.id] as const, () => {
  sequence++; current.value = null; histories.value = []; mode.value = 'view'; review.value = false; notice.value = ''; conflict.value = false; uncertain.value = false; client.clearError()
  if (props.open) void load(1)
}, { immediate: true })
onBeforeUnmount(() => { alive = false; sequence++ })
function close() { if (!client.busy.value) emit('close') }
function start(next: 'edit' | 'delete' | 'revert') {
  if (!current.value || client.busy.value || next === 'revert' && !canRevert.value) return
  Object.assign(form, { transaction_type: current.value.transaction_type, amount: current.value.amount, occurred_at: current.value.occurred_at.replace(' ', 'T'), description: current.value.description, reason: '' })
  mode.value = next; review.value = false; conflict.value = false; uncertain.value = false; notice.value = ''; client.clearError()
  intendedHistoryId.value = next === 'revert' ? latest.value?.id ?? null : null
}
function prepare() { review.value = true; client.clearError() }
async function reloadForIntent() {
  await load(1)
  if (!current.value) return
  conflict.value = false; uncertain.value = false; review.value = false; client.clearError()
  notice.value = 'Đã tải phiên bản mới. Nội dung bạn đang nhập được giữ lại; hãy đối chiếu dữ liệu hiện tại và xem lại trước khi xác nhận.'
  if (mode.value === 'revert' && latest.value?.id !== intendedHistoryId.value) {
    mode.value = 'view'
    notice.value = 'Thao tác mới nhất đã thay đổi. Hãy xem nhật ký để xác định kết quả; chỉ chọn hoàn tác mới nếu bạn muốn đảo đúng thao tác đang hiển thị.'
  }
  if (current.value.is_deleted && mode.value !== 'revert') mode.value = 'view'
}
async function confirm() {
  if (!current.value || client.busy.value || conflict.value || uncertain.value || mode.value === 'view') return
  const id = current.value.id, token = current.value.version_token
  try {
    if (mode.value === 'edit') await client.write('patch', `/cash-transactions/${id}`, { version_token: token, transaction_type: form.transaction_type, amount: form.amount, occurred_at: eventPayload(form.occurred_at), description: form.description.trim(), reason: form.reason.trim() || null })
    else if (mode.value === 'delete') await client.write('delete', `/cash-transactions/${id}`, { version_token: token, reason: form.reason.trim() || null })
    else {
      if (!canRevert.value || !latest.value || latest.value.id !== intendedHistoryId.value) return
      await client.write('post', `/cash-transactions/${id}/revert`, { version_token: token, history_id: latest.value.id, reason: form.reason.trim() })
    }
    notice.value = 'Đã lưu thay đổi và ghi nhật ký.'; mode.value = 'view'; review.value = false; emit('changed'); await load(1)
  } catch (caught) {
    const status = toApiError(caught).status
    conflict.value = status === 409
    uncertain.value = status === 0 || status >= 500
    if (status === 422) review.value = false
  }
}
</script>

<template>
  <UiDialog :open="open" :title="review ? `Xem lại: ${modeLabel}` : modeLabel" @update:open="close">
    <UiNotice v-if="notice" tone="success">{{ notice }}</UiNotice>
    <p v-if="client.error.value" class="form-error" role="alert">{{ client.error.value }}</p>
    <UiNotice v-if="conflict || uncertain" tone="warning">{{ conflict ? 'Khoản này đã được thay đổi ở nơi khác.' : 'Kết quả thao tác chưa rõ do lỗi kết nối.' }} Tải phiên bản mới để đối chiếu trước khi tiếp tục. Nội dung đang nhập được giữ lại.<div class="button-row"><UiButton variant="secondary" :loading="loading" @click="reloadForIntent">Tải phiên bản mới để đối chiếu</UiButton></div></UiNotice>
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="error" kind="error" :description="error"><UiButton @click="load()">Thử tải lại chi tiết</UiButton></UiPageState>
    <template v-else-if="current">
      <template v-if="mode === 'view'">
        <div class="button-row"><UiBadge :tone="current.is_deleted ? 'neutral' : current.transaction_type === 'expense' ? 'warning' : 'success'">{{ current.is_deleted ? 'Đã xóa' : cashTypeLabel(current.transaction_type) }}</UiBadge><strong class="cash-detail-amount">{{ cashMoney(current.amount) }}</strong></div>
        <dl class="record-facts"><div><dt>Nội dung</dt><dd>{{ current.description }}</dd></div><div><dt>Phát sinh (giờ Việt Nam)</dt><dd>{{ formatDateTime(current.occurred_at) }}</dd></div><div><dt>Ghi nhận lần đầu</dt><dd>{{ formatDateTime(current.recorded_at) }}</dd></div><div v-if="current.deleted_at"><dt>Thời điểm xóa</dt><dd>{{ formatDateTime(current.deleted_at) }}</dd></div></dl>
        <div class="button-row cash-detail-actions"><UiButton v-if="!current.is_deleted" variant="secondary" @click="start('edit')">Sửa khoản này</UiButton><UiButton v-if="!current.is_deleted" variant="danger" @click="start('delete')">Xóa khoản này</UiButton><UiButton v-if="canRevert" variant="secondary" @click="start('revert')">{{ current.is_deleted ? 'Hoàn tác để khôi phục' : 'Hoàn tác mới nhất' }}</UiButton><UiButton v-else-if="page !== 1" variant="secondary" @click="load(1)">Về nhật ký mới nhất</UiButton></div>
        <h3 class="cash-history-title">Nhật ký chỉnh sửa</h3>
        <p v-if="!histories.length" class="help-text">Chưa có nhật ký. Các khoản cũ bắt đầu lưu nhật ký từ lần thay đổi đầu tiên sau nâng cấp.</p>
        <ol v-else class="cash-history"><li v-for="item in histories" :key="item.id"><header><strong>{{ cashActionLabel(item.action) }}</strong><span>{{ formatDateTime(item.created_at) }}</span></header><p>{{ item.actor?.username || 'Tài khoản đã ghi nhận' }}<span v-if="item.reason"> · {{ item.reason }}</span></p><details><summary>Xem thay đổi trước và sau</summary><CashHistoryDelta :before="item.before" :after="item.after" /></details></li></ol>
        <OwnerPager v-if="total > 0" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" />
      </template>
      <template v-else-if="review">
        <UiNotice :tone="mode === 'edit' ? 'info' : 'warning'">{{ mode === 'delete' ? 'Khoản sẽ không còn tính vào sổ thu chi. Bạn có thể khôi phục qua nhật ký.' : mode === 'revert' ? 'Chỉ đảo thao tác mới nhất. Thao tác này cũng được ghi vào nhật ký.' : 'Kiểm tra nội dung thay đổi trước khi lưu.' }}</UiNotice>
        <CashHistoryDelta v-if="desired" :before="current" :after="desired" />
        <p class="help-text">Lý do: {{ form.reason || 'Không có' }}</p>
      </template>
      <form v-else id="cash-change-form" class="form-grid" @submit.prevent="prepare">
        <fieldset class="form-grid" :disabled="client.busy.value || conflict || uncertain">
          <template v-if="mode === 'edit'"><label class="select-field">Loại khoản thu chi<select v-model="form.transaction_type" class="ui-select"><option value="expense">Chi phí</option><option value="other_income">Thu khác</option></select></label><OwnerField v-model="form.amount" label="Số tiền (VNĐ)" name="amount" :errors="client.errors.value" hint="Số dương, tối đa 2 số lẻ, không dùng dấu phân cách hàng nghìn." required :max-length="19" /><OwnerField v-model="form.occurred_at" label="Thời điểm phát sinh (giờ Việt Nam)" name="occurred_at" type="datetime-local" step="1" :max="localEventTime()" :errors="client.errors.value" required /><OwnerField v-model="form.description" label="Nội dung thu chi" name="description" :max-length="255" :errors="client.errors.value" required /></template>
          <template v-else><p>{{ current.description }} · <strong>{{ cashMoney(current.amount) }}</strong></p><UiNotice tone="warning">{{ mode === 'delete' ? 'Khoản xóa được giữ trong nhật ký để có thể khôi phục.' : latest?.before === null ? 'Hoàn tác tạo sẽ đưa khoản này sang đã xóa.' : latest?.before?.deleted_at ? 'Hoàn tác này sẽ đưa khoản về trạng thái đã xóa.' : current.is_deleted ? 'Khoản sẽ được khôi phục và tính lại vào sổ thu chi.' : 'Dữ liệu được đưa về trước thao tác mới nhất.' }}</UiNotice></template>
          <OwnerField v-model="form.reason" label="Lý do thay đổi" name="reason" :max-length="1000" :errors="client.errors.value" :required="mode === 'revert'" />
        </fieldset>
      </form>
    </template>
    <template #actions><UiButton variant="secondary" :disabled="client.busy.value" @click="close">Đóng</UiButton><template v-if="mode !== 'view' && current && !loading"><UiButton variant="secondary" :disabled="client.busy.value" @click="review ? review = false : mode = 'view'">{{ review ? 'Sửa lại' : 'Quay lại chi tiết' }}</UiButton><UiButton v-if="!review" type="submit" form="cash-change-form" :disabled="conflict || uncertain">Xem lại thay đổi</UiButton><UiButton v-else :variant="mode === 'edit' ? 'primary' : 'danger'" :disabled="conflict || uncertain" :loading="client.busy.value" @click="confirm">Xác nhận thay đổi</UiButton></template></template>
  </UiDialog>
</template>

<style scoped>.cash-detail-amount { font-size: 24px; }.cash-detail-actions { padding-bottom: 22px; border-bottom: 1px solid var(--color-border); }.cash-history-title { margin: 24px 0 12px; }.cash-history { list-style: none; padding: 0; margin: 0; }.cash-history > li { padding: 18px 0; border-bottom: 1px solid var(--color-border); overflow-wrap: anywhere; }.cash-history header { display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center; justify-content: space-between; }.cash-history header span, .cash-history p { font-size: 13px; color: var(--color-text-secondary); }.cash-history summary { padding: 10px 0; color: var(--color-primary); font-size: 14px; cursor: pointer; }</style>

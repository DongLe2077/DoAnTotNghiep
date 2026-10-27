<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import { eventPayload, localEventTime, useOwnerApi } from '@/composables/useOwnerApi'
import { useAuthStore } from '@/stores/auth'
import { cashMoney, cashTypeLabel, type CashCreation, type CashTransaction, type CashType } from '@/types/cash'
import { formatDateTime } from '@/lib/notifications'

const props = defineProps<{ open: boolean }>()
const emit = defineEmits<{ close: []; saved: [entry: CashTransaction, replayed: boolean] }>()
const auth = useAuthStore(), client = useOwnerApi()
const form = reactive({ transaction_type: 'expense' as CashType, amount: '', occurred_at: localEventTime(), description: '', reason: '' })
const review = ref(false), pending = ref<CashCreation | null>(null), storageError = ref(''), abandoning = ref(false)
const storageKey = computed(() => `rental.cash.pending.v1:${auth.user?.id ?? 'none'}`)
const restoredStorageKey = ref<string | null>(null)
function pendingKeys() { return Object.keys(localStorage).filter(key => key === storageKey.value || key.startsWith(`${storageKey.value}:`)).sort() }
const shown = computed(() => pending.value ?? ({ ...form, occurred_at: eventPayload(form.occurred_at), reason: form.reason.trim() || null }))
function restore() {
  pending.value = null; restoredStorageKey.value = null; storageError.value = ''; abandoning.value = false; client.clearError()
  try {
    restoredStorageKey.value = pendingKeys()[0] ?? null
    const raw = restoredStorageKey.value ? localStorage.getItem(restoredStorageKey.value) : null
    if (raw) {
      const value: unknown = JSON.parse(raw)
      if (!value || typeof value !== 'object') throw new Error()
      const item = value as Record<string, unknown>
      if (item.transaction_type !== 'expense' && item.transaction_type !== 'other_income') throw new Error()
      if (typeof item.idempotency_key !== 'string' || !/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/.test(item.idempotency_key)
        || typeof item.amount !== 'string' || !/^\d{1,16}(?:\.\d{1,2})?$/.test(item.amount)
        || typeof item.occurred_at !== 'string' || !/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(item.occurred_at)
        || typeof item.description !== 'string' || item.description.length > 255
        || !(item.reason === null || typeof item.reason === 'string' && item.reason.length <= 1000)) throw new Error()
      pending.value = { idempotency_key: item.idempotency_key, transaction_type: item.transaction_type,
        amount: item.amount, occurred_at: item.occurred_at, description: item.description, reason: item.reason as string | null }
    }
  } catch { storageError.value = 'Không đọc được yêu cầu đang lưu trên trình duyệt. Hãy đối chiếu sổ thu chi trước khi bắt đầu khoản mới.' }
  review.value = Boolean(pending.value)
  if (pending.value) Object.assign(form, { transaction_type: pending.value.transaction_type, amount: pending.value.amount, occurred_at: pending.value.occurred_at.replace(' ', 'T'), description: pending.value.description, reason: pending.value.reason ?? '' })
  else Object.assign(form, { transaction_type: 'expense', amount: '', occurred_at: localEventTime(), description: '', reason: '' })
}
watch(() => props.open, open => { if (open) restore() }, { immediate: true })
watch(() => auth.user?.id, () => { pending.value = null; review.value = false; storageError.value = ''; emit('close') }, { flush: 'sync' })
function close() { if (!client.busy.value) emit('close') }
function prepare() {
  client.clearError()
  const errors: Record<string, string[]> = {}
  if (!/^\d{1,16}(?:\.\d{1,2})?$/.test(form.amount) || /^0+(?:\.0{1,2})?$/.test(form.amount)) errors.amount = ['Nhập số tiền dương, tối đa 16 chữ số nguyên và 2 chữ số lẻ, không dùng dấu phân cách hàng nghìn.']
  if (!form.description.trim()) errors.description = ['Nhập nội dung thu chi.']
  if (Object.keys(errors).length) { client.errors.value = errors; client.error.value = 'Vui lòng kiểm tra lại thông tin đã nhập.'; return }
  review.value = true
}
async function send() {
  if (client.busy.value || storageError.value) return
  client.clearError()
  if (!pending.value) {
    pending.value = { idempotency_key: crypto.randomUUID(), transaction_type: form.transaction_type, amount: form.amount,
      occurred_at: eventPayload(form.occurred_at), description: form.description.trim(), reason: form.reason.trim() || null }
  }
  try {
    // Each request has its own storage entry, so concurrent tabs never overwrite one another's recovery data.
    for (const key of pendingKeys()) {
      const existing: unknown = JSON.parse(localStorage.getItem(key) ?? 'null')
      if (!existing || typeof existing !== 'object' || !('idempotency_key' in existing) || existing.idempotency_key !== pending.value.idempotency_key) {
        restore(); client.error.value = 'Có yêu cầu khác đang chờ trên trình duyệt. Hãy đối chiếu yêu cầu đó trước khi ghi thêm khoản mới.'; return
      }
    }
    localStorage.setItem(`${storageKey.value}:${pending.value.idempotency_key}`, JSON.stringify(pending.value))
  }
  catch { storageError.value = 'Trình duyệt chưa lưu được yêu cầu để chống gửi trùng. Chưa gửi khoản này. Hãy cho phép lưu trữ rồi thử lại.'; return }
  try {
    const sent = pending.value
    const result = await client.write<{ data: CashTransaction; replayed: boolean }>('post', '/cash-transactions', sent)
    try { clearStored(sent.idempotency_key) } catch { /* Replaying this retained request remains safe. */ }
    pending.value = null; review.value = false
    emit('saved', result.data, result.replayed); emit('close')
  } catch (error) {
    // Only a validation rejection establishes that no entry was created; unknown results keep the exact request.
    if (error && typeof error === 'object' && 'status' in error && error.status === 422) {
      try { if (pending.value) clearStored(pending.value.idempotency_key); pending.value = null; review.value = false }
      catch { storageError.value = 'Chưa xóa được yêu cầu bị từ chối trên trình duyệt. Hãy tải lại để kiểm tra.' }
    }
  }
}
function clearStored(expectedKey: string) {
  for (const key of [storageKey.value, `${storageKey.value}:${expectedKey}`]) {
    const stored = localStorage.getItem(key)
    if (stored && (JSON.parse(stored) as { idempotency_key?: unknown }).idempotency_key === expectedKey) localStorage.removeItem(key)
  }
}
function abandon() {
  if (client.busy.value) return
  try { if (pending.value) clearStored(pending.value.idempotency_key); else if (restoredStorageKey.value) localStorage.removeItem(restoredStorageKey.value); pending.value = null; storageError.value = ''; abandoning.value = false; review.value = false; client.clearError() }
  catch { storageError.value = 'Chưa xóa được yêu cầu lưu trên trình duyệt. Vui lòng kiểm tra quyền lưu trữ.' }
}
</script>

<template>
  <UiDialog :open="open" :title="abandoning ? 'Đối chiếu yêu cầu trước khi bỏ' : review ? 'Xem lại khoản thu chi' : 'Thêm khoản thu chi'" @update:open="close">
    <p v-if="client.error.value" class="form-error" role="alert">{{ client.error.value }}</p>
    <UiNotice v-if="storageError" tone="warning">{{ storageError }}</UiNotice>
    <template v-if="abandoning">
      <UiNotice tone="warning">Khoản này có thể đã được máy chủ ghi nhận. Hãy xem sổ thu chi và đối chiếu thời gian, số tiền, nội dung trước khi bỏ yêu cầu. Bỏ yêu cầu chỉ xóa bản chờ trên trình duyệt.</UiNotice>
      <p class="help-text">Nếu chưa rõ kết quả, quay lại và gửi lại yêu cầu cũ để nhận đúng khoản đã lưu.</p>
    </template>
    <template v-else-if="review">
      <UiNotice v-if="pending" tone="warning" title="Có yêu cầu đang chờ xác định kết quả">Thông tin được giữ nguyên để gửi lại an toàn. Bạn có thể đóng và mở lại, hoặc tải lại trang; không cần nhập một khoản mới.</UiNotice>
      <dl class="record-facts review-box"><div><dt>Loại</dt><dd>{{ cashTypeLabel(shown.transaction_type) }}</dd></div><div><dt>Số tiền</dt><dd>{{ cashMoney(shown.amount) }}</dd></div><div><dt>Phát sinh (giờ Việt Nam)</dt><dd>{{ formatDateTime(shown.occurred_at) }}</dd></div><div><dt>Nội dung</dt><dd>{{ shown.description }}</dd></div><div><dt>Ghi chú ghi nhận</dt><dd>{{ shown.reason || 'Không có' }}</dd></div></dl>
      <p class="help-text">Tiền đã thu từ hóa đơn tự xuất hiện trong sổ. Không ghi lại lần thu hóa đơn thành thu khác.</p>
    </template>
    <form v-else id="cash-create-form" class="form-grid" @submit.prevent="prepare">
      <fieldset :disabled="client.busy.value || Boolean(storageError)" class="form-grid">
        <label class="select-field">Loại khoản thu chi<select v-model="form.transaction_type" class="ui-select" name="transaction_type"><option value="expense">Chi phí</option><option value="other_income">Thu khác</option></select></label>
        <OwnerField v-model="form.amount" label="Số tiền (VNĐ)" name="amount" :errors="client.errors.value" hint="Nhập số dương, không dùng dấu phân cách hàng nghìn. Ví dụ: 150000 hoặc 150000.50." required :max-length="19" />
        <OwnerField v-model="form.occurred_at" label="Thời điểm phát sinh (giờ Việt Nam)" name="occurred_at" type="datetime-local" step="1" :max="localEventTime()" :errors="client.errors.value" required />
        <OwnerField v-model="form.description" label="Nội dung thu chi" name="description" :errors="client.errors.value" :max-length="255" required />
        <OwnerField v-model="form.reason" label="Ghi chú ghi nhận" name="reason" :errors="client.errors.value" :max-length="1000" />
      </fieldset>
    </form>
    <template #actions>
      <template v-if="abandoning"><UiButton variant="secondary" @click="abandoning = false">Quay lại</UiButton><UiButton variant="danger" @click="abandon">Đã đối chiếu, bỏ yêu cầu này</UiButton></template>
      <template v-else><UiButton variant="secondary" :disabled="client.busy.value" @click="close">Đóng</UiButton><UiButton v-if="review && !pending" variant="secondary" @click="review = false">Sửa lại</UiButton><UiButton v-if="pending || storageError" variant="ghost" :disabled="client.busy.value" @click="abandoning = true">Đối chiếu và bỏ yêu cầu</UiButton><UiButton v-if="!review && !storageError" type="submit" form="cash-create-form">Xem lại khoản thu chi</UiButton><UiButton v-else-if="review && pending && storageError" :loading="client.busy.value" @click="storageError = ''; send()">Thử lưu và gửi lại yêu cầu</UiButton><UiButton v-else-if="review" :loading="client.busy.value" @click="send">{{ pending ? 'Gửi lại yêu cầu đang chờ' : 'Xác nhận ghi nhận' }}</UiButton></template>
    </template>
  </UiDialog>
</template>

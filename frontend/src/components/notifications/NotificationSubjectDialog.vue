<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { api, ApiError, toApiError } from '@/lib/api'
import { notificationTarget, formatDateTime } from '@/lib/notifications'
import { formatMoney } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import type { AppNotification } from '@/types/notifications'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiPageState from '@/components/ui/UiPageState.vue'

const props = defineProps<{ open: boolean; notification: AppNotification | null }>()
const emit = defineEmits<{ 'update:open': [value: boolean] }>()
const auth = useAuthStore()
const loading = ref(false)
const error = ref('')
const errorStatus = ref(0)
const subject = ref<Record<string, unknown> | null>(null)
const kind = ref<'invoice' | 'maintenance' | 'announcement' | null>(null)
const fullPage = computed(() => {
  const id = subject.value?.id
  return auth.user && kind.value && typeof id === 'number' && Number.isSafeInteger(id) && id > 0
    ? `/${auth.user.role}/${kind.value === 'invoice' ? 'invoices' : kind.value === 'maintenance' ? 'maintenance' : 'announcements'}/${id}` : null
})
let sequence = 0
let controller: AbortController | null = null
const title = computed(() => subject.value ? kind.value === 'invoice' ? 'Hóa đơn liên quan' : kind.value === 'announcement' ? 'Thông báo từ chủ trọ' : 'Yêu cầu sửa chữa liên quan' : 'Chi tiết thông báo')
const announcement = computed(() => subject.value && kind.value === 'announcement' ? subject.value : null)
const invoice = computed(() => subject.value && kind.value === 'invoice' ? subject.value : null)
const report = computed(() => subject.value && kind.value === 'maintenance' ? subject.value : null)
const roomNumber = computed(() => {
  const room = subject.value?.room
  return room && typeof room === 'object' && 'room_number' in room ? String(room.room_number) : '—'
})
function text(value: unknown) { return typeof value === 'string' || typeof value === 'number' ? String(value) : '—' }
function money(value: unknown) { return typeof value === 'string' ? formatMoney(value) : '—' }
const statusLabel = computed(() => ({ submitted: 'Đã gửi', in_progress: 'Đang xử lý', resolved: 'Đã xử lý' })[text(report.value?.status) as 'submitted' | 'in_progress' | 'resolved'] ?? 'Đang cập nhật')

async function load() {
  const current = ++sequence
  controller?.abort()
  subject.value = null; error.value = ''; errorStatus.value = 0; kind.value = null
  if (!props.open || !props.notification || !auth.user) { loading.value = false; return }
  const ownerKey = `${auth.user.id}:${auth.user.role}`
  const target = notificationTarget(props.notification, auth.user.role)
  if (!target) { error.value = 'Thông báo này chưa có nội dung liên quan để mở.'; errorStatus.value = 404; loading.value = false; return }
  kind.value = target.kind
  const signal = new AbortController()
  controller = signal
  loading.value = true
  try {
    const response = await api.get<{ data: unknown }>(target.path, { signal: signal.signal })
    if (current !== sequence || !props.open || !auth.user || ownerKey !== `${auth.user.id}:${auth.user.role}`) return
    const data = response.data.data
    if (!data || typeof data !== 'object' || Array.isArray(data)) throw new ApiError(502, 'Nội dung nhận được chưa hợp lệ. Vui lòng thử lại.')
    subject.value = data as Record<string, unknown>
  } catch (caught) {
    if (current !== sequence || !props.open) return
    const normalized = toApiError(caught)
    errorStatus.value = normalized.status
    error.value = normalized.status === 403 || normalized.status === 404
      ? 'Nội dung đã thay đổi, không còn tồn tại hoặc bạn không còn quyền xem.' : normalized.message
  } finally {
    if (current === sequence) loading.value = false
  }
}

watch(() => [props.open, props.notification?.id], () => { void load() }, { immediate: true })
watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', () => {
  sequence++
  controller?.abort()
  subject.value = null; error.value = ''; errorStatus.value = 0; kind.value = null; loading.value = false
  emit('update:open', false)
}, { flush: 'sync' })
function revalidate() { if (document.visibilityState === 'visible' && props.open) void load() }
window.addEventListener('focus', revalidate)
onBeforeUnmount(() => { sequence++; controller?.abort(); window.removeEventListener('focus', revalidate) })
</script>

<template>
  <UiDialog :open="open" :title="title" description="Nội dung được tải lại theo quyền truy cập hiện tại của bạn." @update:open="emit('update:open', $event)">
    <UiPageState v-if="loading" kind="loading" title="Đang mở nội dung…" />
    <UiPageState v-else-if="error" :kind="errorStatus === 403 ? 'forbidden' : errorStatus === 404 ? 'not-found' : 'error'" :title="errorStatus === 403 || errorStatus === 404 ? 'Nội dung không còn khả dụng' : 'Chưa tải được nội dung'" :description="error"><template #actions><UiButton variant="secondary" @click="load">Thử lại</UiButton></template></UiPageState>
    <section v-else-if="announcement" aria-label="Thông báo chung"><h3 class="subject-heading">{{ text(announcement.title) }}</h3><p class="subject-description">{{ text(announcement.body) }}</p><p class="subject-note">Gửi {{ formatDateTime(text(announcement.created_at)) }}</p></section>
    <section v-else-if="invoice" aria-label="Thông tin hóa đơn">
      <p class="subject-heading">{{ text(invoice.invoice_number) }}</p>
      <dl class="subject-summary"><div><dt>Phòng</dt><dd>{{ roomNumber }}</dd></div><div><dt>Kỳ hóa đơn</dt><dd>{{ text(invoice.billing_month) }}</dd></div><div><dt>Tổng tiền hóa đơn</dt><dd class="ui-money">{{ money(invoice.total_amount) }}</dd></div><div><dt>Đã thanh toán</dt><dd class="ui-money">{{ money(invoice.paid_amount) }}</dd></div><div class="outstanding"><dt>{{ auth.user?.role === 'owner' ? 'Còn cần thu' : 'Còn cần thanh toán' }}</dt><dd class="ui-money">{{ money(invoice.outstanding_amount) }}</dd></div></dl>
      <p class="subject-note">{{ auth.user?.role === 'owner' ? 'Số tiền còn cần thu được cập nhật theo các khoản thanh toán đã ghi nhận.' : 'Thông tin thanh toán được chủ trọ ghi nhận. Nếu cần đối chiếu, bạn hãy liên hệ chủ trọ.' }}</p>
    </section>
    <section v-else-if="report" aria-label="Thông tin sửa chữa">
      <UiBadge :tone="report.status === 'resolved' ? 'success' : 'warning'">{{ statusLabel }}</UiBadge>
      <h3 class="subject-heading">{{ text(report.title) }}</h3>
      <p class="subject-description">{{ text(report.description) }}</p>
      <dl class="subject-summary"><div><dt>Phòng</dt><dd>{{ roomNumber }}</dd></div><div><dt>Ngày gửi</dt><dd>{{ formatDateTime(text(report.submitted_at)) }}</dd></div><div><dt>Cập nhật mới nhất</dt><dd>{{ formatDateTime(text(report.updated_at)) }}</dd></div></dl>
      <div v-if="report.resolution" class="resolution"><h4>Ghi chú và kết quả xử lý</h4><p>{{ text(report.resolution) }}</p></div>
    </section>
    <template #actions><RouterLink v-if="fullPage" class="record-link" :to="fullPage" @click="emit('update:open', false)">Mở trang chi tiết →</RouterLink><UiButton variant="secondary" @click="emit('update:open', false)">Đóng</UiButton></template>
  </UiDialog>
</template>

<style scoped>
.subject-heading { margin: 16px 0; font-size: 20px; font-weight: 600; overflow-wrap: anywhere; }
.subject-summary { display: grid; gap: 16px; margin: 24px 0; }
.subject-summary > div { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 16px; font-size: 14px; }
dt { color: var(--color-text-secondary); }
dd { margin: 0; overflow-wrap: anywhere; }
.outstanding { border-top: 1px solid var(--color-border); padding-top: 16px; font-weight: 600; }
.subject-note { padding: 16px; border-radius: 10px; background: var(--color-bg); color: var(--color-text-secondary); font-size: 13px; }
.subject-description, .resolution p { white-space: pre-wrap; overflow-wrap: anywhere; font-size: 14px; }
.resolution { padding: 16px; border-radius: 10px; background: var(--color-primary-soft); }
.resolution h4 { margin: 0; font-size: 14px; }
.resolution p { margin: 8px 0 0; }
</style>

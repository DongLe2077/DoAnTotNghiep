<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import OccupancyPicker from '@/components/billing/OccupancyPicker.vue'
import InvoiceLines from '@/components/billing/InvoiceLines.vue'
import { localEventTime, useOwnerApi } from '@/composables/useOwnerApi'
import { registerReloadBlocker } from '@/lib/reload-safety'
import { toApiError } from '@/lib/api'
import { dueDateText } from '@/lib/invoice-controls'
import { previousMonth } from '@/lib/billing'
import { formatMoney } from '@/lib/format'
import { formatDateTime } from '@/lib/notifications'
import type { Occupancy, ServicePeriod } from '@/types/owner'
import type { Invoice, InvoiceKind, InvoicePreview } from '@/types/billing'
const route = useRoute(), router = useRouter(), client = useOwnerApi()
const occupancyId = ref<number | null>(Number(route.query.occupancy_id) > 0 ? Number(route.query.occupancy_id) : null), month = ref(typeof route.query.billing_month === 'string' && /^\d{4}-(0[1-9]|1[0-2])$/.test(route.query.billing_month) ? route.query.billing_month : previousMonth()), dueDate = ref(''), preview = ref<InvoicePreview | null>(null), occupancy = ref<Occupancy | null>(null), reviewing = ref(false), confirm = ref(false), message = ref(''), representative = ref('')
const kind = ref<InvoiceKind>('monthly')
const description = ref('')
const amount = ref('')
const excludedServiceIds = ref<number[]>([])
const oneTime = computed(() => kind.value === 'one_time')
const hasDetails = computed(() => !oneTime.value || Boolean(description.value.trim() && amount.value))
const previewHeading = ref<HTMLElement | null>(null)
const previewError = ref<HTMLElement | null>(null)
const statusMessage = ref<HTMLElement | null>(null)
let alive = true, sequence = 0, detailSequence = 0
const releaseReloadGuard = registerReloadBlocker(() => !!occupancyId.value || !!preview.value || client.busy.value)
onBeforeUnmount(releaseReloadGuard)
const candidates = computed(() => { const end = occupancy.value?.ended_at; return end ? occupancy.value?.members?.filter(item => item.moved_in_at < end && (!item.moved_out_at || item.moved_out_at >= end)) ?? [] : [] })
const serviceOptions = computed(() => {
  const stay = occupancy.value
  if (!stay || !month.value) return []
  const monthStart = month.value + '-01 00:00:00'
  const start = stay.started_at > monthStart ? stay.started_at : monthStart
  const now = localEventTime().replace('T', ' ')
  const cutoff = stay.ended_at && stay.ended_at < now ? stay.ended_at : now
  if (start >= cutoff) return []

  const services = new Map<number, ServicePeriod>()
  for (const period of stay.services ?? []) {
    if (period.effective_from.slice(0, 7) > month.value || period.effective_from >= cutoff) continue
    if (period.effective_until && period.effective_until <= start) continue
    if (!services.has(period.service_id)) services.set(period.service_id, period)
  }
  return [...services.values()]
})
const laterServices = computed(() => {
  if (!month.value) return []
  const eligibleIds = new Set(serviceOptions.value.map(service => service.service_id))
  return (occupancy.value?.services ?? []).filter(period =>
    period.is_active && period.effective_from.slice(0, 7) > month.value && !eligibleIds.has(period.service_id),
  )
})
const selectedServiceIds = computed(() => serviceOptions.value
  .map(service => service.service_id)
  .filter(id => !excludedServiceIds.value.includes(id)))

watch([occupancyId, month, kind, description, amount, excludedServiceIds], () => { sequence++; preview.value = null; reviewing.value = false; confirm.value = false; message.value = ''; client.clearError() })
watch([occupancyId, month], () => {
  detailSequence++
  occupancy.value = null
  excludedServiceIds.value = []
  representative.value = ''
}, { flush: 'sync' })

async function loadSelectedOccupancy(selection: Occupancy) {
  const id = selection.id
  const current = ++detailSequence
  try {
    const result = await client.get<{ data: Occupancy }>(`/occupancies/${id}`)
    if (alive && current === detailSequence && id === occupancyId.value) occupancy.value = result.data
  } catch (caught) {
    if (alive && current === detailSequence) message.value = toApiError(caught).message
  }
}
function toggleService(id: number) {
  excludedServiceIds.value = excludedServiceIds.value.includes(id)
    ? excludedServiceIds.value.filter(excluded => excluded !== id)
    : [...excludedServiceIds.value, id]
}

function invoicePath(id: number, invoiceKind: InvoiceKind) {
  return `/occupancies/${id}/invoices${invoiceKind === 'one_time' ? '/one-time' : ''}`
}

function oneTimeFields() {
  return { description: description.value.trim(), amount: amount.value }
}

type PendingCharge = { draft: string; key: string }
const chargeStorageKey = 'rentbase:one-time-invoice'
let pendingCharge: PendingCharge | null = null
function chargeKey(draft: string) {
  if (pendingCharge?.draft === draft) return pendingCharge.key
  try {
    const saved = JSON.parse(sessionStorage.getItem(chargeStorageKey) ?? 'null') as PendingCharge | null
    if (saved?.draft === draft && typeof saved.key === 'string') {
      pendingCharge = saved
      return saved.key
    }
  } catch { /* The active form still retains its request key. */ }
  const key = crypto.randomUUID()
  pendingCharge = { draft, key }
  try { sessionStorage.setItem(chargeStorageKey, JSON.stringify(pendingCharge)) } catch { /* The active form still retains its request key. */ }
  return key
}

async function calculate() {
  if (!occupancyId.value || !occupancy.value || !month.value || !hasDetails.value || client.busy.value) return
  const current = ++sequence
  preview.value = null
  reviewing.value = false
  confirm.value = false
  message.value = ''
  try {
    const result = await client.write<{ data: InvoicePreview }>('post', `${invoicePath(occupancyId.value, kind.value)}/preview`, {
      billing_month: month.value, ...(oneTime.value ? oneTimeFields() : { service_ids: selectedServiceIds.value }),
    })
    if (alive && current === sequence) preview.value = result.data
  } catch { /* The form displays the API error. */ }
  await nextTick()
  if (!alive || current !== sequence) return
  const target = previewHeading.value ?? previewError.value
  target?.focus({ preventScroll: true })
  target?.scrollIntoView({ block: 'start' })
}

async function setReviewing(value: boolean) {
  reviewing.value = value
  confirm.value = false
  await nextTick()
  previewHeading.value?.focus({ preventScroll: true })
  previewHeading.value?.scrollIntoView({ block: 'start' })
}
async function issue() {
  if (!preview.value?.can_issue || !confirm.value || !reviewing.value || client.busy.value) return
  const { occupancy_id: id, billing_month, invoice_kind, preview_token, service_ids } = preview.value
  const fields = oneTimeFields()
  const extra = invoice_kind === 'one_time'
    ? { ...fields, idempotency_key: chargeKey(JSON.stringify([id, billing_month, fields])) }
    : { service_ids }
  try {
    const result = await client.write<{ data: Invoice }>('post', invoicePath(id, invoice_kind), {
      billing_month, preview_token, due_date: dueDate.value || null, ...extra,
    })
    if (invoice_kind === 'one_time') {
      pendingCharge = null
      try { sessionStorage.removeItem(chargeStorageKey) } catch { /* The request has been confirmed. */ }
    }
    await router.push(`/owner/invoices/${result.data.id}?issued=1`)
  } catch (caught) {
    reviewing.value = false
    confirm.value = false
    if (toApiError(caught).status === 409) {
      preview.value = null
      message.value = invoice_kind === 'one_time'
        ? toApiError(caught).message
        : 'Dữ liệu tính tiền đã thay đổi hoặc tháng đã được phát hành. Hãy xem trước lại hoặc kiểm tra danh sách hóa đơn.'
      client.clearError()
    }
    await nextTick()
    const target = previewError.value ?? statusMessage.value
    target?.focus({ preventScroll: true })
    target?.scrollIntoView({ block: 'start' })
  }
}
async function supplement() { if (!occupancyId.value || !representative.value) return; try { await client.write('post', `/occupancies/${occupancyId.value}/billing-representative`, { tenant_stay_id: Number(representative.value) }); const result = await client.get<{ data: Occupancy }>(`/occupancies/${occupancyId.value}`); occupancy.value = result.data; message.value = 'Đã bổ sung đại diện lịch sử. Hãy xem trước lại hóa đơn.'; preview.value = null } catch { /* Preserve selection and display backend validation. */ } }
onBeforeUnmount(() => { alive = false; sequence++; detailSequence++ })
</script>
<template>
  <section class="management-page">
    <RouterLink class="back-link" to="/owner/invoices">← Danh sách hóa đơn</RouterLink>
    <header class="management-header">
      <div>
        <h1>Lập hóa đơn</h1>
        <p>Chọn loại hóa đơn, tháng tính tiền và đợt thuê.</p>
      </div>
    </header>

    <div v-if="message" ref="statusMessage" class="issue-feedback" tabindex="-1">
      <UiNotice tone="warning">{{ message }}</UiNotice>
    </div>
    <form class="record-card form-grid" @submit.prevent="calculate">
      <fieldset class="form-grid" :disabled="client.busy.value || reviewing">
        <label class="select-field">
          Loại hóa đơn
          <select v-model="kind" class="ui-select">
            <option value="monthly">Hóa đơn tháng</option>
            <option value="one_time">Khoản thu riêng</option>
          </select>
        </label>
        <OwnerField v-model="month" label="Tháng tính tiền" name="billing_month" type="month" required :errors="client.errors.value" />
        <OccupancyPicker v-model="occupancyId" :billing-month="month" @choose="loadSelectedOccupancy" />
        <template v-if="oneTime">
          <OwnerField v-model="description" label="Nội dung khoản thu" name="description" :max-length="255" required :errors="client.errors.value" />
          <OwnerField v-model="amount" label="Số tiền (đ)" name="amount" type="number" min="0.01" step="0.01" required :errors="client.errors.value" />
        </template>
        <fieldset v-else-if="occupancy" class="remote-picker">
          <legend>Dịch vụ thu trong hóa đơn</legend>
          <template v-if="serviceOptions.length">
            <p id="invoice-services-help" class="help-text">Tiền phòng được tính tự động. Chỉ các dịch vụ được tích mới được thu trong hóa đơn tháng này.</p>
            <div class="picker-options invoice-services" aria-describedby="invoice-services-help">
              <label v-for="service in serviceOptions" :key="service.service_id">
                <input type="checkbox" :checked="selectedServiceIds.includes(service.service_id)" @change="toggleService(service.service_id)" />
                {{ service.service?.name ?? ('Dịch vụ #' + service.service_id) }}
              </label>
            </div>
          </template>
          <p v-else class="help-text">Đợt thuê chưa có dịch vụ áp dụng trong tháng này.</p>
          <div v-if="laterServices.length" class="later-services">
            <strong>Dịch vụ có hiệu lực sau tháng đã chọn</strong>
            <ul>
              <li v-for="service in laterServices" :key="service.id">
                <span>{{ service.service?.name ?? ('Dịch vụ #' + service.service_id) }}</span>
                <span class="help-text"> · Từ {{ formatDateTime(service.effective_from) }}</span>
              </li>
            </ul>
          </div>
          <RouterLink v-if="occupancy.is_active" class="record-link" :to="'/owner/stays/' + occupancy.id">Áp dụng dịch vụ cho đợt thuê</RouterLink>
        </fieldset>
      </fieldset>
      <label>
        Hạn thanh toán (không bắt buộc)
        <input v-model="dueDate" type="date" class="ui-input" :min="localEventTime().slice(0, 10)" :disabled="reviewing || client.busy.value" />
        <span class="help-text">Bạn có thể để trống. Hóa đơn chỉ quá hạn sau ngày đã chọn.</span>
      </label>
      <p v-if="client.errors.value.due_date" class="field-error">{{ client.errors.value.due_date[0] }}</p>
      <p v-if="client.error.value" ref="previewError" class="form-error issue-feedback" role="alert" tabindex="-1">{{ client.error.value }}</p>
      <div class="button-row">
        <UiButton type="submit" :loading="client.busy.value" :disabled="!occupancyId || !occupancy || !month || !hasDetails || reviewing">Xem trước hóa đơn</UiButton>
        <RouterLink v-if="occupancyId && !oneTime" class="record-link" :to="{ path: '/owner/meters', query: { occupancy_id: occupancyId, billing_month: month } }">Ghi / kiểm tra điện nước</RouterLink>
      </div>
    </form>

    <section v-if="occupancy?.ended_at && !occupancy.representatives?.length" class="record-card issue-panel">
      <h2>Bổ sung đại diện lịch sử</h2>
      <p class="help-text">Chọn người còn cư trú lúc trả phòng.</p>
      <label class="select-field">
        Người đại diện để lập hóa đơn
        <select v-model="representative" class="ui-select">
          <option value="">Chọn thành viên</option>
          <option v-for="member in candidates" :key="member.id" :value="String(member.id)">{{ member.tenant?.full_name }}</option>
        </select>
      </label>
      <UiButton variant="secondary" :loading="client.busy.value" :disabled="!representative" @click="supplement">Xác nhận bổ sung đại diện</UiButton>
    </section>

    <section v-if="preview" class="record-card issue-panel" aria-labelledby="invoice-preview-title">
      <header>
        <div>
          <h2 id="invoice-preview-title" ref="previewHeading" class="issue-feedback" tabindex="-1">{{ reviewing ? 'Xác nhận phát hành hóa đơn' : 'Bản xem trước hóa đơn' }}</h2>
          <p class="help-text">
            Phòng {{ occupancy?.room.room_number }} · {{ preview.billing_month }}
            <template v-if="preview.invoice_kind === 'monthly'"><br />{{ formatDateTime(preview.period_start) }} → {{ formatDateTime(preview.period_end) }}</template>
          </p>
        </div>
      </header>

      <template v-if="preview.existing_invoice_id">
        <UiNotice tone="warning" title="Kỳ này đã có hóa đơn">Mở hóa đơn đã phát hành để xem số tiền, điều chỉnh hoặc tải PDF.</UiNotice>
        <div class="button-row">
          <RouterLink class="record-link" :to="`/owner/invoices/${preview.existing_invoice_id}`">Xem hóa đơn đã phát hành</RouterLink>
        </div>
      </template>
      <template v-else>
        <UiNotice v-if="preview.blockers.length" tone="warning" title="Cần xử lý trước khi phát hành">
          <ul><li v-for="blocker in preview.blockers" :key="blocker">{{ blocker }}</li></ul>
          <RouterLink v-if="occupancyId && preview.invoice_kind === 'monthly'" class="record-link" :to="{ path: '/owner/meters', query: { occupancy_id: occupancyId, billing_month: month } }">Mở chỉ số điện nước của đợt thuê</RouterLink>
        </UiNotice>
        <UiNotice v-if="preview.warnings.length" tone="warning">
          <ul><li v-for="warning in preview.warnings" :key="warning">{{ warning }}</li></ul>
        </UiNotice>
        <template v-if="preview.can_issue">
          <dl class="record-facts review-box">
            <template v-if="preview.rounding_amount !== '0.00'">
              <div><dt>Tổng trước khi làm tròn</dt><dd>{{ formatMoney(preview.subtotal_amount) }}</dd></div>
              <div><dt>Chênh lệch làm tròn đến 1.000 đ</dt><dd>{{ formatMoney(preview.rounding_amount) }}</dd></div>
            </template>
            <div><dt>Tổng hóa đơn dự kiến</dt><dd><strong>{{ formatMoney(preview.total_amount) }}</strong></dd></div>
            <div><dt>Hạn thanh toán</dt><dd>{{ dueDateText(dueDate) }}</dd></div>
            <div><dt>Quyền xem người thuê</dt><dd>{{ preview.recipient_user_id ? 'Sẽ cấp cho người đại diện' : 'Chưa cấp quyền người thuê' }}</dd></div>
          </dl>
          <div v-if="!reviewing" class="button-row">
            <UiButton :disabled="client.busy.value" @click="setReviewing(true)">Kiểm tra và phát hành</UiButton>
          </div>
          <template v-else>
            <p v-if="preview.invoice_kind === 'monthly'" class="help-text">Phát hành sẽ khóa các kỳ chỉ số trong tháng.</p>
            <label class="billing-confirm">
              <input v-model="confirm" type="checkbox" />
              {{ preview.invoice_kind === 'one_time' ? 'Tôi đã kiểm tra đợt thuê, nội dung và số tiền.' : 'Tôi đã kiểm tra thời gian, chỉ số và các khoản tính tiền.' }}
            </label>
            <div class="button-row">
              <UiButton variant="secondary" :disabled="client.busy.value" @click="setReviewing(false)">Quay lại kiểm tra</UiButton>
              <UiButton :loading="client.busy.value" :disabled="!confirm" @click="issue">Xác nhận phát hành hóa đơn</UiButton>
            </div>
          </template>
        </template>
        <InvoiceLines :items="preview.items" />
      </template>
    </section>
  </section>
</template>

<style scoped>
.issue-panel { display: grid; gap: 16px; margin-top: 24px; }
.issue-panel > header { margin-bottom: 0; }
.issue-feedback { scroll-margin-top: calc(var(--pwa-notice-height, 0px) + 94px); }
.invoice-services { max-height: none; margin-bottom: 8px; }
.later-services { margin: 8px 0 12px; padding: 12px; border: 1px solid var(--color-border); border-radius: 8px; }
.later-services ul { margin: 4px 0 0; padding-inline-start: 20px; }
.billing-confirm { display: flex; gap: 12px; align-items: flex-start; font-size: 14px; }
.billing-confirm input { width: 20px; height: 20px; accent-color: var(--color-primary); flex-shrink: 0; }
</style>

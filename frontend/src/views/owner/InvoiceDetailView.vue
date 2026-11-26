<script setup lang="ts">
import ScheduledDeleteButton from '@/components/owner/ScheduledDeleteButton.vue'
import PdfDownloadButton from '@/components/billing/PdfDownloadButton.vue'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import InvoiceLines from '@/components/billing/InvoiceLines.vue'
import InvoiceChangeDialog from '@/components/billing/InvoiceChangeDialog.vue'
import InvoiceHistory from '@/components/billing/InvoiceHistory.vue'
import { dueDateText, dueStatusLabel, readPendingInvoiceChange } from '@/lib/invoice-controls'
import InvoicePaymentDialog from '@/components/billing/InvoicePaymentDialog.vue'
import PaymentDetailDialog from '@/components/billing/PaymentDetailDialog.vue'
import PaymentReceiptSummary from '@/components/billing/PaymentReceiptSummary.vue'
import { useOwnerApi } from '@/composables/useOwnerApi'
import { useAuthStore } from '@/stores/auth'
import { toApiError } from '@/lib/api'
import { paymentStatusLabel, positiveMoney } from '@/lib/billing'
import { formatMoney } from '@/lib/format'
import { formatDateTime } from '@/lib/notifications'
import type { Occupancy, Representative } from '@/types/owner'
import type { Invoice, InvoiceAction } from '@/types/billing'
const route = useRoute(), client = useOwnerApi(), auth = useAuthStore(), invoice = ref<Invoice | null>(null), occupancy = ref<Occupancy | null>(null), loading = ref(false), error = ref(''), actionError = ref(''), success = ref(route.query.issued ? 'Đã phát hành hóa đơn. Ghi nhận thu tiền khi bạn thực nhận tiền.' : '')
const changeAction = ref<InvoiceAction | null>(null)
const changeDetailsOpen = ref(false)
const pendingChange = computed(() => invoice.value ? readPendingInvoiceChange(`rental:pending-invoice-change:${auth.user?.id}:${invoice.value.id}`) : null)
watch(pendingChange, change => { if (change) changeDetailsOpen.value = true })
const paymentOpen = ref(false), paymentId = ref<number | null>(null), accessOpen = ref(false), revokeOpen = ref(false), reminderOpen = ref(false), representative = ref(''), reason = ref(''), accessLoading = ref(false)
let sequence = 0, alive = true
const activeGrant = computed(() => invoice.value?.access_grants?.find(item => item.is_active))
const hasEffectivePayments = computed(() => invoice.value?.payments?.some(payment => payment.status !== 'voided') ?? false)
const paidReceipt = computed(() => invoice.value?.payment_status === 'paid'
  ? invoice.value.payments?.filter(payment => payment.status !== 'voided').at(-1) : undefined)
const canCollect = computed(() => invoice.value && positiveMoney(invoice.value.outstanding_amount)
  && invoice.value.payment_status !== 'inconsistent' && invoice.value.payment_status !== 'cancelled')
const pendingPayment = computed(() => { try { return !!sessionStorage.getItem(`rental:pending-payment:${auth.user?.id}:${invoice.value?.id}`) } catch { return false } })
function representativeName(id: number) { const rep = occupancy.value?.representatives?.find(item => item.id === id); return rep?.tenant.full_name ?? 'Đại diện tại thời điểm cấp quyền' }
async function load() { const current = ++sequence; invoice.value = null; occupancy.value = null; error.value = ''; actionError.value = ''; loading.value = true; try { const result = await client.get<{ data: Invoice }>(`/invoices/${route.params.id}`); if (!alive || current !== sequence) return; invoice.value = result.data; try { const detail = await client.get<{ data: Occupancy }>(`/occupancies/${result.data.occupancy_id}`); if (alive && current === sequence) occupancy.value = detail.data } catch { /* Invoice remains usable; access dialog can load this separately. */ } } catch (caught) { if (alive && current === sequence) error.value = toApiError(caught).message } finally { if (alive && current === sequence) loading.value = false } }
async function openAccess() { if (!invoice.value) return; client.clearError(); actionError.value = ''; accessOpen.value = true; representative.value = ''; reason.value = ''; if (occupancy.value) return; accessLoading.value = true; try { const result = await client.get<{ data: Occupancy }>(`/occupancies/${invoice.value.occupancy_id}`); if (alive && accessOpen.value) occupancy.value = result.data } catch (caught) { if (alive) actionError.value = toApiError(caught).message } finally { if (alive) accessLoading.value = false } }
function repLabel(rep: Representative) { return `${rep.tenant.full_name} · ${formatDateTime(rep.started_at)} → ${rep.ended_at ? formatDateTime(rep.ended_at) : 'hiện tại'}` }
async function saveAccess() { if (!invoice.value) return; try { const result = await client.write<{ data: Invoice }>('put', `/invoices/${invoice.value.id}/access`, { representative_assignment_id: Number(representative.value), grant_reason: reason.value }); invoice.value = result.data; accessOpen.value = false; success.value = 'Đã cấp quyền xem hóa đơn cho đại diện được chọn.' } catch { /* Keep form for corrections. */ } }
async function revoke() { if (!invoice.value) return; try { const result = await client.write<{ data: Invoice }>('delete', `/invoices/${invoice.value.id}/access`); invoice.value = result.data; revokeOpen.value = false; success.value = 'Đã thu hồi quyền xem hiện tại; lịch sử vẫn được giữ.' } catch { /* Manual retry only. */ } }
async function remind() { if (!invoice.value) return; try { const result = await client.write<{ message: string }>('post', `/invoices/${invoice.value.id}/reminder`); reminderOpen.value = false; success.value = result.message } catch { /* Backend checks balance, grant and daily duplicate. */ } }
watch(() => route.params.id, () => { changeAction.value = null; paymentOpen.value = false; accessOpen.value = false; revokeOpen.value = false; reminderOpen.value = false; paymentId.value = null; void load() }, { immediate: true })
onBeforeUnmount(() => { alive = false; sequence++ })
</script>
<template>
  <section class="management-page">
    <RouterLink class="back-link" to="/owner/invoices">← Danh sách hóa đơn</RouterLink>
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="error" kind="error" :description="error"><UiButton @click="load">Tải lại</UiButton></UiPageState>
    <template v-else-if="invoice">
      <header class="management-header workflow-header">
        <div><h1>{{ invoice.invoice_kind === 'one_time' ? 'Khoản thu riêng phòng' : 'Hóa đơn phòng' }} {{ invoice.room.room_number }}</h1><p>Tháng {{ invoice.billing_month.slice(5) }}/{{ invoice.billing_month.slice(0, 4) }} · {{ invoice.invoice_number }}</p></div>
        <div class="button-row">
          <ScheduledDeleteButton kind="invoices" :id="invoice.id" :label="invoice.invoice_number" button-label="Xóa hóa đơn" />
          <PdfDownloadButton :path="`/owner/invoices/${invoice.id}/pdf`" :filename="`hoa-don-${invoice.id}.pdf`" label="Tải PDF hóa đơn" />
        </div>
      </header>
      <UiNotice v-if="success" tone="success">{{ success }}</UiNotice>
      <UiNotice v-if="invoice.payment_status === 'inconsistent'" tone="warning">Số dư cần kiểm tra. Hãy đối chiếu các dòng hóa đơn và lịch sử thu trước khi ghi nhận thêm.</UiNotice>
      <UiNotice v-if="invoice.payos_review_transfers?.length" tone="warning" title="Giao dịch payOS cần đối chiếu">
        Kiểm tra các giao dịch này trong ngân hàng trước khi ghi nhận thu tiền.
        <ul>
          <li v-for="transfer in invoice.payos_review_transfers" :key="transfer.id">
            <strong>{{ formatMoney(String(transfer.amount)) }}</strong> · {{ formatDateTime(transfer.paid_at) }} · Mã {{ transfer.reference }}. {{ transfer.review_reason }}
          </li>
        </ul>
      </UiNotice>
      <div class="detail-sections workflow-sections">
        <section class="record-card wide workflow-summary">
          <header>
            <div v-if="canCollect">
              <h2 class="workflow-caption">Còn phải thu</h2>
              <strong class="workflow-amount">{{ formatMoney(invoice.outstanding_amount) }}</strong>
            </div>
            <h2 v-else class="workflow-caption">Tình trạng thu tiền</h2>
            <UiBadge :tone="invoice.payment_status === 'paid' ? 'success' : invoice.payment_status === 'inconsistent' ? 'danger' : invoice.payment_status === 'cancelled' ? 'neutral' : 'warning'">{{ paymentStatusLabel(invoice.payment_status) }}</UiBadge>
          </header>
          <p v-if="invoice.payment_status === 'cancelled'" class="help-text">Hóa đơn đã hủy. Khoản này không tính vào công nợ.</p>
          <p v-else-if="canCollect" class="workflow-deadline"><strong>{{ invoice.due_date ? `Hạn thanh toán ${dueDateText(invoice.due_date)}` : 'Chưa đặt hạn thanh toán' }}</strong><span v-if="invoice.due_status === 'overdue'">{{ dueStatusLabel(invoice) }}</span></p>
          <dl class="record-facts workflow-facts">
            <div><dt>Tổng hóa đơn</dt><dd>{{ formatMoney(invoice.total_amount) }}</dd></div>
            <div><dt>Đã thu</dt><dd>{{ formatMoney(invoice.paid_amount) }}</dd></div>
          </dl>
          <div class="button-row workflow-actions">
            <UiButton v-if="canCollect || pendingPayment" @click="paymentOpen = true">{{ pendingPayment ? 'Kiểm tra lần thu đang chờ' : 'Ghi nhận thu tiền' }}</UiButton>
            <UiButton v-else-if="paidReceipt" @click="paymentId = paidReceipt.id">Xem phiếu thu PT-{{ paidReceipt.id }}</UiButton>
            <UiButton v-if="canCollect" variant="ghost" @click="reminderOpen = true; client.clearError()">Nhắc thanh toán</UiButton>
          </div>
        </section>
        <section class="record-card wide">
          <h2>Lịch sử thu tiền</h2>
          <p v-if="!invoice.payments?.length" class="help-text">Chưa ghi nhận lần thu nào.</p>
          <ul v-else class="detail-items">
            <li v-for="payment in invoice.payments" :key="payment.id">
              <PaymentReceiptSummary :payment="payment" show-actor />
              <UiButton v-if="payment.id !== paidReceipt?.id || pendingPayment" variant="ghost" @click="paymentId = payment.id">Xem lần thu PT-{{ payment.id }}</UiButton>
            </li>
          </ul>
          <div class="button-row">
            <RouterLink class="workflow-card-link" to="/owner/payments">Tất cả lần thu</RouterLink>
            <UiButton variant="ghost" @click="load">Làm mới số dư</UiButton>
          </div>
        </section>
        <details class="workflow-disclosure wide">
          <summary>Chi tiết các khoản và PDF hóa đơn</summary>
          <div class="workflow-disclosure-body">
            <InvoiceLines :items="invoice.items ?? []" />
            <dl v-if="invoice.rounding_amount && invoice.rounding_amount !== '0.00'" class="record-facts">
              <div><dt>Chênh lệch làm tròn đến 1.000 đ</dt><dd>{{ formatMoney(invoice.rounding_amount) }}</dd></div>
            </dl>
            <p class="help-text">Nhóm {{ invoice.group.group_code }} · Phát hành {{ formatDateTime(invoice.issued_at) }}</p>
            <p v-if="!canCollect && invoice.due_date" class="help-text">Hạn thanh toán {{ dueDateText(invoice.due_date) }}</p>
          </div>
        </details>
        <details class="workflow-disclosure wide" :open="changeDetailsOpen" @toggle="changeDetailsOpen = ($event.currentTarget as HTMLDetailsElement).open">
          <summary>Điều chỉnh, hạn thanh toán và lịch sử</summary>
          <div class="workflow-disclosure-body">
            <section class="record-card">
              <UiNotice v-if="invoice.payment_status === 'cancelled'" tone="warning">Hóa đơn đã hủy và đã được loại khỏi công nợ.</UiNotice>
              <div class="button-row">
                <UiButton v-if="pendingChange" @click="changeAction = pendingChange.action">Kiểm tra thay đổi đang chờ</UiButton>
                <template v-else-if="invoice.payment_status !== 'inconsistent'">
                  <template v-if="invoice.payment_status !== 'cancelled'">
                    <UiButton variant="secondary" @click="changeAction = 'adjustment'">Điều chỉnh số tiền</UiButton>
                    <UiButton variant="secondary" @click="changeAction = 'due_date'">Đặt / đổi hạn thanh toán</UiButton>
                    <UiButton v-if="!hasEffectivePayments" variant="ghost" @click="changeAction = 'cancel'">Hủy hóa đơn</UiButton>
                    <p v-else class="help-text">Đang có phiếu thu hiệu lực: có thể điều chỉnh, không được hủy hóa đơn.</p>
                  </template>
                  <UiButton v-else variant="secondary" @click="changeAction = 'restore'">Khôi phục hóa đơn</UiButton>
                </template>
              </div>
            </section>
            <InvoiceHistory :invoice="invoice" />
          </div>
        </details>
        <details class="workflow-disclosure wide">
          <summary>Quyền xem của người thuê · {{ activeGrant ? 'Đang cấp quyền' : 'Chưa cấp quyền' }}</summary>
          <div class="workflow-disclosure-body">
            <p class="help-text">Đổi đại diện hoặc trả phòng không tự chuyển quyền hóa đơn cũ.</p>
            <p v-if="activeGrant">{{ representativeName(activeGrant.representative_assignment_id) }}</p>
            <div class="button-row">
              <UiButton variant="secondary" @click="openAccess">Cấp / thay người được xem</UiButton>
              <UiButton v-if="activeGrant" variant="ghost" @click="revokeOpen = true; client.clearError()">Thu hồi quyền hiện tại</UiButton>
            </div>
            <ul class="detail-items">
              <li v-for="grant in invoice.access_grants ?? []" :key="grant.id">
                <strong>{{ representativeName(grant.representative_assignment_id) }}</strong>
                <p class="help-text">Cấp {{ formatDateTime(grant.granted_at) }} · {{ grant.revoked_at ? `Thu hồi ${formatDateTime(grant.revoked_at)}` : 'Đang có hiệu lực' }}</p>
                <p>{{ grant.grant_reason }}</p>
              </li>
            </ul>
          </div>
        </details>
      </div>
    </template>
<InvoiceChangeDialog v-if="changeAction && invoice" :invoice="invoice" :action="changeAction" @close="changeAction = null" @refresh="load" @saved="updated => { invoice = updated; success = 'Đã lưu thay đổi và cập nhật công nợ.' }" />
<InvoicePaymentDialog v-if="paymentOpen && invoice" :invoice="invoice" @close="paymentOpen = false" @paid="(updated, payment, replayed) => { invoice = updated; success = replayed ? 'Lần thu này đã được ghi nhận trước đó; số dư không cộng thêm.' : `Đã ghi nhận lần thu ${payment.receipt_number}.` }" />
<PaymentDetailDialog :payment-id="paymentId" @close="paymentId = null" @changed="updated => { invoice = updated; success = 'Đã sửa sai phiếu thu và cập nhật số dư hóa đơn.' }" />
<UiDialog :open="accessOpen" title="Cấp quyền xem hóa đơn" description="Chọn khoảng đại diện thuộc đúng đợt thuê. Tài khoản người thuê phải đang hoạt động." @update:open="value => { if (!client.busy.value) accessOpen = value }"><UiPageState v-if="accessLoading" kind="loading" /><p v-if="actionError || client.error.value" class="form-error" role="alert">{{ actionError || client.error.value }}</p><form v-if="!accessLoading" class="form-grid" @submit.prevent="saveAccess"><label>Người đại diện<select v-model="representative" class="ui-select" required><option value="">Chọn người đại diện</option><option v-for="rep in occupancy?.representatives ?? []" :key="rep.id" :value="String(rep.id)">{{ repLabel(rep) }}</option></select></label><p v-if="!occupancy?.representatives?.length" class="help-text">Chưa có khoảng đại diện. Kiểm tra đợt thuê trước khi cấp quyền.</p><p v-if="client.errors.value.representative_assignment_id" class="field-error">{{ client.errors.value.representative_assignment_id[0] }}</p><OwnerField v-model="reason" label="Lý do cấp / thay quyền" name="grant_reason" required :max-length="255" :errors="client.errors.value" /><UiButton type="submit" :loading="client.busy.value" :disabled="!representative">Xác nhận cấp quyền</UiButton></form></UiDialog>
<UiDialog :open="revokeOpen" title="Thu hồi quyền xem hiện tại?" description="Người thuê hiện được cấp quyền sẽ không còn thấy hóa đơn này. Lịch sử cấp quyền được giữ lại." @update:open="value => { if (!client.busy.value) revokeOpen = value }"><p v-if="client.error.value" class="form-error" role="alert">{{ client.error.value }}</p><template #actions><UiButton variant="secondary" :disabled="client.busy.value" @click="revokeOpen = false">Giữ quyền xem</UiButton><UiButton :loading="client.busy.value" @click="revoke">Xác nhận thu hồi</UiButton></template></UiDialog>
<UiDialog :open="reminderOpen" title="Nhắc người thuê thanh toán?" description="Tạo thông báo trong ứng dụng cho người đang được cấp quyền. Cùng hóa đơn và người nhận chỉ được nhắc một lần mỗi ngày." @update:open="value => { if (!client.busy.value) reminderOpen = value }"><p v-if="client.error.value" class="form-error" role="alert">{{ client.error.value }}</p><template #actions><UiButton variant="secondary" :disabled="client.busy.value" @click="reminderOpen = false">Để sau</UiButton><UiButton :loading="client.busy.value" @click="remind">Gửi nhắc thanh toán</UiButton></template></UiDialog></section></template>

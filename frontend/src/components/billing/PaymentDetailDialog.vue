<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import { useOwnerApi } from '@/composables/useOwnerApi'
import { toApiError } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { formatDateTime } from '@/lib/notifications'
import type { Payment } from '@/types/billing'
const props = defineProps<{ paymentId: number | null }>(), emit = defineEmits<{ close: [] }>(), client = useOwnerApi()
const payment = ref<Payment | null>(null), loading = ref(false), error = ref('')
let sequence = 0, alive = true
async function load() { const id = props.paymentId, current = ++sequence; payment.value = null; error.value = ''; if (!id) return; loading.value = true; try { const result = await client.get<{ data: Payment }>(`/payments/${id}`); if (alive && current === sequence) payment.value = result.data } catch (caught) { if (alive && current === sequence) error.value = toApiError(caught).message } finally { if (alive && current === sequence) loading.value = false } }
watch(() => props.paymentId, load, { immediate: true }); onBeforeUnmount(() => { alive = false; sequence++ })
</script>
<template><UiDialog :open="!!paymentId" title="Chi tiết lần thu" @update:open="value => { if (!value) emit('close') }"><UiPageState v-if="loading" kind="loading" /><UiPageState v-else-if="error" kind="error" :description="error"><UiButton @click="load">Thử lại</UiButton></UiPageState><template v-else-if="payment"><h3>{{ payment.receipt_number }} · {{ formatMoney(payment.amount) }}</h3><dl class="record-facts"><div><dt>Hóa đơn</dt><dd>{{ payment.invoice_number }}</dd></div><div><dt>Phòng / nhóm</dt><dd>{{ payment.room.room_number }} / {{ payment.group.group_code }}</dd></div><div><dt>Tháng hóa đơn</dt><dd>{{ payment.billing_month }}</dd></div><div><dt>Nhận tiền lúc</dt><dd>{{ formatDateTime(payment.paid_at) }}</dd></div><div><dt>Hình thức</dt><dd>{{ payment.payment_method === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' }}</dd></div><div><dt>Ghi nhận lúc</dt><dd>{{ formatDateTime(payment.recorded_at) }}</dd></div></dl><p class="help-text">Mã lần thu dùng để tra cứu trong hệ thống.</p><RouterLink class="record-link" :to="`/owner/invoices/${payment.invoice_id}`" @click="emit('close')">Mở hóa đơn liên quan</RouterLink></template></UiDialog></template>

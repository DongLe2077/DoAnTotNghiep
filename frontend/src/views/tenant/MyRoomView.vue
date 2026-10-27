<script setup lang="ts">
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import { useTenantResource } from '@/composables/useTenantApi'
import { formatMoney } from '@/lib/format'
import { formatUnitPrice } from '@/lib/unit-price'
import { formatDateTime } from '@/lib/notifications'
import type { MyStay } from '@/types/tenant'
const { data: stay, loading, error, status, load } = useTenantResource<MyStay | null>('/current-stay')
void load()
</script>
<template>
  <section class="management-page tenant-page">
    <header class="management-header"><div><span class="eyebrow">Không gian của bạn</span><h1>Phòng của tôi</h1><p>Nơi ở, dịch vụ và những thông tin bạn cần mỗi ngày.</p></div><UiButton variant="secondary" :loading="loading" @click="load">Làm mới thông tin</UiButton></header>
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="error" :kind="status === 404 ? 'not-found' : 'error'" :title="status === 404 ? 'Chưa có hồ sơ liên kết' : 'Chưa tải được phòng của bạn'" :description="status === 404 ? 'Hãy liên hệ chủ trọ để liên kết tài khoản với hồ sơ người thuê.' : error"><UiButton @click="load">Thử lại</UiButton></UiPageState>
    <template v-else-if="stay">
      <article class="record-card tenant-room-hero"><div><UiBadge tone="success">Đang cư trú</UiBadge><h2>Phòng {{ stay.room.room_number }}</h2><p>Nhóm {{ stay.group.group_code }}</p><p class="help-text">Bạn vào ở từ {{ formatDateTime(stay.moved_in_at) }}</p></div><div class="tenant-rent"><span>Giá thuê toàn phòng / tháng</span><strong>{{ formatMoney(stay.monthly_room_rent) }}</strong><small>Giá đã thỏa thuận cho phòng, không phải tiền riêng của từng người.</small></div></article>
      <UiNotice v-if="stay.room.operational_status === 'maintenance'" tone="warning">Phòng đang được chủ trọ đánh dấu bảo trì. Thông tin cư trú của bạn vẫn được giữ nguyên.</UiNotice>
      <div class="tenant-quick-links"><RouterLink to="/tenant/invoices">Xem hóa đơn <span aria-hidden="true">→</span></RouterLink><RouterLink :to="stay.can_submit_maintenance ? '/tenant/maintenance/new' : '/tenant/maintenance'">{{ stay.can_submit_maintenance ? 'Gửi báo hỏng' : 'Yêu cầu đã gửi' }} <span aria-hidden="true">→</span></RouterLink><RouterLink to="/tenant/stays">Lịch sử cư trú <span aria-hidden="true">→</span></RouterLink></div>
      <section class="dashboard-section"><div class="dashboard-section-heading"><h2>Dịch vụ đang sử dụng</h2><span class="help-text">Giá đã áp dụng cho đợt thuê</span></div><p v-if="!stay.services?.length" class="record-card help-text">Chưa có dịch vụ đang áp dụng cho phòng.</p><div v-else class="management-grid"><article v-for="service in stay.services" :key="service.id" class="record-card"><header><h3>{{ service.service.name }}</h3><UiBadge :tone="service.billing_method === 'metered' ? 'info' : 'neutral'">{{ service.billing_method === 'metered' ? 'Theo chỉ số' : 'Theo phòng' }}</UiBadge></header><dl class="record-facts"><div><dt>Đơn giá</dt><dd><strong>{{ formatUnitPrice(service.unit_price) }} / {{ service.unit }}</strong></dd></div><div><dt>Áp dụng từ</dt><dd>{{ formatDateTime(service.effective_from) }}</dd></div></dl><p class="help-text">{{ service.billing_method === 'metered' ? 'Số tiền được tính theo lượng điện/nước sử dụng và thể hiện trên hóa đơn.' : 'Phí được phân bổ theo thời gian sử dụng trong tháng.' }}</p></article></div></section>
      <UiNotice tone="info">{{ stay.is_current_representative ? 'Bạn đang là đại diện của nhóm và có thể gửi báo hỏng cho phòng này.' : 'Bạn là thành viên đang cư trú. Đại diện hiện tại của nhóm thực hiện gửi báo hỏng.' }} Quyền xem hóa đơn được chủ trọ cấp riêng.</UiNotice>
    </template>
    <template v-else><UiPageState title="Bạn chưa có phòng đang cư trú" description="Khi chủ trọ ghi nhận bạn vào ở, thông tin phòng và dịch vụ sẽ xuất hiện tại đây." /><div class="tenant-quick-links"><RouterLink to="/tenant/stays">Xem lịch sử cư trú →</RouterLink><RouterLink to="/tenant/invoices">Hóa đơn còn quyền xem →</RouterLink><RouterLink to="/tenant/maintenance">Yêu cầu bạn đã gửi →</RouterLink></div></template>
  </section>
</template>

<script setup lang="ts">
import { useLiveRefresh } from '@/composables/useLiveRefresh'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import MyCheckoutRequests from '@/components/tenancy/MyCheckoutRequests.vue'
import { useTenantResource } from '@/composables/useTenantApi'
import { formatMoney } from '@/lib/format'
import { formatUnitPrice } from '@/lib/unit-price'
import { formatDateTime } from '@/lib/notifications'
import type { MyStay } from '@/types/tenant'
const { data: stay, loading, error, status, load, refresh } = useTenantResource<MyStay | null>('/current-stay')
void load()
useLiveRefresh('property', () => !loading.value ? refresh() : false)
</script>
<template>
  <section class="management-page tenant-page tenant-workspace">
    <header class="management-header workflow-header">
      <div><span class="tenant-kicker">Nơi ở của bạn</span><h1>Phòng của tôi</h1><p>Xem giá thuê, dịch vụ và thông tin cư trú.</p></div>
      <div class="button-row"><UiButton variant="ghost" :loading="loading" @click="load">Làm mới thông tin</UiButton><RouterLink v-if="stay && !loading" class="rb7-action" to="/tenant/invoices">Xem hóa đơn</RouterLink></div>
    </header>
    <UiPageState v-if="loading" kind="loading" skeleton="cards" />
    <UiPageState v-else-if="error" :kind="status === 404 ? 'not-found' : 'error'" :title="status === 404 ? 'Chưa có hồ sơ liên kết' : 'Chưa tải được phòng của bạn'" :description="status === 404 ? 'Hãy liên hệ chủ trọ để liên kết tài khoản với hồ sơ người thuê.' : error"><UiButton @click="load">Tải lại</UiButton></UiPageState>
    <template v-else-if="stay">
      <div class="room-sections">
        <article class="record-card tenant-room-hero tenant-highlight">
          <div><UiBadge tone="success">Đang cư trú</UiBadge><h2>Phòng {{ stay.room.room_number }}</h2></div>
          <div class="tenant-rent"><span>Giá thuê toàn phòng / tháng</span><strong>{{ formatMoney(stay.monthly_room_rent) }}</strong></div>
        </article>
        <UiNotice v-if="stay.room.operational_status === 'maintenance'" tone="warning">Phòng đang được bảo trì.</UiNotice>
        <div class="room-information">
        <section class="record-card" aria-labelledby="room-services-title">
          <h2 id="room-services-title">Dịch vụ đang sử dụng</h2>
          <p v-if="!stay.services?.length" class="help-text">Phòng chưa có dịch vụ đang áp dụng.</p>
          <dl v-else class="room-services">
            <div v-for="service in stay.services" :key="service.id">
              <dt>{{ service.service.name }}</dt>
              <dd>{{ formatUnitPrice(service.unit_price) }} / {{ service.unit }}</dd>
            </div>
          </dl>
          <details v-if="stay.services?.length" class="workflow-disclosure">
            <summary>Cách tính và thời gian áp dụng</summary>
            <div class="workflow-disclosure-body">
              <ul class="detail-items">
                <li v-for="service in stay.services" :key="service.id">
                  <strong>{{ service.service.name }}</strong>
                  <p class="help-text">{{ service.billing_method === 'metered' ? 'Theo lượng sử dụng' : 'Phân bổ theo thời gian sử dụng trong tháng' }} · Từ {{ formatDateTime(service.effective_from) }}</p>
                </li>
              </ul>
            </div>
          </details>
        </section>
        <section class="record-card" aria-labelledby="room-stay-title">
          <h2 id="room-stay-title">Cư trú</h2>
          <dl class="record-facts">
            <div><dt>Nhóm thuê</dt><dd>{{ stay.group.group_code }}</dd></div>
            <div><dt>Vào ở từ</dt><dd>{{ formatDateTime(stay.moved_in_at) }}</dd></div>
          </dl>
          <details class="workflow-disclosure">
            <summary>Thông tin cư trú</summary>
            <div class="workflow-disclosure-body">
              <p class="help-text">{{ stay.is_current_representative ? 'Bạn là đại diện nhóm và có thể gửi báo hỏng.' : 'Bạn là thành viên đang cư trú. Đại diện nhóm gửi báo hỏng cho phòng.' }} Quyền xem hóa đơn do chủ trọ cấp.</p>
              <RouterLink class="workflow-card-link" to="/tenant/stays">Xem lịch sử cư trú →</RouterLink>
            </div>
          </details>
        </section>
        </div>
      </div>
    </template>
    <UiPageState v-else title="Bạn chưa có phòng đang cư trú" description="Khi chủ trọ ghi nhận bạn vào ở, thông tin phòng và dịch vụ sẽ xuất hiện tại đây.">
      <RouterLink class="record-link" to="/tenant/stays">Xem lịch sử cư trú</RouterLink>
    </UiPageState>
    <MyCheckoutRequests :stay="stay" />
  </section>
</template>

<style scoped>
.room-sections { display: grid; gap: 16px; }
.tenant-room-hero h2 { margin: 8px 0 0; font-size: clamp(26px, 6vw, 39px); letter-spacing: -.04em; overflow-wrap: anywhere; }
.tenant-rent { gap: 4px; }
.tenant-rent > span { color: #c5cfd6; }
.tenant-rent > strong { color: var(--color-on-dark-accent, #b5c8ff); }
.room-information { display: grid; align-items: start; gap: 14px; }
.room-services { margin: 0 0 14px; }
.room-services > div { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 4px 16px; padding: 10px 0; }
.room-services > div + div { border-top: 1px solid var(--color-border); }
.room-services dd { margin: 0; color: var(--color-primary); font-weight: 650; }
.room-sections :deep(.ui-notice) { margin: 0; }
@media (min-width: 820px) {
  .room-information { grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); }
  .tenant-rent { justify-self: end; text-align: right; }
}
@media (max-width: 700px) {
  .tenant-room-hero { gap: 14px; }
  .tenant-rent { border-color: #4a5566; padding-top: 12px; }
}
</style>

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
    <header class="management-header workflow-header"><div><h1>Phòng của tôi</h1></div><UiButton variant="ghost" :loading="loading" @click="load">Làm mới thông tin</UiButton></header>
    <UiPageState v-if="loading" kind="loading" skeleton="cards" />
    <UiPageState v-else-if="error" :kind="status === 404 ? 'not-found' : 'error'" :title="status === 404 ? 'Chưa có hồ sơ liên kết' : 'Chưa tải được phòng của bạn'" :description="status === 404 ? 'Hãy liên hệ chủ trọ để liên kết tài khoản với hồ sơ người thuê.' : error"><UiButton @click="load">Thử lại</UiButton></UiPageState>
    <template v-else-if="stay">
      <div class="room-sections">
        <article class="record-card tenant-room-hero">
          <div><UiBadge tone="success">Đang cư trú</UiBadge><h2>Phòng {{ stay.room.room_number }}</h2></div>
          <div class="tenant-rent"><span>Giá thuê toàn phòng / tháng</span><strong>{{ formatMoney(stay.monthly_room_rent) }}</strong></div>
        </article>
        <UiNotice v-if="stay.room.operational_status === 'maintenance'" tone="warning">Phòng đang được bảo trì.</UiNotice>
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
        <details class="workflow-disclosure">
          <summary>Thông tin cư trú</summary>
          <div class="workflow-disclosure-body">
            <dl class="record-facts">
              <div><dt>Nhóm thuê</dt><dd>{{ stay.group.group_code }}</dd></div>
              <div><dt>Vào ở từ</dt><dd>{{ formatDateTime(stay.moved_in_at) }}</dd></div>
            </dl>
            <p class="help-text">{{ stay.is_current_representative ? 'Bạn là đại diện nhóm và có thể gửi báo hỏng.' : 'Bạn là thành viên đang cư trú. Đại diện nhóm gửi báo hỏng cho phòng.' }} Quyền xem hóa đơn do chủ trọ cấp.</p>
            <RouterLink class="workflow-card-link" to="/tenant/stays">Xem lịch sử cư trú →</RouterLink>
          </div>
        </details>
      </div>
    </template>
    <UiPageState v-else title="Bạn chưa có phòng đang cư trú" description="Khi chủ trọ ghi nhận bạn vào ở, thông tin phòng và dịch vụ sẽ xuất hiện tại đây." />
  </section>
</template>

<style scoped>
.room-sections { display: grid; gap: 16px; }
.tenant-room-hero h2 { margin: 8px 0 0; font-size: clamp(24px, 6vw, 32px); }
.tenant-rent { gap: 4px; }
.room-services { margin: 0 0 14px; }
.room-services > div { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 4px 16px; padding: 10px 0; }
.room-services > div + div { border-top: 1px solid var(--color-border); }
.room-services dd { margin: 0; color: var(--color-primary); font-weight: 650; }
.room-sections :deep(.ui-notice) { margin: 0; }
</style>

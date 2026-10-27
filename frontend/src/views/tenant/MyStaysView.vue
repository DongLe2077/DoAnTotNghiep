<script setup lang="ts">
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import { useTenantList } from '@/composables/useTenantApi'
import { formatMoney } from '@/lib/format'
import { formatDateTime } from '@/lib/notifications'
import type { MyStay } from '@/types/tenant'
const { items, page, lastPage, total, loading, error, status, load } = useTenantList<MyStay>('/stays')
void load()
</script>
<template><section class="management-page tenant-page"><header class="management-header"><div><span class="eyebrow">Những nơi bạn đã ở</span><h1>Lịch sử cư trú</h1><p>Các lần nhận và rời phòng của chính bạn.</p></div><UiButton variant="secondary" :loading="loading" @click="load()">Làm mới lịch sử</UiButton></header><UiPageState v-if="loading" kind="loading" /><UiPageState v-else-if="error" :kind="status === 404 ? 'not-found' : 'error'" :title="status === 404 ? 'Chưa có hồ sơ liên kết' : 'Chưa tải được lịch sử'" :description="error"><UiButton @click="load()">Thử lại</UiButton></UiPageState><UiPageState v-else-if="!items.length" title="Bạn chưa có lịch sử cư trú" description="Các lần nhận/chuyển/trả phòng sẽ được lưu tại đây." /><div v-else class="management-grid"><article v-for="stay in items" :key="stay.id" class="record-card"><header><div><h2>Phòng {{ stay.room.room_number }}</h2><span class="help-text">Nhóm {{ stay.group.group_code }}</span></div><UiBadge :tone="stay.is_active ? 'success' : 'neutral'">{{ stay.is_active ? 'Đang cư trú' : 'Đã rời phòng' }}</UiBadge></header><dl class="record-facts"><div><dt>Vào ở</dt><dd>{{ formatDateTime(stay.moved_in_at) }}</dd></div><div><dt>Rời phòng</dt><dd>{{ stay.moved_out_at ? formatDateTime(stay.moved_out_at) : stay.occupancy_ended_at ? formatDateTime(stay.occupancy_ended_at) : 'Chưa kết thúc' }}</dd></div><div><dt>Giá toàn phòng / tháng</dt><dd>{{ formatMoney(stay.monthly_room_rent) }}</dd></div></dl><RouterLink v-if="stay.is_active" class="record-link" to="/tenant">Xem phòng hiện tại →</RouterLink></article></div><OwnerPager v-if="!error && total" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" /></section></template>

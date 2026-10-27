<script setup lang="ts">
import { ref } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import { useOwnerList } from '@/composables/useOwnerApi'
import { formatDateTime } from '@/lib/notifications'
import type { Announcement } from '@/types/announcements'
const query = ref(''), audience = ref('')
const { items, page, lastPage, total, loading, loadError, load } = useOwnerList<Announcement>('/announcements')
function filter() { void load(1, { ...(query.value.trim() ? { q: query.value.trim() } : {}), ...(audience.value ? { audience: audience.value } : {}) }) }
void load()
</script>
<template><section class="management-page"><header class="management-header"><div><span class="eyebrow">Kết nối với người thuê</span><h1>Thông báo chung</h1><p>Gửi nội quy, lịch bảo trì hoặc thông tin sinh hoạt. Theo dõi người nhận và trạng thái đã đọc.</p></div><RouterLink class="tenant-primary-link" to="/owner/announcements/new">+ Soạn thông báo</RouterLink></header>
  <form class="management-toolbar" @submit.prevent="filter"><label>Tìm thông báo<input v-model="query" class="ui-input" maxlength="160" placeholder="Tiêu đề hoặc nội dung" /></label><label>Phạm vi gửi<select v-model="audience" class="ui-select"><option value="">Tất cả phạm vi</option><option value="all">Toàn bộ người đang ở</option><option value="rooms">Theo phòng đã chọn</option></select></label><UiButton type="submit" variant="secondary" :loading="loading">Tìm thông báo</UiButton><UiButton variant="ghost" :loading="loading" @click="load()">Làm mới</UiButton></form>
  <UiPageState v-if="loading" kind="loading" /><UiPageState v-else-if="loadError" kind="error" :description="loadError"><UiButton @click="load()">Thử lại</UiButton></UiPageState><UiPageState v-else-if="!items.length" title="Chưa có thông báo phù hợp" description="Soạn thông báo mới hoặc thay đổi bộ lọc." />
  <div v-else class="management-grid"><article v-for="item in items" :key="item.id" class="record-card"><header><h2>{{ item.title }}</h2><UiBadge tone="info">{{ item.audience === 'all' ? 'Toàn bộ' : 'Theo phòng' }}</UiBadge></header><p class="announcement-excerpt">{{ item.body }}</p><p class="help-text">{{ formatDateTime(item.created_at) }} · {{ item.created_by }}</p><dl class="record-facts"><div><dt>Người nhận</dt><dd>{{ item.recipient_count }}</dd></div><div><dt>Đã đọc / chưa đọc</dt><dd>{{ item.read_count }} / {{ item.unread_count }}</dd></div></dl><footer><RouterLink class="record-link" :to="`/owner/announcements/${item.id}`">Xem nội dung và người nhận →</RouterLink></footer></article></div>
  <OwnerPager v-if="!loadError && total" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" />
</section></template>
<style scoped>.announcement-excerpt{white-space:pre-wrap;overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;font-size:14px;line-height:1.8}</style>

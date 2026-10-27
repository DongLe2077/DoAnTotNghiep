<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { useRoute } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import { useOwnerApi, useOwnerList } from '@/composables/useOwnerApi'
import { toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import type { Announcement, AnnouncementRecipient } from '@/types/announcements'
const route = useRoute(), client = useOwnerApi(), data = ref<Announcement | null>(null), error = ref(''), loading = ref(true), readStatus = ref('')
const recipients = useOwnerList<AnnouncementRecipient>(`/announcements/${route.params.id}/recipients`)
let alive = true, sequence = 0
onBeforeUnmount(() => { alive = false; sequence++ })
async function load() {
  const current = ++sequence; loading.value = true; error.value = ''; data.value = null
  try { const [response] = await Promise.all([client.get<{ data: Announcement }>(`/announcements/${route.params.id}`), recipients.load(1, readStatus.value ? { read_status: readStatus.value } : {})]); if (alive && current === sequence) data.value = response.data }
  catch (caught) { if (alive && current === sequence) error.value = toApiError(caught).message }
  finally { if (alive && current === sequence) loading.value = false }
}
void load()
</script>
<template><section class="management-page"><RouterLink class="back-link" to="/owner/announcements">← Thông báo chung</RouterLink><UiPageState v-if="loading" kind="loading" /><UiPageState v-else-if="error" kind="error" :description="error"><UiButton @click="load">Thử lại</UiButton></UiPageState><template v-else-if="data"><header class="management-header"><div><span class="eyebrow">Thông báo đã gửi</span><h1 class="announcement-heading">{{ data.title }}</h1><p>{{ formatDateTime(data.created_at) }} · {{ data.created_by }}</p></div><UiButton variant="secondary" @click="load">Cập nhật trạng thái đọc</UiButton></header><UiNotice v-if="route.query.sent" tone="success">Đã ghi nhận thông báo. Mỗi người trong danh sách nhận một thông báo cho lượt gửi này.</UiNotice>
  <article class="record-card"><p class="help-text">{{ data.audience === 'all' ? 'Toàn bộ người thuê đang ở lúc gửi' : `Phòng ${data.rooms.map(room => room.room_number).join(', ')}` }}</p><p class="announcement-body">{{ data.body }}</p></article>
  <div class="dashboard-stats"><article class="stat-card"><span>Người nhận</span><strong>{{ data.recipient_count }}</strong></article><article class="stat-card"><span>Đã đọc</span><strong>{{ data.read_count }}</strong></article><article class="stat-card"><span>Chưa đọc</span><strong>{{ data.unread_count }}</strong></article></div>
  <section class="record-card"><h2>Danh sách người nhận</h2><form class="management-toolbar" @submit.prevent="load"><label>Trạng thái đọc<select v-model="readStatus" class="ui-select"><option value="">Tất cả người nhận</option><option value="read">Đã đọc</option><option value="unread">Chưa đọc</option></select></label><UiButton type="submit" variant="secondary">Lọc người nhận</UiButton></form><UiPageState v-if="recipients.loading.value" kind="loading" /><UiPageState v-else-if="recipients.loadError.value" kind="error" :description="recipients.loadError.value"><UiButton @click="load">Thử lại</UiButton></UiPageState><p v-else-if="!recipients.items.value.length" class="help-text">Không có người nhận phù hợp.</p><ul v-else class="detail-items"><li v-for="recipient in recipients.items.value" :key="recipient.notification_id"><div class="button-row"><strong>{{ recipient.full_name }}</strong><UiBadge :tone="recipient.is_read ? 'success' : 'neutral'">{{ recipient.is_read ? 'Đã đọc' : 'Chưa đọc' }}</UiBadge></div><p class="help-text">{{ recipient.username }} · Phòng lúc nhận: {{ recipient.room_numbers.join(', ') }}<template v-if="!recipient.is_active"> · Tài khoản đang khóa</template></p><p v-if="recipient.read_at" class="help-text">Đọc lúc {{ formatDateTime(recipient.read_at) }}</p></li></ul><OwnerPager v-if="!recipients.loadError.value && recipients.total.value" :page="recipients.page.value" :last-page="recipients.lastPage.value" :total="recipients.total.value" :loading="recipients.loading.value" @change="recipients.load($event)" /><p class="help-text">Danh sách được giữ theo lúc gửi. Trạng thái đã đọc tính khi người nhận mở hoặc đánh dấu đã đọc trong ứng dụng.</p></section>
</template></section></template>
<style scoped>.announcement-heading{overflow-wrap:anywhere}.announcement-body{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.8;margin:16px 0}</style>

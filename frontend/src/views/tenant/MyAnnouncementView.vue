<script setup lang="ts">
import { useRoute } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import { useTenantResource } from '@/composables/useTenantApi'
import { useNotificationsStore } from '@/stores/notifications'
import { formatDateTime } from '@/lib/notifications'
import type { TenantAnnouncement } from '@/types/announcements'
const route = useRoute(), notifications = useNotificationsStore()
const { data, loading, error, status, load } = useTenantResource<TenantAnnouncement>(() => `/announcements/${route.params.id}`)
async function read() { if (!data.value) return; try { await notifications.markRead(data.value.notification_id); await load() } catch { /* Keep the explicit action available on failure. */ } }
void load()
</script>
<template><section class="management-page tenant-page"><RouterLink class="back-link" to="/notifications?type=owner_announcement">← Thông báo chung</RouterLink><UiPageState v-if="loading" kind="loading" /><UiPageState v-else-if="error" :kind="status === 404 ? 'not-found' : 'error'" :description="status === 404 ? 'Thông báo không tồn tại hoặc không nằm trong danh sách gửi cho tài khoản của bạn.' : error"><UiButton @click="load">Thử lại</UiButton></UiPageState><template v-else-if="data"><header class="management-header"><div><span class="eyebrow">Thông báo từ chủ trọ</span><h1 class="announcement-heading">{{ data.title }}</h1><p>{{ formatDateTime(data.created_at) }}</p></div><UiBadge :tone="data.is_read ? 'success' : 'info'">{{ data.is_read ? 'Đã đọc' : 'Chưa đọc' }}</UiBadge></header><article class="record-card"><p class="announcement-body">{{ data.body }}</p><p v-if="data.read_at" class="help-text">Đã đọc lúc {{ formatDateTime(data.read_at) }}</p><p v-if="notifications.actionError" role="alert" class="form-error">{{ notifications.actionError }}</p><div class="button-row"><UiButton v-if="!data.is_read" :loading="notifications.busyIds.includes(data.notification_id)" @click="read">Đánh dấu đã đọc</UiButton><UiButton variant="secondary" @click="load">Làm mới</UiButton></div></article></template></section></template>
<style scoped>.announcement-heading{overflow-wrap:anywhere}.announcement-body{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.9;margin:8px 0 24px}</style>

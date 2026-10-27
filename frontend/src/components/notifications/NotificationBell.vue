<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'

const auth = useAuthStore()
const notifications = useNotificationsStore()
const route = useRoute()
const label = computed(() => notifications.unreadCount ? `Thông báo, ${notifications.unreadCount} chưa đọc` : 'Thông báo')
let timer: ReturnType<typeof setInterval> | undefined
let lastRefresh = 0
function refresh(force = false) {
  if (!auth.user || document.visibilityState !== 'visible' || (!force && Date.now() - lastRefresh < 5000)) return
  lastRefresh = Date.now()
  void notifications.refreshCount()
  if (route.name === 'notifications' && !notifications.loading) void notifications.refresh()
}
function onReturn() { refresh() }
watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', () => { lastRefresh = 0; refresh(true) })
onMounted(() => {
  refresh(true)
  timer = setInterval(() => refresh(), 60_000)
  document.addEventListener('visibilitychange', onReturn)
  window.addEventListener('focus', onReturn)
})
onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
  document.removeEventListener('visibilitychange', onReturn)
  window.removeEventListener('focus', onReturn)
})
</script>

<template>
  <RouterLink class="notification-bell" to="/notifications" :aria-label="label" :aria-current="route.name === 'notifications' ? 'page' : undefined" :title="notifications.countError ? 'Chưa cập nhật được số thông báo. Mở hộp thông báo để thử lại.' : label">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
    <span class="bell-text">Thông báo</span>
    <span v-if="notifications.unreadCount" class="unread-badge" aria-hidden="true">{{ notifications.unreadCount > 99 ? '99+' : notifications.unreadCount }}</span>
  </RouterLink>
</template>

<style scoped>
.notification-bell { position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-width: 44px; min-height: 44px; padding: 10px; border: 1px solid var(--color-border); border-radius: 10px; color: var(--color-primary); background: var(--color-surface); text-decoration: none; }
.notification-bell:hover, .notification-bell[aria-current='page'] { background: var(--color-primary-soft); border-color: var(--color-input-border); }
svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linejoin: round; stroke-linecap: round; }
.bell-text { font-size: 13px; font-weight: 500; }
.unread-badge { display: grid; place-items: center; min-width: 20px; min-height: 20px; padding: 1px 5px; border-radius: 6px; background: var(--color-primary); color: #fff; font-size: 12px; font-weight: 600; }
@media (max-width: 1100px) { .bell-text { display: none; } .unread-badge { position: absolute; top: -7px; right: -7px; } }
@media (max-width: 640px) { .notification-bell { min-height: 48px; min-width: 48px; margin-right: 8px; } }
</style>

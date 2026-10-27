<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NotificationSubjectDialog from '@/components/notifications/NotificationSubjectDialog.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiEmptyState from '@/components/ui/UiEmptyState.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import { formatDateTime, notificationTypeLabel } from '@/lib/notifications'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import type { AppNotification, NotificationType } from '@/types/notifications'

const notifications = useNotificationsStore()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const selectedNotification = ref<AppNotification | null>(null)
const subjectOpen = ref(false)
const openingId = ref<number | null>(null)
const notificationTypes: NotificationType[] = ['maintenance_submitted', 'maintenance_updated', 'invoice_reminder', 'invoice_updated', 'owner_announcement']
let activeRoute = ''
let activeIdentity = ''
let routeLoadPending = false
let routeLoadSequence = 0
let canonicalTarget = ''
let canonicalIdentity = ''
let pendingFilterNavigations = 0
let mounted = true

const unreadOnly = computed(() => route.query.unread === '1')
const selectedType = computed<NotificationType | ''>(() => {
  const value = route.query.type
  return typeof value === 'string' && notificationTypes.includes(value as NotificationType) ? value as NotificationType : ''
})
const requestedPage = computed(() => {
  const value = Number(route.query.page)
  return Number.isSafeInteger(value) && value > 0 ? value : 1
})
const unreadDescription = computed(() => notifications.unreadCount === null
  ? 'Theo dõi những cập nhật dành cho bạn.'
  : notifications.unreadCount === 0
    ? 'Bạn đã đọc hết các thông báo.'
    : `Bạn có ${notifications.unreadCount} thông báo chưa đọc.`)
const actionsPending = computed(() => notifications.readingAll || openingId.value !== null)

const identity = computed(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '')

watch([() => route.fullPath, identity], async () => {
  const sequence = ++routeLoadSequence
  if (route.name !== 'notifications' || !identity.value) return
  activeRoute = route.fullPath
  activeIdentity = identity.value
  if (canonicalTarget === route.fullPath && canonicalIdentity === identity.value) return
  routeLoadPending = true
  await notifications.load({ page: requestedPage.value, unreadOnly: unreadOnly.value, type: selectedType.value })
  if (!mounted || sequence !== routeLoadSequence) return
  routeLoadPending = false
  await canonicalizePage()
}, { immediate: true })

watch(() => [notifications.loading, notifications.page, notifications.error, notifications.filter.unreadOnly, notifications.filter.type], () => {
  void canonicalizePage()
}, { flush: 'post' })

async function canonicalizePage() {
  if (!mounted || routeLoadPending || pendingFilterNavigations > 0 || canonicalTarget || route.name !== 'notifications' || !identity.value
    || activeRoute !== route.fullPath || activeIdentity !== identity.value || notifications.loading || notifications.error
    || notifications.filter.unreadOnly !== unreadOnly.value || notifications.filter.type !== selectedType.value
    || notifications.page === requestedPage.value) return

  const query = { ...route.query, page: notifications.page > 1 ? String(notifications.page) : undefined }
  const target = router.resolve({ path: route.path, query, hash: route.hash }).fullPath
  canonicalTarget = target
  canonicalIdentity = identity.value
  try {
    await router.replace({ path: route.path, query, hash: route.hash })
  } finally {
    if (canonicalTarget === target) {
      canonicalTarget = ''
      canonicalIdentity = ''
    }
  }
}

onBeforeUnmount(() => {
  mounted = false
  routeLoadSequence++
})

async function updateFilters(options: { page?: number, unreadOnly?: boolean, type?: NotificationType | '' }) {
  const page = options.page ?? 1
  const unread = options.unreadOnly ?? unreadOnly.value
  const type = options.type ?? selectedType.value
  pendingFilterNavigations++
  try {
    await router.replace({ query: {
      ...route.query,
      page: page > 1 ? String(page) : undefined,
      unread: unread ? '1' : undefined,
      type: type || undefined,
    } })
  } finally {
    pendingFilterNavigations--
    await canonicalizePage()
  }
}

function changeType(event: Event) {
  const value = (event.target as HTMLSelectElement).value
  void updateFilters({ type: notificationTypes.includes(value as NotificationType) ? value as NotificationType : '' })
}

function changeUnread(event: Event) {
  void updateFilters({ unreadOnly: (event.target as HTMLInputElement).checked })
}

async function markRead(item: AppNotification) {
  if (actionsPending.value || notifications.busyIds.includes(item.id)) return
  try {
    await notifications.markRead(item.id)
  } catch {
    // The store presents the API error without retrying a write automatically.
  }
}

async function markAllRead() {
  if (actionsPending.value || notifications.busyIds.length) return
  try {
    await notifications.markAllRead()
  } catch {
    // A failed write remains visible for the user to retry explicitly.
  }
}

async function openSubject(item: AppNotification) {
  if (actionsPending.value || notifications.busyIds.includes(item.id)) return
  openingId.value = item.id
  try {
    if (!item.is_read) {
      await notifications.markRead(item.id)
      if (notifications.actionError) return
    }
    selectedNotification.value = notifications.items.find((entry) => entry.id === item.id) ?? item
    subjectOpen.value = true
  } catch {
    // Do not open the subject after a failed read action.
  } finally {
    openingId.value = null
  }
}
</script>

<template>
  <section class="notifications-page" aria-labelledby="notifications-heading">
    <header class="page-heading">
      <div>
        <span class="eyebrow">Cập nhật dành cho bạn</span>
        <h1 id="notifications-heading">Thông báo</h1>
        <p aria-live="polite">{{ unreadDescription }}</p>
      </div>
      <div class="page-actions">
        <RouterLink v-if="auth.user?.role === 'owner'" class="record-link" to="/owner/announcements">Quản lý thông báo chung</RouterLink>
        <UiButton variant="secondary" :loading="notifications.loading" :disabled="actionsPending" @click="notifications.refresh()">
          <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M16.8 8a7 7 0 1 0-.6 5.4M17 3v5h-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
          Làm mới
        </UiButton>
        <UiButton variant="secondary" :loading="notifications.readingAll" :disabled="notifications.loading || notifications.unreadCount === 0 || !!notifications.busyIds.length || openingId !== null" @click="markAllRead">
          Đánh dấu tất cả đã đọc
        </UiButton>
      </div>
    </header>

    <UiNotice v-if="notifications.actionError" class="action-notice" tone="danger" title="Chưa thể hoàn tất thao tác">
      {{ notifications.actionError }}
    </UiNotice>

    <div class="inbox-card">
      <div class="inbox-toolbar">
        <label class="unread-filter" for="unread-filter">
          <input id="unread-filter" type="checkbox" :checked="unreadOnly" @change="changeUnread" />
          Chỉ xem chưa đọc
        </label>
        <div class="type-filter">
          <label for="notification-type">Loại thông báo</label>
          <select id="notification-type" :value="selectedType" @change="changeType">
            <option value="">Tất cả loại thông báo</option>
            <option v-for="type in notificationTypes" :key="type" :value="type">{{ notificationTypeLabel(type) }}</option>
          </select>
        </div>
      </div>

      <div>
        <UiEmptyState v-if="notifications.loading" tone="loading" title="Đang tải thông báo…" description="Nội dung sẽ hiển thị sau giây lát." />
        <UiEmptyState v-else-if="notifications.error" tone="error" title="Chưa tải được thông báo" :description="notifications.error">
          <UiButton variant="secondary" @click="notifications.refresh()">Thử lại</UiButton>
        </UiEmptyState>
        <UiEmptyState v-else-if="!notifications.items.length" :title="unreadOnly ? 'Không có thông báo chưa đọc' : 'Chưa có thông báo phù hợp'" :description="unreadOnly ? 'Bạn có thể bỏ bộ lọc để xem những thông báo đã đọc.' : selectedType ? 'Hãy chọn loại khác hoặc quay lại sau khi có cập nhật mới.' : 'Những cập nhật mới về hóa đơn và sửa chữa sẽ hiển thị tại đây.'">
          <UiButton v-if="unreadOnly || selectedType" variant="secondary" @click="updateFilters({ unreadOnly: false, type: '' })">Xem tất cả thông báo</UiButton>
        </UiEmptyState>

        <ul v-else class="notification-list" aria-label="Danh sách thông báo">
          <li v-for="item in notifications.items" :key="item.id" class="notification-item" :class="{ 'notification-item--unread': !item.is_read }">
            <span class="notification-icon" aria-hidden="true">
              <svg v-if="item.type === 'invoice_reminder'" viewBox="0 0 24 24" fill="none"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3ZM9 8h6M9 12h6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
              <svg v-else-if="item.type === 'owner_announcement'" viewBox="0 0 24 24" fill="none"><path d="M3 10v4h4l10 5V5L7 10H3Zm4 4 2 7h3l-2-6m10-7v8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
              <svg v-else viewBox="0 0 24 24" fill="none"><path d="m14 4 2 2-4 4 2 2 4-4 2 2a7 7 0 0 1-8 8l-5 5-4-4 5-5a7 7 0 0 1 6-10Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
            </span>
            <article class="notification-content" :aria-labelledby="`notification-title-${item.id}`">
              <div class="notification-meta">
                <UiBadge :tone="item.type === 'invoice_reminder' ? 'warning' : 'info'">{{ notificationTypeLabel(item.type) }}</UiBadge>
                <span class="read-status" :class="{ 'read-status--unread': !item.is_read }"><span v-if="!item.is_read" aria-hidden="true" />{{ item.is_read ? 'Đã đọc' : 'Chưa đọc' }}</span>
              </div>
              <h2 :id="`notification-title-${item.id}`">{{ item.data.title }}</h2>
              <p class="notification-body">{{ item.data.body }}</p>
              <div class="notification-footer">
                <time v-if="item.created_at" :datetime="item.created_at">{{ formatDateTime(item.created_at) }}</time>
                <span v-else class="unknown-time">Chưa có thời gian</span>
                <div class="item-actions">
                  <UiButton v-if="!item.is_read" variant="ghost" :loading="notifications.busyIds.includes(item.id) && openingId !== item.id" :disabled="actionsPending || notifications.busyIds.includes(item.id)" :aria-label="`Đánh dấu đã đọc: ${item.data.title}`" @click="markRead(item)">Đánh dấu đã đọc</UiButton>
                  <UiButton variant="secondary" :loading="openingId === item.id" :disabled="actionsPending || notifications.busyIds.includes(item.id)" :aria-label="`Xem nội dung: ${item.data.title}`" @click="openSubject(item)">Xem nội dung</UiButton>
                </div>
              </div>
            </article>
          </li>
        </ul>
      </div>

      <nav v-if="!notifications.loading && !notifications.error && notifications.total > 0" class="pagination" aria-label="Phân trang thông báo">
        <p>{{ notifications.total }} thông báo <span aria-hidden="true">·</span> Trang {{ notifications.page }} / {{ notifications.lastPage }}</p>
        <div>
          <UiButton variant="secondary" :disabled="notifications.page <= 1 || actionsPending" @click="updateFilters({ page: notifications.page - 1 })">Trang trước</UiButton>
          <UiButton variant="secondary" :disabled="notifications.page >= notifications.lastPage || actionsPending" @click="updateFilters({ page: notifications.page + 1 })">Trang sau</UiButton>
        </div>
      </nav>
    </div>

    <NotificationSubjectDialog v-model:open="subjectOpen" :notification="selectedNotification" />
  </section>
</template>

<style scoped>
.notifications-page { width: 100%; max-width: 1120px; margin: 0 auto; color: var(--color-text); }
.page-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 28px; }
.eyebrow { color: var(--color-primary); font-size: 14px; font-weight: 600; }
.page-heading h1 { margin: 8px 0 12px; font-size: 32px; line-height: 1.35; letter-spacing: -.7px; font-weight: 650; }
.page-heading p { margin: 0; color: var(--color-text-secondary); font-size: 15px; line-height: 1.7; }
.page-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.page-actions svg { flex-shrink: 0; width: 18px; height: 18px; }
.action-notice { margin-bottom: 20px; }
.inbox-card { overflow: hidden; border: 1px solid var(--color-border); border-radius: var(--radius-card); background: var(--color-surface); }
.inbox-toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px 24px; padding: 20px 24px; border-bottom: 1px solid var(--color-border); }
.unread-filter { display: inline-flex; align-items: center; gap: 10px; min-height: 44px; font-size: 14px; font-weight: 500; cursor: pointer; }
.unread-filter input { width: 18px; height: 18px; margin: 0; accent-color: var(--color-primary); }
.type-filter { display: flex; align-items: center; gap: 12px; }
.type-filter label { color: var(--color-text-secondary); font-size: 14px; }
.type-filter select { width: 240px; min-height: 44px; padding: 10px 32px 10px 12px; border: 1px solid var(--color-input-border); border-radius: var(--radius-control); background: var(--color-surface); color: var(--color-text); font: inherit; font-size: 14px; cursor: pointer; }
.notification-list { margin: 0; padding: 0; list-style: none; }
.notification-item { display: flex; align-items: flex-start; gap: 16px; padding: 24px; }
.notification-item + .notification-item { border-top: 1px solid var(--color-border); }
.notification-item--unread { background: #f9fbf8; }
.notification-icon { display: grid; place-items: center; flex-shrink: 0; width: 40px; height: 40px; border: 1px solid var(--color-border); border-radius: 12px; background: var(--color-surface); color: var(--color-primary); }
.notification-icon svg { width: 22px; height: 22px; }
.notification-content { min-width: 0; width: 100%; }
.notification-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
.read-status { display: inline-flex; align-items: center; gap: 6px; color: var(--color-text-secondary); font-size: 12px; }
.read-status--unread { color: var(--color-primary); font-weight: 600; }
.read-status > span { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.notification-content h2 { margin: 12px 0 8px; font-size: 16px; font-weight: 600; line-height: 1.6; overflow-wrap: anywhere; }
.notification-body { margin: 0; color: var(--color-text-secondary); font-size: 14px; line-height: 1.8; white-space: pre-line; overflow-wrap: anywhere; }
.notification-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px 16px; margin-top: 16px; }
.notification-footer time, .unknown-time { color: var(--color-text-secondary); font-size: 12px; line-height: 1.7; }
.item-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.pagination { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 20px 24px; border-top: 1px solid var(--color-border); }
.pagination p { margin: 0; color: var(--color-text-secondary); font-size: 13px; }
.pagination p > span { margin: 0 6px; }
.pagination > div { display: flex; gap: 8px; }
@media (max-width: 900px) {
  .page-heading { align-items: flex-start; flex-direction: column; }
}
@media (max-width: 600px) {
  .page-heading { gap: 20px; margin-bottom: 24px; }
  .page-heading h1 { font-size: 28px; }
  .page-actions { width: 100%; }
  .page-actions > :last-child { flex: 1; }
  .inbox-toolbar { align-items: stretch; flex-direction: column; gap: 12px; padding: 16px; }
  .type-filter { align-items: stretch; flex-direction: column; gap: 8px; }
  .type-filter select { width: 100%; min-height: 48px; font-size: 16px; }
  .notification-item { gap: 12px; padding: 20px 16px; }
  .notification-icon { width: 32px; height: 32px; border-radius: 8px; }
  .notification-icon svg { width: 18px; height: 18px; }
  .notification-meta { align-items: flex-start; gap: 8px; }
  .notification-footer { align-items: stretch; flex-direction: column; gap: 16px; }
  .item-actions { flex-direction: column; }
  .item-actions > * { width: 100%; }
  .pagination { align-items: stretch; flex-direction: column; padding: 20px 16px; text-align: center; }
  .pagination > div { justify-content: center; }
  .pagination > div > * { flex: 1; }
}
</style>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import NotificationSubjectDialog from '@/components/notifications/NotificationSubjectDialog.vue'
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

function subjectLabel(item: AppNotification) {
  if (item.type === 'invoice_reminder' || item.type === 'invoice_updated') return 'Xem hóa đơn'
  if (item.type === 'maintenance_submitted' || item.type === 'maintenance_updated') return 'Xem báo hỏng'
  return 'Xem thông báo'
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
  <section class="management-page tenant-workspace notifications-page" aria-labelledby="notifications-heading">
    <header class="management-header">
      <div>
        <h1 id="notifications-heading">Thông báo</h1>
        <p aria-live="polite">{{ unreadDescription }}</p>
      </div>
      <div class="page-actions">
        <RouterLink v-if="auth.user?.role === 'owner'" class="workflow-card-link" to="/owner/announcements">Quản lý thông báo chung</RouterLink>
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
        <div class="inbox-filters" role="group" aria-label="Trạng thái đọc">
          <button type="button" :aria-pressed="!unreadOnly" @click="updateFilters({ unreadOnly: false })">Tất cả</button>
          <button type="button" :aria-pressed="unreadOnly" @click="updateFilters({ unreadOnly: true })">Chưa đọc</button>
        </div>
        <details class="workflow-disclosure" :open="!!selectedType">
          <summary>Bộ lọc thêm<span v-if="selectedType"> · {{ notificationTypeLabel(selectedType) }}</span></summary>
          <div class="workflow-disclosure-body workflow-extra-fields">
            <div class="filter-field">
              <label for="notification-type">Loại thông báo</label>
              <select id="notification-type" class="ui-select" :value="selectedType" @change="changeType">
                <option value="">Tất cả loại thông báo</option>
                <option v-for="type in notificationTypes" :key="type" :value="type">{{ notificationTypeLabel(type) }}</option>
              </select>
            </div>
            <UiButton variant="ghost" :loading="notifications.loading" :disabled="actionsPending" @click="notifications.refresh()">Làm mới</UiButton>
          </div>
        </details>
      </div>

      <UiEmptyState v-if="notifications.loading" tone="loading" title="Đang tải thông báo…" />
      <UiEmptyState v-else-if="notifications.error" tone="error" title="Chưa tải được thông báo" :description="notifications.error">
        <UiButton variant="secondary" @click="notifications.refresh()">Thử lại</UiButton>
      </UiEmptyState>
      <UiEmptyState v-else-if="!notifications.items.length" :title="unreadOnly ? 'Không có thông báo chưa đọc' : 'Chưa có thông báo phù hợp'" :description="unreadOnly || selectedType ? 'Thử xem tất cả thông báo.' : 'Các cập nhật mới sẽ hiển thị tại đây.'">
        <UiButton v-if="unreadOnly || selectedType" variant="secondary" @click="updateFilters({ unreadOnly: false, type: '' })">Xem tất cả thông báo</UiButton>
      </UiEmptyState>

      <ul v-else class="notification-list" aria-label="Danh sách thông báo">
        <li v-for="item in notifications.items" :key="item.id" class="notification-item" :class="{ 'notification-item--unread': !item.is_read }">
          <article class="notification-content" :aria-labelledby="`notification-title-${item.id}`">
            <div class="notification-meta">
              <span v-if="!item.is_read" class="unread-label">Chưa đọc</span>
              <span>{{ notificationTypeLabel(item.type) }}</span>
              <time v-if="item.created_at" :datetime="item.created_at">{{ formatDateTime(item.created_at) }}</time>
            </div>
            <h2 :id="`notification-title-${item.id}`">{{ item.data.title }}</h2>
            <p class="notification-body">{{ item.data.body }}</p>
            <div class="item-actions">
              <UiButton variant="secondary" :loading="openingId === item.id" :disabled="actionsPending || notifications.busyIds.includes(item.id)"
                :aria-label="`${subjectLabel(item)}: ${item.data.title}`" @click="openSubject(item)">{{ subjectLabel(item) }}</UiButton>
              <UiButton v-if="!item.is_read" variant="ghost" class="mark-read-button"
                :loading="notifications.busyIds.includes(item.id) && openingId !== item.id" :disabled="actionsPending || notifications.busyIds.includes(item.id)"
                :aria-label="`Đánh dấu đã đọc: ${item.data.title}`" title="Đánh dấu đã đọc" @click="markRead(item)">
                Đánh dấu đã đọc
              </UiButton>
            </div>
          </article>
        </li>
      </ul>

      <nav v-if="!notifications.loading && !notifications.error && notifications.lastPage > 1" class="pagination" aria-label="Phân trang thông báo">
        <p>{{ notifications.total }} thông báo · Trang {{ notifications.page }} / {{ notifications.lastPage }}</p>
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
.notifications-page { width: 100%; max-width: 1120px; margin: 0 auto; }
.page-actions, .inbox-toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 8px 12px; }
.notification-body { margin: 0; color: var(--color-text-secondary); font-size: 14px; }
.action-notice { margin-bottom: 16px; }
.inbox-card { border: 1px solid var(--color-border); border-radius: var(--radius-card); background: var(--color-surface); }
.inbox-toolbar { justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--color-border); }
.inbox-toolbar > details[open] { width: 100%; }
.inbox-filters, .item-actions { display: flex; flex-wrap: wrap; gap: 4px; }
.inbox-filters button { min-height: 44px; padding: 8px 14px; border: 1px solid transparent; border-radius: 8px; color: var(--color-text-secondary); background: transparent; font: inherit; font-size: 14px; cursor: pointer; }
.inbox-filters button[aria-pressed="true"] { color: var(--color-action-text); background: var(--color-action); font-weight: 650; }
.notification-list { margin: 0; padding: 0; list-style: none; }
.notification-item { padding: 16px 18px; border-inline-start: 4px solid transparent; }
.notification-item + .notification-item { border-top: 1px solid var(--color-border); }
.notification-item--unread { border-inline-start-color: var(--color-focus); background: var(--color-primary-soft); }
.notification-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; color: var(--color-text-secondary); font-size: 12px; }
.notification-meta time { margin-inline-start: auto; }
.notification-content h2 { margin: 6px 0; font-size: 17px; overflow-wrap: anywhere; }
.unread-label { display: inline-flex; align-items: center; gap: 6px; color: var(--color-success); font-weight: 700; }
.unread-label::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.notification-body { display: -webkit-box; overflow: hidden; overflow-wrap: anywhere; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.item-actions { margin-top: 10px; }
.item-actions > button { min-height: 44px; }
.mark-read-button { min-width: 44px; }
.pagination { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--color-border); }
.pagination p { margin: 0; color: var(--color-text-secondary); font-size: 13px; }
.pagination > div { display: flex; flex-wrap: wrap; gap: 8px; }
@media (max-width: 560px) {
  .page-actions > button { width: 100%; }
  .notification-item { padding-inline: 12px; }
  .notification-meta time { margin-inline-start: 0; }
}
</style>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import NotificationBell from '@/components/notifications/NotificationBell.vue'
import PwaInstallButton from '@/components/pwa/PwaInstallButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import '@/redesign/owner-preview.css'

type NavigationItem = { label: string; to: string }
type DirectoryItem = NavigationItem & { icon: string }
type NavigationSection = { label: string; items: DirectoryItem[] }

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const menuOpen = ref(false)
let menuBackdropPressed = false

function isMenuBackdrop(event: MouseEvent) {
  const panel = event.currentTarget
  if (!(panel instanceof HTMLDialogElement) || event.target !== panel) return false
  const bounds = panel.getBoundingClientRect()
  return event.clientX < bounds.left || event.clientX >= bounds.right
    || event.clientY < bounds.top || event.clientY >= bounds.bottom
}

function closeMenuFromBackdrop(event: MouseEvent) {
  if (menuBackdropPressed && isMenuBackdrop(event)) menuOpen.value = false
  menuBackdropPressed = false
}
const isLoggingOut = ref(false)
const logoutError = ref('')

const ownerNavigation: NavigationSection[] = [
  { label: '', items: [{ label: 'Tổng quan', to: '/owner', icon: 'overview' }, { label: 'Công việc', to: '/owner/work', icon: 'tasks' }] },
  { label: 'Quản lý thuê', items: [
    { label: 'Phòng', to: '/owner/rooms', icon: 'room' },
    { label: 'Người thuê', to: '/owner/tenants', icon: 'person' },
    { label: 'Nhóm thuê', to: '/owner/groups', icon: 'people' },
    { label: 'Quá trình thuê', to: '/owner/stays', icon: 'calendar' },
  ] },
  { label: 'Tài chính', items: [
    { label: 'Tổng quan tài chính', to: '/owner/finance', icon: 'wallet' },
    { label: 'Hóa đơn', to: '/owner/invoices', icon: 'invoice' },
    { label: 'Biên nhận thu tiền', to: '/owner/payments', icon: 'receipt' },
    { label: 'Công nợ', to: '/owner/receivables', icon: 'wallet' },
    { label: 'Thu chi', to: '/owner/cash', icon: 'wallet' },
  ] },
  { label: 'Vận hành', items: [
    { label: 'Điện nước', to: '/owner/meters', icon: 'meter' },
    { label: 'Dịch vụ', to: '/owner/services', icon: 'settings' },
    { label: 'Báo hỏng', to: '/owner/maintenance', icon: 'repair' },
    { label: 'Thông báo chung', to: '/owner/announcements', icon: 'bell' },
    { label: 'Thông tin nhà trọ', to: '/owner/property', icon: 'home' },
    { label: 'Chờ xóa', to: '/owner/pending-deletions', icon: 'trash' },
  ] },
]

const tenantNavigation: DirectoryItem[] = [
  { label: 'Phòng của tôi', to: '/tenant', icon: 'room' },
  { label: 'Hóa đơn', to: '/tenant/invoices', icon: 'invoice' },
  { label: 'Báo hỏng', to: '/tenant/maintenance', icon: 'repair' },
  { label: 'Lịch sử cư trú', to: '/tenant/stays', icon: 'calendar' },
  { label: 'Thông tin cá nhân', to: '/tenant/profile', icon: 'person' },
]
const isOwner = computed(() => auth.user?.role === 'owner')
const navigation = computed<NavigationSection[]>(() => {
  if (auth.user?.needs_property) return []
  if (!auth.user?.properties) return isOwner.value ? ownerNavigation : [{ label: '', items: tenantNavigation }]
  if (isOwner.value) {
    return [
      ownerNavigation[0]!,
      { label: 'Tiếp nhận', items: [
        { label: 'Thành viên khu trọ', to: '/owner/members', icon: 'people' },
        { label: 'Tin phòng cho thuê', to: '/owner/marketplace/listings', icon: 'room' },
        { label: 'Lịch xem và yêu cầu thuê', to: '/owner/marketplace/requests', icon: 'calendar' },
      ] },
      ...ownerNavigation.slice(1),
    ]
  }
  return [{ label: '', items: [
    { label: 'Khu trọ của tôi', to: '/tenant/workspace', icon: 'home' },
    ...(auth.user.tenant_id !== null ? tenantNavigation : []),
    { label: 'Tìm phòng trọ', to: '/find-rooms', icon: 'search' },
    { label: 'Yêu cầu của tôi', to: '/my-room-requests', icon: 'tasks' },
  ] }]
})

const tenantHome = computed(() => auth.user?.needs_property ? '/properties'
  : auth.user?.properties ? '/tenant/workspace' : '/tenant')
const home = computed(() => isOwner.value ? '/owner/work' : tenantHome.value)
const property = computed(() => auth.user?.properties?.find(p => p.id === auth.user?.property_id))
const mainNavigation = computed<NavigationItem[]>(() => {
  if (auth.user?.needs_property) return [{ label: 'Chọn khu trọ', to: '/properties' }]
  if (isOwner.value) return [
    { label: 'Tổng quan', to: '/owner' },
    { label: 'Công việc', to: '/owner/work' }, { label: 'Phòng', to: '/owner/rooms' },
    { label: 'Tài chính', to: '/owner/finance' },
  ]
  return [
    { label: 'Phòng', to: tenantHome.value },
    ...(!auth.user?.properties || auth.user.tenant_id !== null ? [
      { label: 'Hóa đơn', to: '/tenant/invoices' },
      { label: 'Báo hỏng', to: '/tenant/maintenance' },
    ] : [{ label: 'Tìm phòng', to: '/find-rooms' }]),
  ]
})
const title = computed(() => String(route.meta.title ?? 'RentBase'))
const isOverview = computed(() => route.name === 'owner-work-preview' || route.name === 'owner-finance-preview')
function isActive(path: string) {
  return route.path === path || (path !== '/owner' && path !== '/tenant' && route.path.startsWith(path + '/'))
}
watch(() => route.fullPath, () => { menuOpen.value = false })

async function logout() {
  if (isLoggingOut.value) return
  isLoggingOut.value = true
  logoutError.value = ''
  try {
    await auth.logout()
    await router.replace({ name: 'login', query: { reason: 'logged-out' } })
  } catch {
    logoutError.value = 'Chưa thể đăng xuất. Vui lòng kiểm tra kết nối rồi thử lại.'
  } finally {
    isLoggingOut.value = false
  }
}
</script>

<template>
  <div class="rb7 rb7-theme rb7-workspace" :class="isOwner ? 'owner-shell' : 'tenant-shell'">
    <a class="rb7-skip" href="#main-content">Đến nội dung chính</a>
    <header class="rb7-top">
      <div class="rb7-bar">
        <RouterLink class="rb7-brand" :to="home" aria-label="RentBase, trang chính"><img src="/icons/rentbase-mark.svg" width="34" height="34" alt="" />RentBase</RouterLink>
        <nav class="rb7-nav" aria-label="Các mục thường dùng">
          <RouterLink v-for="link in mainNavigation" :key="link.to" :to="link.to" :aria-current="isActive(link.to) ? 'page' : undefined">{{ link.label }}</RouterLink>
          <button class="rb7-header-action" type="button" aria-label="Mở menu" aria-haspopup="dialog" :aria-expanded="menuOpen" @click="menuOpen = true">Danh mục</button>
        </nav>
        <div class="rb7-session">
          <PwaInstallButton compact />
          <NotificationBell v-if="!auth.user?.needs_property" />
          <button class="rb7-header-action rb7-signout" type="button" :disabled="isLoggingOut" :aria-label="isLoggingOut ? 'Đang đăng xuất…' : 'Đăng xuất'" @click="logout">
            <svg class="rb7-signout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
              <path d="M11 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h6m4-12 4 4-4 4m4-4H9" />
            </svg><span class="rb7-signout-label">{{ isLoggingOut ? 'Đang đăng xuất…' : 'Đăng xuất' }}</span>
          </button>
        </div>
      </div>
    </header>
    <div v-if="logoutError" class="rb7-message rb7-logout-error" role="alert">
      {{ logoutError }} <button type="button" aria-label="Đóng thông báo lỗi" @click="logoutError = ''">Đóng</button>
    </div>
    <main id="main-content" class="rb7-content" tabindex="-1">
      <div v-if="!isOverview" class="rb7-location">
        <RouterLink v-if="auth.user?.properties" class="record-link" to="/properties">{{ property?.name ?? 'Chọn khu trọ' }}</RouterLink>
        <span v-else>{{ isOwner ? 'Chủ trọ' : 'Người thuê' }}</span>
        <span aria-hidden="true">/</span><span>{{ title }}</span>
      </div>
      <div :key="route.path" class="rb7-view">
        <RouterView :key="auth.user ? auth.user.id + ':' + auth.user.role + ':' + (auth.user.property_id ?? '') : 'anonymous'" />
      </div>
    </main>
    <footer class="rb7-footer"><span>RentBase</span></footer>
    <nav class="rb7-mobile" aria-label="Các mục thường dùng trên điện thoại">
      <RouterLink v-for="link in mainNavigation" :key="link.to" :to="link.to" :aria-current="isActive(link.to) ? 'page' : undefined">{{ link.label }}</RouterLink>
      <button type="button" class="rb7-header-action" aria-label="Mở menu" aria-haspopup="dialog" :aria-expanded="menuOpen" @click="menuOpen = true">Thêm</button>
    </nav>
    <UiDialog v-model:open="menuOpen" title="Điều hướng chính" close-label="Đóng menu" class="rb7-drawer rb7-directory" @pointerdown="menuBackdropPressed = isMenuBackdrop($event)" @click="closeMenuFromBackdrop">
      <div class="rb7-directory-heading">
        <strong>{{ auth.user?.username }}</strong>
        <RouterLink class="record-link" to="/account">Tài khoản</RouterLink>
        <RouterLink v-if="auth.user?.properties" class="record-link" to="/properties">Đổi khu trọ</RouterLink>
      </div>
      <nav aria-label="Điều hướng chính" class="rb7-directory-grid">
        <section v-for="section in navigation" :key="section.label || 'main'">
          <h3 v-if="section.label">{{ section.label }}</h3>
          <RouterLink v-for="link in section.items" :key="link.to" :to="link.to" :aria-current="isActive(link.to) ? 'page' : undefined" @click="route.fullPath === link.to && (menuOpen = false)">
            <span class="rb7-directory-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" focusable="false"><use :href="`/icons/workspace-symbols.svg#${link.icon}`" /></svg>
            </span>
            <span>{{ link.label }}</span>
            <svg class="rb7-directory-arrow" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m9 6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" /></svg>
          </RouterLink>
        </section>
      </nav>
    </UiDialog>
  </div>
</template>

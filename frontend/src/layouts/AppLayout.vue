<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getRoleHome } from '@/router/navigation'
import NotificationBell from '@/components/notifications/NotificationBell.vue'

type NavigationItem = { label: string; to: string; icon: string }

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const isMenuOpen = ref(false)
const isLoggingOut = ref(false)
const logoutError = ref('')
const isDemo = import.meta.env.VITE_DEMO_MODE === '1'
const isPresentation = import.meta.env.VITE_PRESENTATION_MODE === '1'
const sidebar = ref<HTMLElement | null>(null)
const menuButton = ref<HTMLButtonElement | null>(null)
let previousBodyOverflow = ''

const icons = {
  home: 'M3 10.5 12 3l9 7.5M5 9.5V21h5v-7h4v7h5V9.5',
  rooms: 'M4 21V4a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v17M2 21h20M8 7h2m4 0h2M8 11h2m4 0h2M10 21v-6h4v6',
  services: 'm13 2-9 12h7l-1 8 10-12h-7l1-8Z',
  tenants: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8m7-7.87a4 4 0 0 1 0 7.75',
  stays: 'M8 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3M12 7l5 5-5 5M7 12h14',
  meters: 'M3 14a9 9 0 1 1 18 0v5H3v-5Zm9-1 4-5m-4 7v.01M6 10l1 1m10 0 1-1M12 5v1',
  invoices: 'M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6m-6 4h6m-6 4h3',
  receivables: 'M3 5h18v14H3V5Zm0 4h18m-5 5h2M7 14h3',
  cash: 'M3 7h18v13H3V7Zm3 0V4h12v3m-3 6h6m-4 0v.01',
  maintenance: 'm14 6 4-4a6 6 0 0 1-7.8 7.8L3 17a2.83 2.83 0 0 0 4 4l7.2-7.2A6 6 0 0 0 22 6l-4 4-4-4Z',
  profile: 'M20 21v-2a7 7 0 0 0-14 0v2M13 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8',
  account: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6m8-3 2-1-2-4-2 1-2-1V4H8v3L6 8 4 7l-2 4 2 1v2l-2 1 2 4 2-1 2 1v3h8v-3l2-1 2 1 2-4-2-1v-2Z',
}

const ownerNavigation: NavigationItem[] = [
  { label: 'Tổng quan', to: '/owner', icon: icons.home },
  { label: 'Phòng', to: '/owner/rooms', icon: icons.rooms },
  { label: 'Dịch vụ', to: '/owner/services', icon: icons.services },
  { label: 'Người thuê', to: '/owner/tenants', icon: icons.tenants },
  { label: 'Quá trình thuê', to: '/owner/stays', icon: icons.stays },
  { label: 'Điện nước', to: '/owner/meters', icon: icons.meters },
  { label: 'Hóa đơn', to: '/owner/invoices', icon: icons.invoices },
  { label: 'Công nợ', to: '/owner/receivables', icon: icons.receivables },
  { label: 'Thu chi', to: '/owner/cash', icon: icons.cash },
  { label: 'Sửa chữa', to: '/owner/maintenance', icon: icons.maintenance },
  { label: 'Thông báo chung', to: '/owner/announcements', icon: 'M3 10v4h4l10 5V5L7 10H3Zm4 4 2 7h3l-2-6m10-7v8' },
  { label: 'Thông tin nhà trọ', to: '/owner/property', icon: icons.account },
]

const tenantNavigation: NavigationItem[] = [
  { label: 'Phòng của tôi', to: '/tenant', icon: icons.home },
  { label: 'Hóa đơn', to: '/tenant/invoices', icon: icons.invoices },
  { label: 'Báo hỏng', to: '/tenant/maintenance', icon: icons.maintenance },
  { label: 'Lịch sử cư trú', to: '/tenant/stays', icon: icons.stays },
  { label: 'Thông tin cá nhân', to: '/tenant/profile', icon: icons.profile },
]
const tenantBottomNavigation: NavigationItem[] = [
  { label: 'Phòng', to: '/tenant', icon: icons.home },
  { label: 'Hóa đơn', to: '/tenant/invoices', icon: icons.invoices },
  { label: 'Báo hỏng', to: '/tenant/maintenance', icon: icons.maintenance },
  { label: 'Cá nhân', to: '/tenant/profile', icon: icons.profile },
]
function bottomActive(path: string) { return path === '/tenant/profile' ? ['/tenant/profile', '/tenant/stays', '/account'].includes(route.path) : path === '/tenant' ? route.path === path : route.path.startsWith(path) }

const isOwner = computed(() => auth.user?.role === 'owner')
const navigation = computed(() => isOwner.value ? ownerNavigation : tenantNavigation)
const roleLabel = computed(() => isOwner.value ? 'Chủ trọ' : 'Người thuê')
const home = computed(() => getRoleHome(auth.user?.role ?? 'tenant'))
const title = computed(() => typeof route.meta.title === 'string' ? route.meta.title : 'Không gian của bạn')
const initials = computed(() => (auth.user?.username || 'U').slice(0, 2).toUpperCase())

function isActive(path: string) {
  return path === home.value ? route.path === path : route.path === path || route.path.startsWith(`${path}/`)
}

function closeMenu() {
  isMenuOpen.value = false
}

function handleWindowResize() {
  if (window.innerWidth >= 1024) closeMenu()
}

function handleKeydown(event: KeyboardEvent) {
  if (!isMenuOpen.value) return
  if (event.key === 'Escape') {
    event.preventDefault()
    closeMenu()
  }
  if (event.key !== 'Tab' || !sidebar.value) return
  const focusable = [...sidebar.value.querySelectorAll<HTMLElement>('a[href], button:not([disabled])')]
    .filter((element) => element.getClientRects().length > 0)
  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (!first || !last) return
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

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

watch(() => route.fullPath, closeMenu)
watch(isMenuOpen, async (open) => {
  if (open) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    await nextTick()
    if (isMenuOpen.value) sidebar.value?.querySelector<HTMLElement>('button, a[href]')?.focus()
  } else {
    document.body.style.overflow = previousBodyOverflow
    if (window.innerWidth < 1024) menuButton.value?.focus()
  }
})

onMounted(() => {
  window.addEventListener('keydown', handleKeydown)
  window.addEventListener('resize', handleWindowResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('resize', handleWindowResize)
  if (isMenuOpen.value) document.body.style.overflow = previousBodyOverflow
})
</script>

<template>
  <div class="app-shell" :class="{ 'tenant-shell': !isOwner }">
    <a class="skip-link" href="#main-content">Đến nội dung chính</a>
    <button v-if="isMenuOpen" class="sidebar-backdrop" aria-label="Đóng menu" tabindex="-1" @click="closeMenu" />

    <aside id="app-navigation" ref="sidebar" class="sidebar" :class="{ 'is-open': isMenuOpen }" :role="isMenuOpen ? 'dialog' : undefined" :aria-modal="isMenuOpen ? true : undefined" aria-label="Điều hướng chính">
      <div class="sidebar-top">
        <RouterLink :to="home" class="brand" aria-label="Quản lý nhà trọ — trang chính">
          <span class="brand-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path :d="icons.rooms" /></svg>
          </span>
          <span><strong>Nhà Trọ</strong><small>Không gian quản lý</small></span>
        </RouterLink>
        <button class="icon-button close-navigation" aria-label="Đóng menu" @click="closeMenu">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
        </button>
      </div>

      <div class="role-switch-label"><span class="role-dot" />{{ roleLabel }}</div>
      <div class="navigation-label">Không gian của bạn</div>
      <nav class="main-navigation">
        <RouterLink v-for="item in navigation" :key="item.to" :to="item.to" class="navigation-link" :class="{ 'is-active': isActive(item.to) }" :aria-current="isActive(item.to) ? 'page' : undefined">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path :d="item.icon" /></svg>
          <span>{{ item.label }}</span>
          <span v-if="isActive(item.to)" class="active-dot" aria-hidden="true" />
        </RouterLink>
      </nav>

      <div class="sidebar-bottom">
        <RouterLink to="/account" class="navigation-link" :class="{ 'is-active': route.path === '/account' }" :aria-current="route.path === '/account' ? 'page' : undefined">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path :d="icons.account" /></svg>
          <span>Tài khoản</span>
        </RouterLink>
        <div class="sidebar-account">
          <span class="avatar" aria-hidden="true">{{ initials }}</span>
          <div class="account-summary"><strong>{{ auth.user?.username }}</strong><span>{{ roleLabel }}</span></div>
          <span class="account-status" title="Đã đăng nhập" aria-label="Đã đăng nhập" />
        </div>
      </div>
    </aside>

    <div class="main-shell" :inert="isMenuOpen ? true : undefined">
      <header class="topbar">
        <div class="page-heading">
          <button ref="menuButton" class="icon-button menu-toggle" aria-label="Mở menu" aria-controls="app-navigation" :aria-expanded="isMenuOpen" @click="isMenuOpen = true">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
          </button>
          <div class="breadcrumb"><span>{{ roleLabel }}</span><span class="breadcrumb-separator" aria-hidden="true">/</span><strong>{{ title }}</strong></div>
        </div>
        <div class="topbar-actions">
          <NotificationBell />
          <span v-if="auth.user" class="connection-status" :class="{ 'has-error': auth.status === 'error' }"><span />{{ auth.status === 'error' ? 'Gián đoạn kết nối' : auth.status === 'loading' ? 'Đang kiểm tra…' : 'Đã đăng nhập' }}</span>
          <span class="topbar-divider" aria-hidden="true" />
          <button class="logout-button" :disabled="isLoggingOut" @click="logout">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9" /></svg>
            <span>{{ isLoggingOut ? 'Đang đăng xuất…' : 'Đăng xuất' }}</span>
          </button>
        </div>
      </header>

      <div v-if="logoutError" class="logout-alert" role="alert">
        <span>{{ logoutError }}</span>
        <button type="button" aria-label="Đóng thông báo lỗi" @click="logoutError = ''">×</button>
      </div>

      <p v-if="isDemo" class="demo-banner" role="status">{{ isPresentation ? 'Bản trình đánh giá · Dữ liệu giả riêng, được giữ khi khởi động lại.' : 'Bản dùng thử · Dữ liệu mẫu được giữ khi khởi động lại.' }} Các tab cùng trình duyệt dùng chung tài khoản đăng nhập.</p>
      <main id="main-content" class="main-content" tabindex="-1"><RouterView :key="auth.user ? `${auth.user.id}:${auth.user.role}:${route.path}` : 'anonymous'" /></main>
      <footer class="app-footer"><span>Quản lý nhà trọ</span><span>Dữ liệu hiển thị theo quyền tài khoản</span></footer>
      <nav v-if="!isOwner && !isMenuOpen" class="tenant-bottom-navigation" aria-label="Điều hướng người thuê"><RouterLink v-for="item in tenantBottomNavigation" :key="item.to" :to="item.to" :class="{ 'is-active': bottomActive(item.to) }" :aria-current="bottomActive(item.to) ? 'page' : undefined"><svg viewBox="0 0 24 24" aria-hidden="true"><path :d="item.icon" /></svg><span>{{ item.label }}</span></RouterLink></nav>
    </div>
  </div>
</template>

<style scoped>
.app-shell {
  min-height: 100dvh;
  color: var(--color-text);
  background: var(--color-bg);
}
.skip-link {
  position: fixed;
  z-index: 100;
  top: -80px;
  left: 16px;
  padding: 12px 16px;
  border-radius: 10px;
  color: #fff;
  background: var(--color-primary);
}
.skip-link:focus { top: 16px; }
.sidebar {
  position: fixed;
  z-index: 40;
  inset: 0 auto 0 0;
  display: flex;
  flex-direction: column;
  width: 264px;
  padding: 24px 16px 16px;
  border-right: 1px solid var(--color-border);
  background: var(--color-surface);
}
.sidebar-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 0 8px;
}
.brand {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  min-height: 48px;
  color: var(--color-text);
  text-decoration: none;
}
.brand-mark {
  display: grid;
  flex: 0 0 auto;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  color: #fff;
  background: var(--color-primary);
}
.brand-mark svg { width: 24px; height: 24px; }
.brand strong { display: block; font-size: 20px; font-weight: 700; line-height: 1.3; letter-spacing: -.4px; }
.brand small { display: block; margin-top: 4px; color: var(--color-text-secondary); font-size: 12px; line-height: 1.5; }
.role-switch-label {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  margin: 24px 8px;
  padding: 8px 12px;
  border: 1px solid var(--color-border);
  border-radius: 10px;
  color: var(--color-primary);
  background: var(--color-primary-soft);
  font-size: 14px;
  font-weight: 600;
}
.role-dot, .connection-status > span, .account-status {
  flex: 0 0 auto;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--color-primary);
}
.navigation-label { padding: 0 16px 8px; color: var(--color-text-muted); font-size: 12px; font-weight: 600; line-height: 1.5; }
.main-navigation { display: flex; flex: 1; flex-direction: column; gap: 4px; min-height: 0; overflow-y: auto; padding: 4px 0 8px; scrollbar-width: thin; }
.navigation-link {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 12px;
  min-height: 44px;
  padding: 10px 16px;
  border-radius: 10px;
  color: var(--color-text-secondary);
  font-size: 14px;
  font-weight: 500;
  line-height: 1.5;
  text-decoration: none;
  transition: background .16s, color .16s;
}
.navigation-link:hover { color: var(--color-primary); background: var(--color-primary-soft); }
.navigation-link svg { flex: 0 0 auto; width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.navigation-link.is-active { color: var(--color-primary); background: var(--color-primary-soft); font-weight: 700; }
.active-dot { width: 6px; height: 6px; margin-left: auto; border-radius: 50%; background: currentColor; }
.sidebar-bottom { flex: 0 0 auto; padding-top: 12px; }
.sidebar-account { display: flex; align-items: center; gap: 12px; margin: 12px 8px 0; padding: 16px 0 0; border-top: 1px solid var(--color-border); }
.avatar { display: grid; flex: 0 0 auto; place-items: center; width: 40px; height: 40px; border: 1px solid var(--color-border); border-radius: 50%; color: var(--color-primary); background: var(--color-primary-soft); font-size: 12px; font-weight: 700; }
.account-summary { min-width: 0; }
.account-summary strong { display: block; overflow: hidden; color: var(--color-text); font-size: 14px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.account-summary > span { display: block; margin-top: 4px; color: var(--color-text-secondary); font-size: 12px; line-height: 1.5; }
.account-status { margin-left: auto; }
.main-shell { display: flex; flex-direction: column; min-height: 100dvh; margin-left: 264px; }
.tenant-bottom-navigation { display: none; }
.demo-banner { margin: 0; padding: 12px 24px; border-bottom: 1px solid var(--color-border); color: var(--color-primary); background: var(--color-primary-soft); font-size: 13px; line-height: 1.7; }
.topbar { display: flex; align-items: center; justify-content: space-between; gap: 24px; min-height: 80px; padding: 16px 40px; border-bottom: 1px solid var(--color-border); background: var(--color-surface); }
.page-heading { display: flex; align-items: center; gap: 16px; min-width: 0; }
.breadcrumb { display: flex; align-items: center; gap: 12px; min-width: 0; color: var(--color-text-secondary); font-size: 14px; line-height: 1.5; }
.breadcrumb strong { overflow: hidden; color: var(--color-text); font-size: 14px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.breadcrumb-separator { color: var(--color-text-muted); }
.topbar-actions { display: flex; flex: 0 0 auto; align-items: center; gap: 16px; }
.connection-status { display: inline-flex; align-items: center; gap: 8px; color: var(--color-text-secondary); font-size: 12px; }
.connection-status.has-error { color: var(--color-danger); }
.connection-status.has-error > span { background: var(--color-danger); }
.topbar-divider { width: 1px; height: 24px; background: var(--color-border); }
.logout-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 10px 12px; border: 1px solid transparent; border-radius: 10px; color: var(--color-text-secondary); background: transparent; font: inherit; font-size: 14px; font-weight: 500; cursor: pointer; }
.logout-button:hover { color: var(--color-text); background: var(--color-bg); border-color: var(--color-border); }
.logout-button:disabled { opacity: .65; cursor: wait; }
.logout-button svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.main-content { flex: 1; width: 100%; max-width: 1440px; margin: 0 auto; padding: 40px; outline: none; }
.app-footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 40px 24px; color: var(--color-text-muted); font-size: 12px; line-height: 1.6; }
.logout-alert { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin: 16px 40px 0; padding: 8px 8px 8px 16px; border: 1px solid #e5beb8; border-radius: 12px; color: var(--color-danger); background: var(--color-danger-soft); font-size: 14px; line-height: 1.6; }
.logout-alert button { flex: 0 0 auto; width: 44px; height: 44px; padding: 0; border: 0; border-radius: 8px; color: inherit; background: transparent; font-size: 24px; cursor: pointer; }
.icon-button { display: grid; flex: 0 0 auto; place-items: center; width: 44px; height: 44px; padding: 10px; border: 1px solid var(--color-border); border-radius: 10px; color: var(--color-text-secondary); background: var(--color-surface); cursor: pointer; }
.icon-button:hover { color: var(--color-primary); background: var(--color-primary-soft); }
.icon-button svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.menu-toggle, .close-navigation, .sidebar-backdrop { display: none; }
a:focus-visible, button:focus-visible { outline: 3px solid var(--color-focus); outline-offset: 3px; }
@media (min-width: 1024px) and (max-height: 820px) {
  .sidebar { padding-top: 16px; }
  .role-switch-label { margin-top: 16px; margin-bottom: 16px; }
  .sidebar-bottom { padding-top: 8px; }
}
@media (max-width: 1199px) {
  .topbar, .main-content, .app-footer { padding-right: 24px; padding-left: 24px; }
}
@media (max-width: 1023px) {
  .sidebar { width: min(320px, calc(100vw - 24px)); transform: translateX(-100%); visibility: hidden; transition: transform .2s ease, visibility .2s; }
  .sidebar.is-open { transform: translateX(0); visibility: visible; }
  .sidebar-backdrop { position: fixed; z-index: 35; inset: 0; display: block; width: 100%; height: 100%; border: 0; background: #233b3266; }
  .close-navigation, .menu-toggle { display: grid; }
  .main-shell { margin-left: 0; }
  .topbar { min-height: 80px; }
  .icon-button, .logout-button, .navigation-link { min-height: 48px; }
  .icon-button { width: 48px; height: 48px; }
}
@media (max-width: 600px) {
  .tenant-shell .main-shell { padding-bottom: calc(76px + env(safe-area-inset-bottom, 0px)); }
  .tenant-bottom-navigation { position: fixed; z-index: 30; bottom: 0; right: 0; left: 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); padding: 6px 8px calc(6px + env(safe-area-inset-bottom, 0px)); border-top: 1px solid var(--color-border); background: var(--color-surface); }
  .tenant-bottom-navigation a { display: grid; justify-items: center; align-content: center; gap: 5px; min-height: 56px; padding: 6px; border-radius: 10px; color: var(--color-text-secondary); text-decoration: none; font-size: 12px; }
  .tenant-bottom-navigation a.is-active { color: var(--color-primary); background: var(--color-primary-soft); font-weight: 700; }
  .tenant-bottom-navigation svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
  .topbar { gap: 8px; padding: 12px 16px; }
  .page-heading { gap: 12px; }
  .breadcrumb { gap: 0; }
  .breadcrumb > span { display: none; }
  .breadcrumb strong { max-width: 135px; }
  .topbar-actions { gap: 0; }
  .connection-status, .topbar-divider { display: none; }
  .logout-button { gap: 6px; padding-right: 0; padding-left: 8px; font-size: 12px; }
  .logout-button svg { width: 18px; height: 18px; }
  .main-content { padding: 24px 16px; }
  .app-footer { flex-direction: column; align-items: flex-start; gap: 4px; padding: 8px 16px 24px; }
  .logout-alert { margin-right: 16px; margin-left: 16px; }
}
@media (max-width: 359px) {
  .page-heading { gap: 8px; }
  .breadcrumb strong { max-width: 104px; font-size: 12px; }
}
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { transition: none !important; }
}
</style>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getRoleHome } from '@/router/navigation'
import NotificationBell from '@/components/notifications/NotificationBell.vue'
import PwaInstallButton from '@/components/pwa/PwaInstallButton.vue'

type NavigationItem = { label: string; to: string; icon: string }
type NavigationSection = { label: string; items: NavigationItem[] }

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const isMenuOpen = ref(false)
const isLoggingOut = ref(false)
const logoutError = ref('')
const isDemo = import.meta.env.VITE_DEMO_MODE === '1'
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

const ownerNavigation: NavigationSection[] = [
  { label: '', items: [{ label: 'Tổng quan', to: '/owner', icon: icons.home }] },
  { label: 'Quản lý thuê', items: [
    { label: 'Phòng', to: '/owner/rooms', icon: icons.rooms },
    { label: 'Người thuê', to: '/owner/tenants', icon: icons.tenants },
    { label: 'Nhóm thuê', to: '/owner/groups', icon: icons.tenants },
    { label: 'Quá trình thuê', to: '/owner/stays', icon: icons.stays },
  ] },
  { label: 'Tài chính', items: [
    { label: 'Hóa đơn', to: '/owner/invoices', icon: icons.invoices },
    { label: 'Biên nhận thu tiền', to: '/owner/payments', icon: icons.invoices },
    { label: 'Công nợ', to: '/owner/receivables', icon: icons.receivables },
    { label: 'Thu chi', to: '/owner/cash', icon: icons.cash },
  ] },
  { label: 'Vận hành', items: [
    { label: 'Điện nước', to: '/owner/meters', icon: icons.meters },
    { label: 'Dịch vụ', to: '/owner/services', icon: icons.services },
    { label: 'Sửa chữa', to: '/owner/maintenance', icon: icons.maintenance },
    { label: 'Thông báo chung', to: '/owner/announcements', icon: 'M3 10v4h4l10 5V5L7 10H3Zm4 4 2 7h3l-2-6m10-7v8' },
    { label: 'Thông tin nhà trọ', to: '/owner/property', icon: icons.account },
  ] },
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
const desktopNav = ref<HTMLElement | null>(null)
const openGroup = ref('')
const ownerGroups = ownerNavigation.filter(section => section.label)
  .map(section => ({ ...section, items: section.items.filter(item => item.to !== '/owner/rooms') }))
const navigation = computed(() => isOwner.value ? ownerNavigation : [{ label: '', items: tenantNavigation }])
const roleLabel = computed(() => isOwner.value ? 'Chủ trọ' : 'Người thuê')
const home = computed(() => getRoleHome(auth.user?.role ?? 'tenant'))
const title = computed(() => typeof route.meta.title === 'string' ? route.meta.title : 'Không gian của bạn')
const initials = computed(() => (auth.user?.username || 'U').slice(0, 2).toUpperCase())

function isActive(path: string) {
  return path === home.value ? route.path === path : route.path === path || route.path.startsWith(`${path}/`)
}

function closeMenu() {
  isMenuOpen.value = false
  openGroup.value = ''
}

function handleWindowResize() {
  if (window.innerWidth >= 1024) closeMenu()
  else openGroup.value = ''
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && openGroup.value) {
    desktopNav.value?.querySelector<HTMLButtonElement>('[aria-expanded="true"]')?.focus()
    openGroup.value = ''
    return
  }
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

function closeGroupOutside(event: PointerEvent) {
  if (event.target instanceof Node && !desktopNav.value?.contains(event.target)) openGroup.value = ''
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
  window.addEventListener('pointerdown', closeGroupOutside)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleKeydown)
  window.removeEventListener('resize', handleWindowResize)
  window.removeEventListener('pointerdown', closeGroupOutside)
  if (isMenuOpen.value) document.body.style.overflow = previousBodyOverflow
})
</script>

<template>
  <div class="app-shell rentbase-graphite" :class="isOwner ? 'owner-shell' : 'tenant-shell'">
    <a class="skip-link" href="#main-content">Đến nội dung chính</a>
    <button v-if="isMenuOpen" class="sidebar-backdrop" aria-label="Đóng menu" tabindex="-1" @click="closeMenu" />

    <aside id="app-navigation" ref="sidebar" class="sidebar" :class="{ 'is-open': isMenuOpen }" :role="isMenuOpen ? 'dialog' : undefined" :aria-modal="isMenuOpen ? true : undefined" aria-label="Điều hướng chính">
      <div class="sidebar-top">
        <RouterLink :to="home" class="brand" aria-label="Quản lý nhà trọ — trang chính">
          <img class="brand-mark" src="/icons/rentbase-mark.svg" alt="" width="44" height="44" />
          <span><strong>RentBase</strong><small>{{ isOwner ? 'Không gian chủ trọ' : 'Không gian người thuê' }}</small></span>
        </RouterLink>
        <button class="icon-button close-navigation" aria-label="Đóng menu" @click="closeMenu">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg>
        </button>
      </div>

      <div class="role-switch-label"><span class="role-dot" />{{ roleLabel }}</div>
      <nav class="main-navigation">
        <div v-for="section in navigation" :key="section.label" class="navigation-section" :role="section.label ? 'group' : undefined" :aria-label="section.label || undefined">
          <div v-if="section.label" class="navigation-label" aria-hidden="true">{{ section.label }}</div>
          <RouterLink v-for="item in section.items" :key="item.to" :to="item.to" class="navigation-link" :class="{ 'is-active': isActive(item.to) }" :aria-current="isActive(item.to) ? 'page' : undefined">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path :d="item.icon" /></svg>
            <span>{{ item.label }}</span>
            <span v-if="isActive(item.to)" class="active-dot" aria-hidden="true" />
          </RouterLink>
        </div>
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
      <header class="topbar workspace-topbar">
        <div class="topbar-row">
        <div class="page-heading">
          <button ref="menuButton" class="icon-button menu-toggle" aria-label="Mở menu" aria-controls="app-navigation" :aria-expanded="isMenuOpen" @click="isMenuOpen = true">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
          </button>
          <RouterLink :to="home" class="workspace-wordmark brand" aria-label="RentBase — Trang chính">
            <img class="workspace-logo" src="/icons/rentbase-mark.svg" alt="" width="42" height="42" />
            <span><strong>RentBase</strong><small>{{ isOwner ? 'Không gian chủ trọ' : 'Không gian người thuê' }}</small></span>
          </RouterLink>
          <div class="breadcrumb"><span>{{ roleLabel }}</span><span class="breadcrumb-separator" aria-hidden="true">/</span><strong>{{ title }}</strong></div>
        </div>
        <div class="topbar-actions">
          <PwaInstallButton compact />
          <NotificationBell />
          <RouterLink class="workspace-account" to="/account" aria-label="Tài khoản">{{ auth.user?.username }}</RouterLink>
          <button class="logout-button" :aria-label="isLoggingOut ? 'Đang đăng xuất…' : 'Đăng xuất'" title="Đăng xuất" :disabled="isLoggingOut" @click="logout">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9" /></svg>
            <span>{{ isLoggingOut ? 'Đang đăng xuất…' : 'Đăng xuất' }}</span>
          </button>
        </div>
        </div>
        <nav v-if="isOwner" ref="desktopNav" class="workspace-nav" aria-label="Điều hướng chính">
          <RouterLink to="/owner" class="workspace-tab" :aria-current="route.path === '/owner' ? 'page' : undefined" @click="closeMenu">Tổng quan</RouterLink>
          <RouterLink to="/owner/rooms" class="workspace-tab" :aria-current="isActive('/owner/rooms') ? 'page' : undefined" @click="closeMenu">Quản lý phòng</RouterLink>
          <div v-for="section in ownerGroups" :key="section.label" class="owner-nav-group"
            @focusout="event => { if (!event.currentTarget || !(event.currentTarget as HTMLElement).contains(event.relatedTarget as Node | null)) openGroup = '' }">
            <button type="button" class="workspace-tab" :class="{ 'is-current': section.items.some(item => isActive(item.to)) }"
              :aria-expanded="openGroup === section.label" @click="openGroup = openGroup === section.label ? '' : section.label">
              {{ section.label }} <span aria-hidden="true">⌄</span>
            </button>
            <div v-if="openGroup === section.label" class="owner-nav-popover">
              <RouterLink v-for="item in section.items" :key="item.to" class="navigation-link" :to="item.to"
                :aria-current="isActive(item.to) ? 'page' : undefined" :class="{ 'is-active': isActive(item.to) }" @click="closeMenu">{{ item.label }}</RouterLink>
            </div>
          </div>
        </nav>
        <nav v-else class="workspace-nav" aria-label="Điều hướng chính">
          <RouterLink v-for="item in tenantNavigation" :key="item.to" :to="item.to" class="workspace-tab"
            :aria-current="isActive(item.to) ? 'page' : undefined">{{ item.label }}</RouterLink>
        </nav>
      </header>

      <div v-if="logoutError" class="logout-alert" role="alert">
        <span>{{ logoutError }}</span>
        <button type="button" aria-label="Đóng thông báo lỗi" @click="logoutError = ''">×</button>
      </div>

      <main id="main-content" class="main-content" tabindex="-1"><RouterView :key="auth.user ? `${auth.user.id}:${auth.user.role}:${route.path}` : 'anonymous'" /></main>
      <footer class="app-footer"><span>RentBase</span><span>{{ isDemo ? 'Bản xem thử · Dữ liệu mẫu' : 'Dữ liệu hiển thị theo quyền tài khoản' }}</span></footer>
      <nav v-if="!isOwner && !isMenuOpen" class="tenant-bottom-navigation" aria-label="Điều hướng người thuê"><RouterLink v-for="item in tenantBottomNavigation" :key="item.to" :to="item.to" :class="{ 'is-active': bottomActive(item.to) }" :aria-current="bottomActive(item.to) ? 'page' : undefined"><svg viewBox="0 0 24 24" aria-hidden="true"><path :d="item.icon" /></svg><span>{{ item.label }}</span></RouterLink></nav>
    </div>
  </div>
</template>

<style scoped>
.app-shell {
  min-height: calc(100dvh - var(--pwa-notice-height, 0px));
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
  inset: calc(var(--pwa-notice-height, 0px) + env(safe-area-inset-top, 0px)) auto 0 env(safe-area-inset-left, 0px);
  display: flex;
  flex-direction: column;
  width: 264px;
  padding: 24px 16px calc(16px + env(safe-area-inset-bottom, 0px));
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
  flex: 0 0 auto;
  width: 44px;
  height: 44px;
}
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
.navigation-label { padding: 4px 16px 2px; color: var(--color-text-secondary); font-size: 12px; font-weight: 650; line-height: 1.5; }
.main-navigation { display: flex; flex: 1; flex-direction: column; gap: 6px; min-height: 0; overflow-y: auto; padding: 4px 0 8px; scrollbar-width: thin; }
.navigation-section { flex: 0 0 auto; }
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
.main-shell { display: flex; flex-direction: column; min-width: 0; min-height: 100dvh; margin-left: 264px; }
.tenant-bottom-navigation { display: none; }
.topbar { position: sticky; top: calc(var(--pwa-notice-height, 0px) + env(safe-area-inset-top, 0px)); z-index: 30; display: flex; align-items: center; justify-content: space-between; gap: 24px; min-height: 80px; padding: 16px 40px; border-bottom: 1px solid var(--color-border); background: var(--color-surface); }
.topbar-row { display: contents; }
.page-heading { display: flex; flex: 1; align-items: center; gap: 16px; min-width: 0; }
.breadcrumb { display: flex; align-items: center; gap: 12px; min-width: 0; color: var(--color-text-secondary); font-size: 14px; line-height: 1.5; }
.breadcrumb > span { flex: 0 0 auto; white-space: nowrap; }
.breadcrumb strong { overflow: hidden; color: var(--color-text); font-size: 14px; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
.breadcrumb-separator { color: var(--color-text-muted); }
.topbar-actions { display: flex; flex: 0 0 auto; align-items: center; gap: 16px; }
.logout-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 10px 12px; border: 1px solid transparent; border-radius: 10px; color: var(--color-text-secondary); background: transparent; font: inherit; font-size: 14px; font-weight: 500; cursor: pointer; }
.logout-button:hover { color: var(--color-text); background: var(--color-bg); border-color: var(--color-border); }
.logout-button:disabled { opacity: .65; cursor: wait; }
.logout-button svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.main-content { flex: 1; min-width: 0; width: 100%; max-width: 1440px; margin: 0 auto; padding: 40px; outline: none; scroll-margin-top: calc(var(--pwa-notice-height, 0px) + 96px); }
.app-footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 40px 24px; color: var(--color-text-muted); font-size: 12px; line-height: 1.6; }
.logout-alert { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin: 16px 40px 0; padding: 8px 8px 8px 16px; border: 1px solid #e5beb8; border-radius: 12px; color: var(--color-danger); background: var(--color-danger-soft); font-size: 14px; line-height: 1.6; }
.logout-alert button { flex: 0 0 auto; width: 44px; height: 44px; padding: 0; border: 0; border-radius: 8px; color: inherit; background: transparent; font-size: 24px; cursor: pointer; }
.icon-button { display: grid; flex: 0 0 auto; place-items: center; width: 44px; height: 44px; padding: 10px; border: 1px solid var(--color-border); border-radius: 10px; color: var(--color-text-secondary); background: var(--color-surface); cursor: pointer; }
.icon-button:hover { color: var(--color-primary); background: var(--color-primary-soft); }
.icon-button svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.menu-toggle, .close-navigation, .sidebar-backdrop { display: none; }
a:focus-visible, button:focus-visible { outline: 3px solid var(--color-focus); outline-offset: 3px; }
.rentbase-graphite .sidebar {
  --color-surface: var(--refresh-dark);
  --color-text: #f4f8fa;
  --color-text-secondary: #c5cfd6;
  --color-border: #4a5664;
  --color-primary: #8addd1;
  --color-primary-soft: #35414b;
  --color-focus: #8addd1;
}
.rentbase-graphite .role-switch-label { min-height: 36px; margin-block: 14px; padding-block: 5px; }
.rentbase-graphite .main-navigation { scrollbar-color: #778696 transparent; }
.rentbase-graphite .navigation-link.is-active {
  background: var(--color-action);
  color: var(--color-action-text);
}
.rentbase-graphite .navigation-link:focus-visible { outline-offset: -3px; }
.rentbase-graphite .sidebar-backdrop { background: rgb(38 43 55 / 52%); }
@media (min-width: 1024px) and (max-height: 820px) {
  .sidebar { padding-top: 16px; }
  .role-switch-label { margin-top: 16px; margin-bottom: 16px; }
  .sidebar-bottom { padding-top: 8px; }
}
@media (max-width: 1199px) {
  .topbar, .main-content, .app-footer { padding-right: 24px; padding-left: 24px; }
}
@media (max-width: 1023px) {
  .sidebar { width: min(320px, calc(100vw - 24px)); transform: translateX(-100%); visibility: hidden; transition: transform var(--motion-normal) var(--motion-ease), visibility var(--motion-normal); }
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
  .tenant-bottom-navigation a.is-active { color: var(--color-action-text); background: var(--color-primary-soft); font-weight: 700; }
  .tenant-bottom-navigation svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
  .topbar { gap: 8px; padding: 12px 16px; }
  .page-heading { gap: 12px; }
  .breadcrumb { gap: 0; }
  .breadcrumb > span { display: none; }
  .breadcrumb strong { max-width: 135px; }
  .topbar-actions { gap: 0; }

  .logout-button { width: 44px; padding: 8px; }
  .logout-button > span { display: none; }
  .logout-button svg { width: 18px; height: 18px; }
  .main-content { padding: 24px 16px; }
  .app-footer { flex-direction: column; align-items: flex-start; gap: 4px; padding: 8px 16px 24px; }
  .logout-alert { margin-right: 16px; margin-left: 16px; }
}
@media (max-width: 359px) {
  .page-heading { gap: 8px; }
  .breadcrumb strong { max-width: 104px; font-size: 12px; }
}
.rentbase-graphite .main-shell { margin-left: 0; }
.rentbase-graphite .main-content,
.rentbase-graphite .app-footer { width: min(var(--owner-width), calc(100% - 48px)); margin-inline: auto; padding-inline: 0; }
.rentbase-graphite .app-footer { border-top: 1px solid var(--color-border); }
.workspace-topbar {
  display: block;
  min-height: 0;
  padding: 0;
  color: white;
  background: var(--owner-header);
  border-color: #394353;
}
.workspace-topbar .topbar-row,
.workspace-nav { width: min(var(--owner-width), calc(100% - 48px)); margin-inline: auto; }
.workspace-topbar .topbar-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 74px; }
.workspace-topbar .workspace-wordmark { color: white; }
.workspace-topbar .workspace-wordmark small { color: #c5cfd6; }
.workspace-logo { flex: none; width: 42px; height: 42px; }
.workspace-topbar .breadcrumb, .workspace-topbar .breadcrumb-separator { color: #c5cfd6; }
.workspace-topbar .breadcrumb strong { color: white; }
.workspace-topbar .topbar-actions { gap: 10px; }
.workspace-topbar .icon-button,
.workspace-topbar .logout-button,
.workspace-topbar :deep(.install-trigger),
.workspace-topbar :deep(.notification-bell) { color: #e6ecf3; background: transparent; border-color: #606b7b; }
.workspace-topbar .icon-button:hover,
.workspace-topbar .logout-button:hover,
.workspace-topbar :deep(.install-trigger:hover),
.workspace-topbar :deep(.notification-bell:hover) { color: white; background: #343e4d; }
.workspace-topbar :deep(.unread-badge) { background: var(--color-action); color: var(--color-action-text); }
.workspace-topbar a:focus-visible, .workspace-topbar button:focus-visible { outline-color: var(--color-action); }
.workspace-topbar :deep(.install-trigger:focus-visible),
.workspace-topbar :deep(.notification-bell:focus-visible) { outline-color: var(--color-action); }
.workspace-account { color: #d2dbe5; font-size: 13px; text-decoration: none; }
.workspace-account:hover { text-decoration: underline; }
.workspace-nav { display: none; align-items: stretch; gap: 16px; }
.owner-nav-group { position: relative; }
.workspace-tab { display: inline-flex; align-items: center; gap: 6px; min-height: 44px; padding: 8px 2px; border: 0; border-bottom: 3px solid transparent; background: transparent; color: #c8d0da; text-decoration: none; font: inherit; font-size: 14px; font-weight: 500; }
.workspace-tab:hover { color: white; }
.workspace-tab[aria-current='page'], .workspace-tab.is-current { color: var(--color-action); border-bottom-color: currentColor; font-weight: 650; }
.workspace-tab[aria-expanded='true'] { color: var(--color-action); }
.owner-nav-popover { position: absolute; inset: 100% auto auto 0; z-index: 1; width: max-content; min-width: 208px; max-width: 260px; padding: 6px; border: 1px solid var(--color-border); border-radius: 0 0 10px 10px; background: var(--color-surface); box-shadow: var(--shadow-popover); }
@media (prefers-reduced-motion: no-preference) {
  .owner-nav-popover { animation: menu-enter var(--motion-normal) var(--motion-ease) both; }
  .sidebar-backdrop { animation: backdrop-enter var(--motion-normal) ease-out both; }
  @keyframes menu-enter { from { opacity: 0; transform: translateY(-4px); } }
  @keyframes backdrop-enter { from { opacity: 0; } }
}
.owner-nav-popover .navigation-link { padding: 9px 12px; }
.owner-nav-popover a:focus-visible { outline-color: var(--color-focus); outline-offset: -3px; }
@media (min-width: 1024px) {
  .rentbase-graphite .sidebar, .workspace-topbar .breadcrumb { display: none; }
  .workspace-nav { display: flex; }
}
@media (max-width: 1023px) {
  .workspace-topbar .workspace-wordmark { display: none; }
}
@media (max-width: 600px) {
  .rentbase-graphite .main-content,
  .rentbase-graphite .app-footer,
  .workspace-topbar .topbar-row { width: calc(100% - 28px); }
  .workspace-topbar .topbar-row { gap: 5px; min-height: 66px; }
  .workspace-topbar .topbar-actions { gap: 4px; }
  .workspace-account { display: none; }
  .workspace-topbar .breadcrumb strong { max-width: none; }
}
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { transition: none !important; }
}
</style>

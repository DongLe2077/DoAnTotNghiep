import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getRoleHome } from './navigation'
import type { Role } from '@/types/auth'

declare module 'vue-router' {
  interface RouteMeta {
    title: string
    description?: string
    phase?: string
    features?: string[]
    requiresAuth?: boolean
    role?: Role
  }
}

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/design', name: 'design', component: () => import('@/views/DesignView.vue'), meta: { title: 'Thiết kế chung' } },
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { title: 'Đăng nhập' } },
    { path: '/connection-error', name: 'connection-error', component: () => import('@/views/ConnectionView.vue'), meta: { title: 'Kiểm tra kết nối' } },
    {
      path: '/', component: () => import('@/layouts/AppLayout.vue'), meta: { title: 'Nhà Trọ', requiresAuth: true },
      children: [
        { path: '', name: 'home', redirect: () => { const user = useAuthStore().user; return user ? getRoleHome(user.role) : '/login' } },
        { path: 'account', name: 'account', component: () => import('@/views/AccountView.vue'), meta: { title: 'Tài khoản' } },
        { path: 'notifications', name: 'notifications', component: () => import('@/views/NotificationsView.vue'), meta: { title: 'Thông báo' } },
        { path: 'forbidden', name: 'forbidden', component: () => import('@/views/ForbiddenView.vue'), meta: { title: 'Không có quyền truy cập' } },
        { path: 'owner', name: 'owner-home', component: () => import('@/views/owner/DashboardView.vue'), meta: { title: 'Tổng quan', role: 'owner' } },
        { path: 'owner/property', component: () => import('@/views/owner/PropertyView.vue'), meta: { title: 'Thông tin nhà trọ', role: 'owner' } },
        { path: 'owner/meters/bulk', component: () => import('@/views/owner/BulkMetersView.vue'), meta: { title: 'Nhập chỉ số nhiều phòng', role: 'owner' } },
        { path: 'owner/announcements', component: () => import('@/views/owner/AnnouncementsView.vue'), meta: { title: 'Thông báo chung', role: 'owner' } },
        { path: 'owner/announcements/new', component: () => import('@/views/owner/NewAnnouncementView.vue'), meta: { title: 'Soạn thông báo', role: 'owner' } },
        { path: 'owner/announcements/:id(\\d+)', component: () => import('@/views/owner/AnnouncementDetailView.vue'), meta: { title: 'Thông báo đã gửi', role: 'owner' } },
        { path: 'tenant/announcements/:id(\\d+)', component: () => import('@/views/tenant/MyAnnouncementView.vue'), meta: { title: 'Thông báo từ chủ trọ', role: 'tenant' } },
        { path: 'owner/rooms', component: () => import('@/views/owner/RoomsView.vue'), meta: { title: 'Quản lý phòng', role: 'owner' } },
        { path: 'owner/services', component: () => import('@/views/owner/ServicesView.vue'), meta: { title: 'Dịch vụ', role: 'owner' } },
        { path: 'owner/tenants', component: () => import('@/views/owner/TenantsView.vue'), meta: { title: 'Người thuê', role: 'owner' } },
        { path: 'owner/tenants/:id(\\d+)', component: () => import('@/views/owner/TenantDetailView.vue'), meta: { title: 'Hồ sơ người thuê', role: 'owner' } },
        { path: 'owner/groups', component: () => import('@/views/owner/GroupsView.vue'), meta: { title: 'Nhóm thuê', role: 'owner' } },
        { path: 'owner/stays', component: () => import('@/views/owner/StaysView.vue'), meta: { title: 'Quá trình thuê', role: 'owner' } },
        { path: 'owner/stays/new', component: () => import('@/views/owner/CheckInView.vue'), meta: { title: 'Nhận phòng', role: 'owner' } },
        { path: 'owner/stays/:id(\\d+)', component: () => import('@/views/owner/StayDetailView.vue'), meta: { title: 'Chi tiết đợt thuê', role: 'owner' } },
        { path: 'owner/meters', component: () => import('@/views/owner/MetersView.vue'), meta: { title: 'Điện nước', role: 'owner' } },
        { path: 'owner/invoices', component: () => import('@/views/owner/InvoicesView.vue'), meta: { title: 'Hóa đơn', role: 'owner' } },
        { path: 'owner/invoices/new', component: () => import('@/views/owner/InvoiceIssueView.vue'), meta: { title: 'Lập hóa đơn', role: 'owner' } },
        { path: 'owner/invoices/:id(\\d+)', component: () => import('@/views/owner/InvoiceDetailView.vue'), meta: { title: 'Chi tiết hóa đơn', role: 'owner' } },
        { path: 'owner/payments', component: () => import('@/views/owner/PaymentsView.vue'), meta: { title: 'Biên nhận thu tiền', role: 'owner' } },
        { path: 'owner/receivables', component: () => import('@/views/owner/ReceivablesView.vue'), meta: { title: 'Công nợ', role: 'owner' } },
        { path: 'owner/cash', component: () => import('@/views/owner/CashView.vue'), meta: { title: 'Thu chi', role: 'owner' } },
        { path: 'owner/maintenance', component: () => import('@/views/owner/MaintenanceView.vue'), meta: { title: 'Sửa chữa', role: 'owner' } },
        { path: 'owner/maintenance/:id(\\d+)', component: () => import('@/views/owner/MaintenanceDetailView.vue'), meta: { title: 'Chi tiết sửa chữa', role: 'owner' } },
        { path: 'tenant', name: 'tenant-home', component: () => import('@/views/tenant/MyRoomView.vue'), meta: { title: 'Phòng của tôi', role: 'tenant' } },
        { path: 'tenant/profile', component: () => import('@/views/tenant/MyProfileView.vue'), meta: { title: 'Thông tin cá nhân', role: 'tenant' } },
        { path: 'tenant/stays', component: () => import('@/views/tenant/MyStaysView.vue'), meta: { title: 'Lịch sử cư trú', role: 'tenant' } },
        { path: 'tenant/invoices', component: () => import('@/views/tenant/MyInvoicesView.vue'), meta: { title: 'Hóa đơn của tôi', role: 'tenant' } },
        { path: 'tenant/invoices/:id(\\d+)', component: () => import('@/views/tenant/MyInvoiceDetailView.vue'), meta: { title: 'Chi tiết hóa đơn', role: 'tenant' } },
        { path: 'tenant/maintenance', component: () => import('@/views/tenant/MyMaintenanceView.vue'), meta: { title: 'Báo hỏng', role: 'tenant' } },
        { path: 'tenant/maintenance/new', component: () => import('@/views/tenant/NewMaintenanceView.vue'), meta: { title: 'Gửi báo hỏng', role: 'tenant' } },
        { path: 'tenant/maintenance/:id(\\d+)', component: () => import('@/views/tenant/MyMaintenanceDetailView.vue'), meta: { title: 'Chi tiết báo hỏng', role: 'tenant' } },
        { path: ':pathMatch(.*)*', name: 'not-found', component: () => import('@/views/NotFoundView.vue'), meta: { title: 'Không tìm thấy trang' } },
      ],
    },
  ],
  scrollBehavior: (_to, _from, savedPosition) => savedPosition ?? { top: 0 },
})

router.beforeEach(async to => {
  if (to.name === 'connection-error' || to.name === 'design') return true
  const auth = useAuthStore()
  try { await auth.initialize() }
  catch { return { name: 'connection-error', query: { redirect: to.fullPath } } }
  if (to.meta.requiresAuth && !auth.user) return { name: 'login', query: { redirect: to.fullPath } }
  if (auth.user && to.name === 'login') return getRoleHome(auth.user.role)
  if (auth.user && to.meta.role && to.meta.role !== auth.user.role) return { name: 'forbidden' }
  return true
})

router.afterEach(to => { document.title = `${to.meta.title} · Nhà Trọ` })

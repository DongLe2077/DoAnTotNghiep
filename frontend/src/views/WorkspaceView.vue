<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { getRoleHome } from '@/router/navigation'

const auth = useAuthStore()
const route = useRoute()
const isOwner = computed(() => auth.user?.role === 'owner')
const roleLabel = computed(() => isOwner.value ? 'Chủ trọ' : 'Người thuê')
const home = computed(() => getRoleHome(auth.user?.role ?? 'tenant'))
const isHome = computed(() => route.path === home.value)
const title = computed(() => typeof route.meta.title === 'string' ? route.meta.title : 'Không gian của bạn')
const description = computed(() => typeof route.meta.description === 'string' ? route.meta.description : 'Thông tin và tiện ích được tập hợp trong một nơi.')
const features = computed(() => Array.isArray(route.meta.features) ? route.meta.features.filter((feature): feature is string => typeof feature === 'string') : [])

const quickLinks = computed(() => isOwner.value ? [
  { title: 'Phòng', description: 'Thông tin phòng và tình trạng sử dụng.', to: '/owner/rooms', icon: 'M4 21V4h16v17M2 21h20M8 8h2m4 0h2M8 12h2m4 0h2M10 21v-5h4v5' },
  { title: 'Người thuê', description: 'Hồ sơ và thông tin cư trú của người thuê.', to: '/owner/tenants', icon: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8m7-7.87a4 4 0 0 1 0 7.75' },
  { title: 'Hóa đơn', description: 'Tiền phòng, dịch vụ và các lần thanh toán.', to: '/owner/invoices', icon: 'M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6m-6 4h6m-6 4h3' },
] : [
  { title: 'Thông tin cá nhân', description: 'Hồ sơ của bạn và thông tin cư trú.', to: '/tenant/profile', icon: 'M19 21v-2a7 7 0 0 0-14 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8' },
  { title: 'Hóa đơn của tôi', description: 'Các hóa đơn và khoản thanh toán của bạn.', to: '/tenant/invoices', icon: 'M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6m-6 4h6m-6 4h3' },
  { title: 'Báo hỏng', description: 'Gửi sự cố và theo dõi tiến độ sửa chữa.', to: '/tenant/maintenance', icon: 'm14 6 4-4a6 6 0 0 1-7.8 7.8L3 17a2.83 2.83 0 0 0 4 4l7.2-7.2A6 6 0 0 0 22 6l-4 4-4-4Z' },
])
</script>

<template>
  <div class="workspace">
    <template v-if="isHome">
      <div class="page-intro">
        <p class="eyebrow">Không gian {{ isOwner ? 'chủ trọ' : 'người thuê' }}</p>
        <h1>{{ isOwner ? 'Tổng quan' : 'Phòng của tôi' }}</h1>
        <p>{{ isOwner ? 'Quản lý nhà trọ gọn gàng, theo dõi mọi việc dễ dàng.' : 'Thông tin nơi ở và các tiện ích dành cho bạn.' }}</p>
      </div>

      <div class="overview-grid">
        <section class="welcome-card" aria-labelledby="welcome-heading">
          <span class="welcome-symbol" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-8h6v8" /></svg>
          </span>
          <h2 id="welcome-heading">Xin chào, <span>{{ auth.user?.username }}</span></h2>
          <p>Bạn đã đăng nhập vào không gian dành cho {{ isOwner ? 'chủ trọ' : 'người thuê' }}. Bạn có thể xem thông tin tài khoản và đổi mật khẩu ngay tại đây.</p>
          <RouterLink to="/account" class="primary-link">Quản lý tài khoản <span aria-hidden="true">→</span></RouterLink>
        </section>

        <section class="identity-card" aria-labelledby="identity-heading">
          <div class="card-heading">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 3v6c0 4-5 8-8 10-3-2-8-6-8-10V6l8-3Z" /><path d="m8 12 3 3 5-6" /></svg>
            <h2 id="identity-heading">Phiên đăng nhập</h2>
          </div>
          <dl class="identity-list">
            <div><dt>Tài khoản</dt><dd>{{ auth.user?.username }}</dd></div>
            <div><dt>Vai trò</dt><dd>{{ roleLabel }}</dd></div>
            <div><dt>Trạng thái</dt><dd class="verified"><span aria-hidden="true" />Đã xác thực</dd></div>
          </dl>
          <p class="identity-note">Thông tin được hiển thị theo quyền tài khoản của bạn.</p>
        </section>
      </div>

      <section class="module-section" aria-labelledby="modules-heading">
        <div class="section-heading">
          <h2 id="modules-heading">{{ isOwner ? 'Các khu vực quản lý' : 'Tiện ích dành cho bạn' }}</h2>
          <span>Đang hoàn thiện</span>
        </div>
        <div class="quick-grid">
          <RouterLink v-for="item in quickLinks" :key="item.to" :to="item.to" class="quick-card">
            <div class="quick-card-top">
              <span class="quick-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path :d="item.icon" /></svg></span>
              <span class="pending-label">{{ isOwner && ['/owner/rooms', '/owner/tenants', '/owner/stays', '/owner/services'].includes(item.to) ? 'Đang sử dụng' : 'Sắp có' }}</span>
            </div>
            <h3>{{ item.title }} <span aria-hidden="true">→</span></h3>
            <p>{{ item.description }}</p>
          </RouterLink>
        </div>
      </section>

      <div class="preview-note">
        <p>Các màn hình nghiệp vụ đang được hoàn thiện. Bạn có thể xem trước phong cách giao diện chung.</p>
        <RouterLink to="/design">Xem mẫu giao diện <span aria-hidden="true">→</span></RouterLink>
      </div>
    </template>

    <template v-else>
      <div class="page-intro">
        <p class="eyebrow">Không gian {{ isOwner ? 'chủ trọ' : 'người thuê' }}</p>
        <h1>{{ title }}</h1>
        <p>{{ description }}</p>
      </div>
      <section class="pending-card" aria-labelledby="pending-heading">
        <div class="pending-illustration" aria-hidden="true">
          <svg viewBox="0 0 48 48"><rect x="9" y="6" width="30" height="36" rx="5" /><path d="M17 16h14M17 23h10M17 30h7" /><circle cx="36" cy="35" r="9" /><path d="m32 35 3 3 5-6" /></svg>
        </div>
        <span class="phase-badge">Sắp có</span>
        <h2 id="pending-heading">Tính năng đang được hoàn thiện</h2>
        <p class="pending-description">Khu vực {{ title.toLocaleLowerCase('vi-VN') }} sẽ sẵn sàng để sử dụng tại đây.</p>
        <div v-if="features.length" class="planned-features">
          <h3>Các tiện ích sắp có</h3>
          <ul>
            <li v-for="feature in features" :key="feature">
              <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m5 10 3 3 7-7" /></svg><span>{{ feature }}</span>
            </li>
          </ul>
        </div>
        <RouterLink :to="home" class="back-home"><span aria-hidden="true">←</span> Về {{ isOwner ? 'tổng quan' : 'trang chính' }}</RouterLink>
      </section>
      <p class="account-note">Bạn vẫn có thể xem thông tin tài khoản và đổi mật khẩu trong mục <RouterLink to="/account">Tài khoản</RouterLink>.</p>
    </template>
  </div>
</template>

<style scoped>
.workspace { width: 100%; }
.page-intro { margin-bottom: 32px; }
.eyebrow { margin: 0 0 8px; color: var(--color-text-secondary); font-size: 14px; font-weight: 500; line-height: 1.6; }
.page-intro h1 { margin: 0; color: var(--color-text); font-size: clamp(26px, 2.5vw, 32px); font-weight: 700; line-height: 1.3; letter-spacing: -.7px; }
.page-intro > p:last-child { margin: 12px 0 0; color: var(--color-text-secondary); font-size: 16px; line-height: 1.7; }
.overview-grid { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(280px, 1fr); gap: 24px; }
.welcome-card { padding: 32px; border: 1px solid var(--color-border); border-radius: 16px; color: var(--color-text); background: var(--color-primary-soft); }
.welcome-symbol { display: grid; place-items: center; width: 48px; height: 48px; margin-bottom: 24px; border: 1px solid var(--color-border); border-radius: 12px; color: var(--color-primary); background: var(--color-surface); }
.welcome-symbol svg { width: 24px; height: 24px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.welcome-card h2 { margin: 0 0 12px; font-size: 24px; font-weight: 600; line-height: 1.5; letter-spacing: -.4px; overflow-wrap: anywhere; }
.welcome-card h2 > span { color: var(--color-primary); }
.welcome-card > p { max-width: 560px; margin: 0; color: var(--color-text-secondary); font-size: 14px; line-height: 1.8; }
.primary-link { display: inline-flex; align-items: center; justify-content: center; gap: 24px; min-height: 44px; margin-top: 24px; padding: 12px 16px; border: 1px solid var(--color-primary); border-radius: 10px; color: #fff; background: var(--color-primary); font-size: 14px; font-weight: 600; line-height: 1.5; text-decoration: none; transition: background .16s; }
.primary-link:hover { border-color: var(--color-primary-hover); background: var(--color-primary-hover); }
.primary-link > span { font-size: 20px; line-height: 1; }
.identity-card { min-width: 0; padding: 32px 24px; border: 1px solid var(--color-border); border-radius: 16px; background: var(--color-surface); }
.card-heading { display: flex; align-items: center; gap: 12px; }
.card-heading h2 { margin: 0; color: var(--color-text); font-size: 16px; font-weight: 600; line-height: 1.6; }
.card-heading > svg { flex: 0 0 auto; width: 22px; height: 22px; fill: none; stroke: var(--color-primary); stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.identity-list { display: grid; gap: 20px; margin: 32px 0 24px; }
.identity-list > div { display: flex; justify-content: space-between; gap: 16px; font-size: 14px; line-height: 1.6; }
.identity-list dt { flex: 0 0 auto; color: var(--color-text-secondary); }
.identity-list dd { min-width: 0; margin: 0; color: var(--color-text); font-weight: 600; text-align: right; overflow-wrap: anywhere; }
.identity-list dd.verified { display: inline-flex; align-items: center; gap: 8px; color: var(--color-primary); }
.verified > span { flex: 0 0 auto; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.identity-note { margin: 0; padding-top: 16px; border-top: 1px solid var(--color-border); color: var(--color-text-secondary); font-size: 12px; line-height: 1.8; }
.module-section { margin-top: 40px; }
.section-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
.section-heading h2 { margin: 0; color: var(--color-text); font-size: 18px; font-weight: 600; line-height: 1.5; }
.section-heading > span { color: var(--color-text-secondary); font-size: 12px; line-height: 1.6; }
.quick-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.quick-card { display: block; min-width: 0; padding: 24px; border: 1px solid var(--color-border); border-radius: 12px; color: inherit; background: var(--color-surface); text-decoration: none; transition: border-color .16s, background .16s; }
.quick-card:hover { border-color: var(--color-primary); background: #fbfcfa; }
.quick-card-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.quick-icon { display: grid; flex: 0 0 auto; place-items: center; width: 40px; height: 40px; border-radius: 10px; color: var(--color-primary); background: var(--color-primary-soft); }
.quick-icon svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.pending-label, .phase-badge { display: inline-flex; align-items: center; min-height: 28px; padding: 4px 8px; border: 1px solid var(--color-border); border-radius: 8px; color: var(--color-text-secondary); background: var(--color-bg); font-size: 12px; font-weight: 500; line-height: 1.5; }
.quick-card h3 { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin: 24px 0 8px; color: var(--color-text); font-size: 16px; font-weight: 600; line-height: 1.6; }
.quick-card h3 > span { color: var(--color-primary); }
.quick-card > p { margin: 0; color: var(--color-text-secondary); font-size: 14px; line-height: 1.8; }
.preview-note { display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--color-border); }
.preview-note > p { max-width: 620px; margin: 0; color: var(--color-text-secondary); font-size: 14px; line-height: 1.8; }
.preview-note > a { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 12px; min-height: 44px; color: var(--color-primary); font-size: 14px; font-weight: 600; text-decoration: none; }
.preview-note > a:hover { text-decoration: underline; text-underline-offset: 4px; }
.pending-card { display: flex; flex-direction: column; align-items: center; padding: 48px 32px; border: 1px solid var(--color-border); border-radius: 16px; background: var(--color-surface); text-align: center; }
.pending-illustration { display: grid; place-items: center; width: 80px; height: 80px; margin-bottom: 24px; border-radius: 16px; color: var(--color-primary); background: var(--color-primary-soft); }
.pending-illustration svg { width: 48px; height: 48px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.pending-illustration circle { fill: var(--color-primary-soft); }
.pending-card h2 { margin: 24px 0 12px; color: var(--color-text); font-size: 24px; font-weight: 600; line-height: 1.5; letter-spacing: -.4px; }
.pending-description { max-width: 560px; margin: 0; color: var(--color-text-secondary); font-size: 14px; line-height: 1.8; }
.planned-features { width: 100%; max-width: 480px; margin-top: 32px; padding: 24px; border: 1px solid var(--color-border); border-radius: 12px; background: var(--color-bg); text-align: left; }
.planned-features h3 { margin: 0 0 16px; color: var(--color-text); font-size: 14px; font-weight: 600; line-height: 1.6; }
.planned-features ul { display: grid; gap: 16px; margin: 0; padding: 0; list-style: none; }
.planned-features li { display: flex; align-items: flex-start; gap: 12px; color: var(--color-text-secondary); font-size: 14px; line-height: 1.7; }
.planned-features svg { flex: 0 0 auto; width: 20px; height: 20px; margin-top: 2px; fill: none; stroke: var(--color-primary); stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.back-home { display: inline-flex; align-items: center; justify-content: center; gap: 12px; min-height: 44px; margin-top: 32px; padding: 12px 16px; border: 1px solid var(--color-border); border-radius: 10px; color: var(--color-primary); background: var(--color-surface); font-size: 14px; font-weight: 600; line-height: 1.5; text-decoration: none; }
.back-home:hover { background: var(--color-primary-soft); border-color: var(--color-primary); }
.account-note { margin: 24px 0 0; color: var(--color-text-secondary); font-size: 14px; line-height: 1.8; }
.account-note > a { color: var(--color-primary); font-weight: 600; text-underline-offset: 4px; }
a:focus-visible { outline: 3px solid var(--color-focus); outline-offset: 3px; }
@media (max-width: 1199px) {
  .overview-grid { grid-template-columns: minmax(0, 1.2fr) minmax(260px, 1fr); gap: 16px; }
  .welcome-card, .identity-card, .quick-card { padding: 24px; }
}
@media (max-width: 760px) {
  .overview-grid { grid-template-columns: 1fr; }
  .quick-grid { grid-template-columns: 1fr; }
  .quick-card { padding: 24px; }
  .quick-card h3 { margin-top: 16px; }
  .preview-note { flex-direction: column; align-items: flex-start; gap: 8px; }
  .primary-link, .back-home, .preview-note > a { min-height: 48px; }
}
@media (max-width: 480px) {
  .page-intro { margin-bottom: 24px; }
  .page-intro > p:last-child { font-size: 14px; }
  .welcome-card, .identity-card { padding: 24px; }
  .welcome-card h2 { font-size: 22px; }
  .identity-list { margin-top: 24px; }
  .module-section { margin-top: 32px; }
  .section-heading { align-items: flex-start; gap: 8px; }
  .section-heading h2 { font-size: 16px; }
  .section-heading > span { max-width: 96px; text-align: right; }
  .pending-card { padding: 32px 24px; }
  .pending-card h2 { font-size: 22px; }
  .planned-features { padding: 20px; }
}
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { transition: none !important; }
}
</style>

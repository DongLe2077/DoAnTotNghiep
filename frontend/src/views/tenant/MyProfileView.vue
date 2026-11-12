<script setup lang="ts">
import UiButton from '@/components/ui/UiButton.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import { useTenantResource } from '@/composables/useTenantApi'
import type { MyProfile } from '@/types/tenant'
const { data: profile, loading, error, status, load } = useTenantResource<MyProfile>('/profile')
void load()
</script>
<template>
  <section class="management-page tenant-page tenant-workspace">
    <header class="management-header">
      <div><h1>Thông tin cá nhân</h1><p>Liên hệ chủ trọ nếu bạn cần cập nhật hồ sơ.</p></div>
      <UiButton variant="secondary" :loading="loading" @click="load">Làm mới hồ sơ</UiButton>
    </header>
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="error" :kind="status === 404 ? 'not-found' : 'error'"
      :title="status === 404 ? 'Chưa có hồ sơ liên kết' : 'Chưa tải được hồ sơ'"
      :description="status === 404 ? 'Liên hệ chủ trọ để kiểm tra hồ sơ gắn với tài khoản của bạn.' : error">
      <UiButton @click="load">Thử lại</UiButton>
    </UiPageState>
    <div v-else-if="profile" class="record-card tenant-profile-card">
      <header><div><span class="tenant-kicker">Hồ sơ người thuê</span><h2>{{ profile.full_name }}</h2></div></header>
      <dl class="tenant-profile-facts">
        <div><dt>Số điện thoại</dt><dd>{{ profile.phone_number || 'Chưa được bổ sung' }}</dd></div>
        <div><dt>Số giấy tờ</dt><dd>{{ profile.identity_number || 'Chưa được bổ sung' }}</dd></div>
        <div><dt>Tên đăng nhập</dt><dd>{{ profile.account.username }}</dd></div>
      </dl>
    </div>
    <nav class="tenant-profile-links" aria-label="Các mục cá nhân">
      <RouterLink class="record-card" to="/account"><strong>Tài khoản / Đổi mật khẩu</strong><span aria-hidden="true">→</span></RouterLink>
      <RouterLink class="record-card" to="/tenant/stays"><strong>Lịch sử cư trú</strong><span aria-hidden="true">→</span></RouterLink>
      <RouterLink class="record-card" to="/notifications"><strong>Thông báo của bạn</strong><span aria-hidden="true">→</span></RouterLink>
    </nav>
  </section>
</template>

<style scoped>
.tenant-profile-card { padding: 0; }
.tenant-profile-card > header { padding: 20px 24px; margin: 0; background: var(--color-primary-soft); border-radius: var(--radius-card) var(--radius-card) 0 0; }
.tenant-profile-card h2 { margin: 4px 0 0; font-size: 24px; }
.tenant-profile-facts, .tenant-profile-links { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.tenant-profile-facts { margin: 0; padding: 20px 24px; }
.tenant-profile-facts dt { font-size: 13px; color: var(--color-text-secondary); }
.tenant-profile-facts dd { margin: 4px 0 0; font-weight: 600; }
.tenant-profile-links { margin-top: 16px; }
.tenant-profile-links a { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px; color: var(--color-primary); text-decoration: none; font-size: 14px; }
.tenant-profile-links a:hover { background: var(--color-primary-soft); border-color: var(--color-focus); }
@media (max-width: 700px) {
  .tenant-profile-card > header, .tenant-profile-facts { padding-inline: 16px; }
  .tenant-profile-facts, .tenant-profile-links { grid-template-columns: 1fr; gap: 8px; }
  .tenant-profile-facts > div { padding-block: 6px; }
  .tenant-profile-facts > div + div { border-top: 1px solid var(--color-border); }
}
</style>

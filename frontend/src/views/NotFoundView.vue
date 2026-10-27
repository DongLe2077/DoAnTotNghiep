<script setup lang="ts">
import UiPageState from '@/components/ui/UiPageState.vue'
import { useAuthStore } from '@/stores/auth'
import { getRoleHome } from '@/router/navigation'

const auth = useAuthStore()
</script>

<template>
  <div class="not-found-page">
    <UiPageState kind="not-found" heading-level="h1" description="Đường dẫn có thể đã thay đổi hoặc không còn được sử dụng. Hãy quay về để tiếp tục.">
      <template #actions>
        <RouterLink class="home-link" :to="auth.user ? getRoleHome(auth.user.role) : '/login'">{{ auth.user ? 'Về không gian của bạn' : 'Đến trang đăng nhập' }}</RouterLink>
      </template>
    </UiPageState>
  </div>
</template>

<style scoped>
.not-found-page { width: 100%; max-width: 760px; margin: 24px auto; border: 1px solid var(--color-border); border-radius: var(--radius-card); background: var(--color-surface); }
.home-link { display: inline-flex; justify-content: center; align-items: center; min-height: 48px; padding: 12px 20px; border: 1px solid var(--color-primary); border-radius: var(--radius-control); background: var(--color-primary); color: #fff; font-size: 14px; font-weight: 600; line-height: 1.5; text-decoration: none; }
.home-link:hover { border-color: var(--color-primary-hover); background: var(--color-primary-hover); }
@media (max-width: 560px) { .not-found-page { margin: 8px auto; } }
</style>

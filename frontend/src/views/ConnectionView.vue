<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import { useAuthStore } from '@/stores/auth'
import { getRoleHome, safeRedirect } from '@/router/navigation'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const checking = ref(false)

async function retry() {
  if (checking.value) return
  checking.value = true
  try {
    const user = await auth.initialize(true)
    await router.replace(user ? safeRedirect(route.query.redirect, user.role) : {
      name: 'login', query: { redirect: typeof route.query.redirect === 'string' ? route.query.redirect : undefined },
    })
  } catch { /* Keep the page open while the store supplies the current connection message. */ }
  finally { checking.value = false }
}
</script>

<template>
  <main class="connection-page">
    <div class="connection-card">
      <p class="connection-brand">NHÀ TRỌ</p>
      <UiPageState kind="error" heading-level="h1" title="Chưa kết nối được với máy chủ" :description="auth.connectionError || 'Máy chủ đang tạm thời không phản hồi. Bạn có thể thử kết nối lại.'">
        <template #actions>
          <UiButton :loading="checking" @click="retry">{{ checking ? 'Đang kết nối…' : 'Thử kết nối lại' }}</UiButton>
          <RouterLink v-if="auth.user" class="connection-home" :to="getRoleHome(auth.user.role)">Về không gian của bạn</RouterLink>
        </template>
      </UiPageState>
      <p class="connection-hint">Kiểm tra kết nối mạng và thử lại. Nếu sự cố tiếp tục, hãy liên hệ người quản lý.</p>
    </div>
  </main>
</template>

<style scoped>
.connection-page { display: grid; place-items: center; min-height: 100dvh; padding: 24px; background: var(--color-bg); }
.connection-card { width: 100%; max-width: 620px; border: 1px solid var(--color-border); border-radius: var(--radius-card); background: var(--color-surface); }
.connection-brand { margin: 32px 24px 0; color: var(--color-primary); font-size: 13px; font-weight: 600; letter-spacing: .1em; text-align: center; }
.connection-home { display: inline-flex; justify-content: center; align-items: center; min-height: 48px; padding: 12px 16px; border-radius: var(--radius-control); color: var(--color-primary); font-size: 14px; font-weight: 600; text-decoration: none; }
.connection-home:hover { background: var(--color-primary-soft); }
.connection-hint { margin: 0; padding: 24px; border-top: 1px solid var(--color-border); color: var(--color-text-secondary); font-size: 13px; line-height: 1.8; text-align: center; }
@media (max-width: 560px) { .connection-page { padding: 16px; } }
</style>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import UiPageState from '@/components/ui/UiPageState.vue'
import { useAuthStore } from '@/stores/auth'
import { getRoleHome } from '@/router/navigation'

const auth = useAuthStore()
const router = useRouter(), busy = ref(false), error = ref('')
const description = computed(() => auth.user
  ? `Bạn đang đăng nhập bằng tài khoản ${auth.user.username} (${auth.user.role === 'owner' ? 'Chủ trọ' : 'Người thuê'}). Trang này không thuộc quyền truy cập của tài khoản hiện tại. Bạn có thể về không gian của mình hoặc đăng xuất để đổi tài khoản.`
  : 'Vui lòng đăng nhập bằng tài khoản được cấp quyền xem nội dung này.')
async function switchAccount() {
  if (busy.value) return
  busy.value = true; error.value = ''
  try { await auth.logout(); await router.replace({ name: 'login', query: { reason: 'logged-out' } }) }
  catch { error.value = 'Chưa thể đăng xuất. Vui lòng kiểm tra kết nối rồi thử lại.' }
  finally { busy.value = false }
}
</script>

<template>
  <div class="forbidden-page">
    <UiPageState kind="forbidden" heading-level="h1" :description="description">
      <template #actions>
        <RouterLink class="state-link state-link--primary" :to="auth.user ? getRoleHome(auth.user.role) : '/login'">{{ auth.user ? 'Về không gian của bạn' : 'Đến trang đăng nhập' }}</RouterLink>
        <button v-if="auth.user" class="state-link" type="button" :disabled="busy" :aria-busy="busy" @click="switchAccount">{{ busy ? 'Đang đăng xuất…' : 'Đăng xuất để đổi tài khoản' }}</button>
        <p v-if="auth.user" class="session-hint">Các tab cùng trình duyệt dùng chung phiên đăng nhập.</p>
        <p v-if="error" class="switch-error" role="alert">{{ error }}</p>
      </template>
    </UiPageState>
  </div>
</template>

<style scoped>
.forbidden-page { width: 100%; max-width: 760px; margin: 24px auto; border: 1px solid var(--color-border); border-radius: var(--radius-card); background: var(--color-surface); }
.state-link { display: inline-flex; justify-content: center; align-items: center; min-height: 48px; padding: 12px 20px; border: 1px solid var(--color-input-border); border-radius: var(--radius-control); color: var(--color-primary); background: var(--color-surface); font-size: 14px; font-weight: 600; line-height: 1.5; text-decoration: none; }
.state-link:hover { background: var(--color-primary-soft); }
.state-link--primary { border-color: var(--color-primary); background: var(--color-primary); color: #fff; }
.state-link--primary:hover { border-color: var(--color-primary-hover); background: var(--color-primary-hover); }
.state-link:disabled { opacity: .65; cursor: wait; }
.session-hint, .switch-error { flex-basis: 100%; margin: 8px 0 0; color: var(--color-text-secondary); font-size: 13px; line-height: 1.8; overflow-wrap: anywhere; }
.switch-error { color: var(--color-danger); }
@media (max-width: 560px) { .forbidden-page { margin: 8px auto; } }
</style>

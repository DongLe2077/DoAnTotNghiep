<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/lib/api'
import { marketRedirect, safeRedirect } from '@/router/navigation'
import { useAuthStore } from '@/stores/auth'
import PwaInstallButton from '@/components/pwa/PwaInstallButton.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const username = ref('')
const password = ref('')
const showPassword = ref(false)
const submitting = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const errorMessage = ref('')
const errorBox = ref<HTMLElement | null>(null)
const retrySeconds = ref(0)
const registrationEnabled = ref(false)
onMounted(async () => {
  try {
    registrationEnabled.value = (await api.get<{ data: { enabled: boolean } }>('/auth/registration')).data.data.enabled
  } catch { /* An older backend can still serve the login screen. */ }
})
let retryTimer: ReturnType<typeof setInterval> | undefined

const notice = computed(() => {
  if (route.query.reason === 'password-changed') return 'Đã đổi mật khẩu thành công. Vui lòng đăng nhập bằng mật khẩu mới.'
  if (route.query.reason === 'session-expired') return 'Phiên đăng nhập đã kết thúc. Vui lòng đăng nhập lại để tiếp tục.'
  if (route.query.reason === 'logged-out') return 'Bạn đã đăng xuất an toàn.'
  return ''
})

function clearField(name: string) {
  delete fieldErrors.value[name]
}

function startRetryCountdown(seconds: number) {
  if (retryTimer) clearInterval(retryTimer)
  const deadline = Date.now() + Math.max(1, Math.ceil(seconds)) * 1000
  retrySeconds.value = Math.max(1, Math.ceil(seconds))
  retryTimer = setInterval(() => {
    retrySeconds.value = Math.max(0, Math.ceil((deadline - Date.now()) / 1000))
    if (!retrySeconds.value && retryTimer) clearInterval(retryTimer)
  }, 250)
}

async function submit() {
  if (submitting.value || retrySeconds.value > 0) return
  errorMessage.value = ''
  fieldErrors.value = {}
  if (!username.value.trim()) fieldErrors.value.username = ['Vui lòng nhập tên đăng nhập.']
  if (!password.value) fieldErrors.value.password = ['Vui lòng nhập mật khẩu.']
  if (Object.keys(fieldErrors.value).length) return

  submitting.value = true
  try {
    const user = await auth.login(username.value.trim(), password.value)
    password.value = ''
    const destination = safeRedirect(route.query.redirect, user.role)
    await router.replace(
      marketRedirect(route.query.redirect) ?? (user.needs_property ? '/properties'
        : user.properties && user.role === 'tenant' && destination === '/tenant' ? '/tenant/workspace' : destination),
    )
  } catch (error) {
    if (error instanceof ApiError) {
      fieldErrors.value = error.errors ?? {}
      errorMessage.value = error.status === 419
        ? 'Phiên bảo mật đã thay đổi. Vui lòng nhấn đăng nhập lại.'
        : error.message
      if (error.status === 429 && error.retryAfter) startRetryCountdown(error.retryAfter)
    } else {
      errorMessage.value = 'Chưa thể đăng nhập. Vui lòng thử lại.'
    }
    await nextTick()
    errorBox.value?.focus()
  } finally {
    submitting.value = false
  }
}

onBeforeUnmount(() => {
  password.value = ''
  if (retryTimer) clearInterval(retryTimer)
})
</script>

<template>
  <div class="login-page rb7-theme">
    <header class="login-header">
      <a class="brand" href="/login" aria-label="RentBase — Đăng nhập">
        <img class="brand-mark" src="/icons/rentbase-mark.svg" alt="" width="44" height="44" />
        <span>RentBase</span>
      </a>
      <PwaInstallButton />
    </header>

    <main class="login-main">
      <section class="form-area" aria-labelledby="login-heading">
        <div class="form-card">
          <h1 id="login-heading">Đăng nhập</h1>
          <p class="form-intro">Dùng tài khoản RentBase để tiếp tục.</p>

          <div v-if="notice" class="notice" role="status">
            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m6 10 3 3 5-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.4"/></svg>
            <span>{{ notice }}</span>
          </div>
          <div v-if="errorMessage" ref="errorBox" class="error-banner" role="alert" tabindex="-1">
            {{ errorMessage }}
            <span v-if="retrySeconds > 0" class="retry-countdown">Có thể thử lại sau {{ retrySeconds }} giây.</span>
          </div>

          <form novalidate :aria-busy="submitting" @submit.prevent="submit">
            <div class="field">
              <label for="login-username">Tên đăng nhập</label>
              <div class="input-wrap" :class="{ invalid: fieldErrors.username?.length }">
                <svg class="input-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="6" r="3" stroke="currentColor" stroke-width="1.4"/><path d="M4 17v-2a6 6 0 0 1 12 0v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                <input id="login-username" v-model="username" name="username" type="text" autocomplete="username" maxlength="80" placeholder="Nhập tên đăng nhập" :disabled="submitting" :aria-invalid="!!fieldErrors.username?.length" :aria-describedby="fieldErrors.username?.length ? 'username-error' : undefined" required @input="clearField('username')" />
              </div>
              <p v-if="fieldErrors.username?.length" id="username-error" class="field-error">{{ fieldErrors.username[0] }}</p>
            </div>
            <div class="field">
              <label for="login-password">Mật khẩu</label>
              <div class="input-wrap" :class="{ invalid: fieldErrors.password?.length }">
                <svg class="input-icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="4" y="8" width="12" height="9" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M6.5 8V5.5a3.5 3.5 0 0 1 7 0V8M10 11v3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                <input id="login-password" v-model="password" name="password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" maxlength="255" placeholder="Nhập mật khẩu" :disabled="submitting" :aria-invalid="!!fieldErrors.password?.length" :aria-describedby="fieldErrors.password?.length ? 'password-error' : undefined" required @input="clearField('password')" />
                <button class="visibility-button" type="button" :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" :aria-pressed="showPassword" :disabled="submitting" @click="showPassword = !showPassword">
                  <svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2 10s3-5 8-5 8 5 8 5-3 5-8 5-8-5-8-5Z" stroke="currentColor" stroke-width="1.4"/><circle cx="10" cy="10" r="2.2" stroke="currentColor" stroke-width="1.4"/><path v-if="showPassword" d="m3 3 14 14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                </button>
              </div>
              <p v-if="fieldErrors.password?.length" id="password-error" class="field-error">{{ fieldErrors.password[0] }}</p>
            </div>

            <button class="submit-button" type="submit" :disabled="submitting || retrySeconds > 0">
              <span v-if="submitting" class="spinner" aria-hidden="true"></span>
              <span>{{ submitting ? 'Đang đăng nhập…' : retrySeconds > 0 ? `Thử lại sau ${retrySeconds} giây` : 'Đăng nhập' }}</span>
              <svg v-if="!submitting && !retrySeconds" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M4 10h12m-5-5 5 5-5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </form>

          <div v-if="registrationEnabled" class="login-options">
            <p class="account-note">Chưa có tài khoản?</p>
            <div class="login-option-links">
              <RouterLink class="record-link" :to="{ name: 'register', query: { redirect: marketRedirect(route.query.redirect) ?? undefined } }">Tạo tài khoản</RouterLink>
              <RouterLink class="record-link" to="/find-rooms">Tìm phòng trọ</RouterLink>
            </div>
          </div>
          <p v-else class="account-note">Cần hỗ trợ tài khoản? Hãy liên hệ chủ trọ.</p>
        </div>
      </section>
    </main>
  </div>
</template>

<style scoped>
.login-page {
  --login-courtyard: url('@/assets/login-courtyard.webp');
  min-height: calc(100dvh - var(--pwa-notice-height, 0px));
  display: flex;
  flex-direction: column;
  background-color: var(--color-bg);
  background-image: linear-gradient(180deg, rgb(249 248 244 / .94), rgb(249 248 244 / .16) 195px, rgb(249 248 244 / .12) 70%, rgb(249 248 244 / .24)), var(--login-courtyard);
  background-position: center, center 48%;
  background-size: cover;
  background-repeat: no-repeat;
  color: var(--color-text);
}

.login-header, .login-main {
  width: min(1100px, calc(100% - 64px));
  margin-inline: auto;
}

.login-header {
  min-height: 80px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--color-text);
  text-decoration: none;
  font-size: clamp(19px, 3vw, 24px);
  font-weight: 700;
  letter-spacing: -.6px;
  border-radius: var(--radius-control);
}

.brand-mark {
  width: 44px;
  height: 44px;
  flex: none;
}

.login-main {
  display: grid;
  width: min(500px, calc(100% - 32px));
  margin-block: clamp(16px, 6vh, 72px) 32px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-card);
  background: var(--color-surface);
  box-shadow: 0 14px 48px rgb(24 40 63 / .14);
}

.form-area {
  display: grid;
  place-items: center;
  min-width: 0;
  padding: clamp(28px, 4vw, 58px);
}

.form-card { width: min(100%, 360px); }
.form-card h1 { margin: 0 0 6px; font-size: clamp(29px, 4vw, 38px); line-height: 1.3; letter-spacing: -.8px; }
.form-intro { margin: 0 0 27px; color: var(--color-text-secondary); font-size: 15px; }

.field { margin-bottom: 18px; }
.field label { display: block; margin-bottom: 8px; color: var(--color-text); font-size: 14px; font-weight: 600; }
.input-wrap { display: flex; align-items: center; min-height: 48px; background: var(--color-surface); border: 1px solid var(--color-input-border); border-radius: 10px; transition: border-color .15s; }
.input-wrap:focus-within { border-color: var(--color-focus); outline: 3px solid var(--color-focus); outline-offset: 2px; }
.input-wrap.invalid { border-color: var(--color-danger); }
.input-icon { flex-shrink: 0; width: 20px; height: 20px; margin-left: 16px; color: var(--color-text-muted); }
.input-wrap input { width: 100%; min-width: 0; min-height: 48px; padding: 12px; border: 0; border-radius: 10px; outline: 0; background: transparent; color: var(--color-text); font: inherit; font-size: 16px; }
.input-wrap input::placeholder { color: var(--color-text-muted); }
.input-wrap input:disabled { opacity: .65; }
.visibility-button { width: 44px; height: 44px; flex-shrink: 0; display: grid; place-items: center; margin-right: 2px; padding: 0; border: 0; border-radius: 8px; background: transparent; color: var(--color-text-secondary); cursor: pointer; }
.visibility-button svg { width: 20px; height: 20px; }
.visibility-button:hover:not(:disabled) { color: var(--color-primary); background: var(--color-primary-soft); }
.visibility-button:disabled { cursor: not-allowed; opacity: .65; }
.visibility-button:focus-visible,
.submit-button:focus-visible,
.brand:focus-visible { outline: 3px solid var(--color-focus); outline-offset: 3px; }

.submit-button { display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%; min-height: 50px; margin-top: 24px; padding: 12px 16px; border: 1px solid transparent; border-radius: var(--radius-control); background: var(--color-action); color: var(--color-action-text); font: inherit; font-size: 15px; font-weight: 600; cursor: pointer; transition: background var(--motion-normal); }
.submit-button:hover:not(:disabled) { background: var(--color-action-hover); }
.submit-button:disabled { opacity: .65; cursor: not-allowed; }
.submit-button svg { width: 20px; height: 20px; }
.account-note { margin: 16px 0 0; color: var(--color-text-secondary); font-size: 13px; }
.login-option-links { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 8px; }
.login-page .login-option-links .record-link { padding-inline: 6px; }

.notice,
.error-banner { margin-bottom: 24px; padding: 16px; border: 1px solid var(--color-border); border-radius: 10px; font-size: 14px; line-height: 1.7; }
.notice { display: flex; gap: 8px; color: var(--color-success); background: var(--color-primary-soft); }
.notice svg { flex-shrink: 0; width: 20px; height: 20px; margin-top: 2px; }
.error-banner { color: var(--color-danger); background: var(--color-danger-soft); border-color: var(--color-danger); }
.error-banner:focus { outline: 3px solid var(--color-focus); outline-offset: 3px; }
.retry-countdown { display: block; margin-top: 8px; font-weight: 600; }
.field-error { margin: 8px 0 0; color: var(--color-danger); font-size: 14px; line-height: 1.6; }
.spinner { width: 16px; height: 16px; border: 2px solid currentColor; border-inline-end-color: transparent; border-radius: 50%; animation: spin .8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 760px) {
  .login-page { position: relative; isolation: isolate; background-image: none; }
  .login-page::before {
    content: '';
    position: absolute;
    z-index: -1;
    inset: 0 0 auto;
    height: 225px;
    background-image: linear-gradient(180deg, rgb(249 248 244 / .95), rgb(249 248 244 / .28) 100px, var(--color-bg)), var(--login-courtyard);
    background-position: center, left 70%;
    background-size: cover;
  }
  .login-header, .login-main { width: min(448px, calc(100% - 32px)); }
  .login-header { min-height: 72px; }
  .login-main { display: block; margin-top: clamp(69px, 22vw, 99px); }
  .form-area { padding: 24px clamp(16px, 5vw, 28px); }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { transition: none !important; }
  .spinner { animation-duration: 1.5s; }
}
</style>

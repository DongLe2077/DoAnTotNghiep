<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError } from '@/lib/api'
import { safeRedirect } from '@/router/navigation'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const isDemo = import.meta.env.VITE_DEMO_MODE === '1'
const username = ref('')
const password = ref('')
const showPassword = ref(false)
const submitting = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const errorMessage = ref('')
const errorBox = ref<HTMLElement | null>(null)
const retrySeconds = ref(0)
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
    await router.replace(safeRedirect(route.query.redirect, user.role))
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
  <div class="login-page">
    <header class="login-header">
      <a class="brand" href="/login" aria-label="Nhà Trọ — Đăng nhập">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none"><path d="m4 10 8-6 8 6v10H4V10Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9 20v-7h6v7M9 8h6" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
        </span>
        <span>Nhà Trọ<span class="brand-dot">.</span></span>
      </a>
      <span class="header-caption">Không gian quản lý nhà trọ</span>
    </header>

    <main class="login-main">
      <section class="intro" aria-labelledby="intro-heading">
        <div class="intro-copy">
          <span class="eyebrow"><span></span> Nhà trọ của bạn</span>
          <h1 id="intro-heading">Quản lý gọn gàng.<br /><em>An tâm mỗi ngày.</em></h1>
          <p>Theo dõi phòng ở, hóa đơn và yêu cầu sửa chữa trong một không gian dễ sử dụng.</p>
        </div>

        <div class="home-art" aria-hidden="true">
          <div class="art-sun"></div>
          <div class="art-grid"></div>
          <svg class="building" viewBox="0 0 440 230" fill="none">
            <path d="M33 209H416" stroke="#8BABA0" stroke-width="1.5"/>
            <path d="M84 208V69L155 31 226 69V208" fill="#D1DFCE" stroke="#66877C" stroke-width="1.5" stroke-linejoin="round"/>
            <path d="M76 72 155 29 234 72" stroke="#426C60" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M226 208V104H346V208" fill="#E9E8D9" stroke="#66877C" stroke-width="1.5"/>
            <path d="M217 104H354" stroke="#426C60" stroke-width="3" stroke-linecap="round"/>
            <path d="M111 91h27v32h-27zM171 91h27v32h-27zM111 146h27v32h-27zM171 146h27v32h-27z" fill="#FFFDF3" stroke="#66877C" stroke-width="1.5"/>
            <path d="M124 91v32M184 91v32M124 146v32M184 146v32M111 107h27M171 107h27M111 162h27M171 162h27" stroke="#9DB6A5" stroke-width="1.2"/>
            <path d="M260 145h34v63h-34z" fill="#749684" stroke="#426C60" stroke-width="1.5"/><path d="M284 177h2" stroke="#F8F5E8" stroke-width="2" stroke-linecap="round"/>
            <path d="M309 130h19v25h-19z" fill="#FFFDF3" stroke="#66877C" stroke-width="1.5"/>
            <path d="M49 208v-36M39 185c-16-19 6-39 11-20 6-24 26-7 11 11 17-2 14 18-12 15" fill="#9CAF87"/><path d="M49 208v-38" stroke="#66877C" stroke-width="1.5"/>
            <path d="M377 208v-48M377 185c-31-1-24-29-10-21-13-29 19-38 22-16 22-9 34 26 1 32" fill="#A6B99A"/><path d="M377 208v-53" stroke="#66877C" stroke-width="1.5"/>
            <path d="M249 209h56M145 208h20" stroke="#426C60" stroke-width="3" stroke-linecap="round"/>
          </svg>
          <span class="art-label"><span></span> Kết nối chủ trọ và người thuê</span>
        </div>
      </section>

      <section class="form-area" aria-labelledby="login-heading">
        <div class="form-card">
          <div class="welcome-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M10 5H6v14h4M14 8l4 4-4 4M8 12h10" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
          <span class="form-eyebrow">Chào mừng bạn trở lại</span>
          <h2 id="login-heading">Đăng nhập</h2>
          <p class="form-intro">Tiếp tục quản lý và theo dõi nơi ở của bạn.</p>

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

          <aside v-if="isDemo" class="demo-guide" aria-label="Tài khoản dùng thử"><strong>Tài khoản dùng thử</strong><p>Chủ trọ: <code>admin / 123</code></p><p>Người thuê: <code>user / 123</code></p><p>Để thử hai vai trò cùng lúc, dùng hai trình duyệt hoặc cửa sổ ẩn danh.</p></aside>
          <p class="account-note">Sử dụng tài khoản đã được cấp.<br />Nếu cần hỗ trợ tài khoản, vui lòng liên hệ chủ trọ.</p>
          <div class="role-note"><span></span> Dành cho chủ trọ và người thuê</div>
        </div>
      </section>
    </main>
    <footer class="login-footer"><span>Nhà Trọ · Không gian quản lý của bạn</span><span>Gọn gàng, rõ ràng, dễ theo dõi</span></footer>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: var(--color-bg);
  color: var(--color-text);
}

.login-header,
.login-main,
.login-footer {
  width: min(1160px, calc(100% - 80px));
  margin: 0 auto;
}

.login-header {
  min-height: 96px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}

.brand {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  color: var(--color-text);
  text-decoration: none;
  font-size: 24px;
  font-weight: 700;
  letter-spacing: -.5px;
  border-radius: 10px;
}

.brand-mark {
  width: 44px;
  height: 44px;
  display: grid;
  place-items: center;
  background: var(--color-primary);
  color: #fff;
  border-radius: 12px;
}

.brand-mark svg { width: 26px; height: 26px; }
.brand-dot { color: var(--color-primary); }
.header-caption { color: var(--color-text-secondary); font-size: 14px; }

.login-main {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 32px;
  align-items: stretch;
  padding: 16px 0 32px;
}

.intro {
  position: relative;
  overflow: hidden;
  min-height: 560px;
  display: flex;
  flex-direction: column;
  padding: 48px 40px 24px;
  background: var(--color-primary-soft);
  border: 1px solid var(--color-border);
  border-radius: 16px;
}

.eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: var(--color-primary);
  font-size: 14px;
  font-weight: 600;
}

.eyebrow span,
.art-label span,
.role-note span {
  width: 6px;
  height: 6px;
  flex-shrink: 0;
  border-radius: 50%;
  background: var(--color-primary);
}

.intro h1 {
  margin: 24px 0 16px;
  font-size: clamp(28px, 3vw, 36px);
  font-weight: 650;
  line-height: 1.4;
  letter-spacing: -.8px;
}

.intro h1 em { color: var(--color-primary); font-style: normal; }
.intro p { max-width: 360px; margin: 0; color: var(--color-text-secondary); font-size: 15px; line-height: 1.8; }

.home-art { position: relative; z-index: 1; margin-top: auto; padding-top: 40px; }
.building { position: relative; left: -8px; display: block; width: calc(100% + 16px); max-height: 224px; }
.art-sun { position: absolute; top: 48px; right: 32px; width: 48px; height: 48px; border-radius: 50%; background: #e4ddbc; }
.art-grid { display: none; }
.art-label { display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 16px; color: var(--color-text-secondary); font-size: 12px; }

.form-area {
  display: grid;
  place-items: center;
  padding: 40px;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 16px;
}

.form-card { width: 100%; max-width: 384px; }
.welcome-icon { width: 44px; height: 44px; display: grid; place-items: center; margin-bottom: 24px; color: var(--color-primary); background: var(--color-primary-soft); border-radius: 12px; }
.welcome-icon svg { width: 24px; height: 24px; }
.form-eyebrow { color: var(--color-text-secondary); font-size: 14px; }
.form-card h2 { margin: 8px 0 12px; font-size: 32px; line-height: 1.35; font-weight: 650; letter-spacing: -.7px; }
.form-intro { margin: 0 0 32px; color: var(--color-text-secondary); font-size: 14px; line-height: 1.7; }

.field { margin-bottom: 24px; }
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

.submit-button { display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%; min-height: 48px; margin-top: 32px; padding: 12px 16px; border: 1px solid var(--color-primary); border-radius: 10px; background: var(--color-primary); color: #fff; font: inherit; font-size: 15px; font-weight: 600; cursor: pointer; transition: background .15s; }
.submit-button:hover:not(:disabled) { background: var(--color-primary-hover); border-color: var(--color-primary-hover); }
.submit-button:disabled { opacity: .65; cursor: not-allowed; }
.submit-button svg { width: 20px; height: 20px; }
.account-note { margin: 24px 0; color: var(--color-text-secondary); font-size: 12px; line-height: 1.8; text-align: center; }
.demo-guide { margin-top: 24px; padding: 16px; border: 1px solid var(--color-border); border-radius: 10px; color: var(--color-text-secondary); background: var(--color-primary-soft); font-size: 13px; line-height: 1.8; }
.demo-guide strong { color: var(--color-primary); }
.demo-guide p { margin: 6px 0 0; }
.demo-guide code { color: var(--color-text); font: inherit; font-weight: 600; white-space: nowrap; }
.role-note { display: flex; justify-content: center; align-items: center; gap: 8px; padding-top: 24px; border-top: 1px solid var(--color-border); color: var(--color-text-secondary); font-size: 12px; }

.notice,
.error-banner { margin-bottom: 24px; padding: 16px; border: 1px solid var(--color-border); border-radius: 10px; font-size: 14px; line-height: 1.7; }
.notice { display: flex; gap: 8px; color: var(--color-success); background: var(--color-primary-soft); }
.notice svg { flex-shrink: 0; width: 20px; height: 20px; margin-top: 2px; }
.error-banner { color: var(--color-danger); background: var(--color-danger-soft); border-color: var(--color-danger); }
.error-banner:focus { outline: 3px solid var(--color-focus); outline-offset: 3px; }
.retry-countdown { display: block; margin-top: 8px; font-weight: 600; }
.field-error { margin: 8px 0 0; color: var(--color-danger); font-size: 14px; line-height: 1.6; }
.spinner { width: 16px; height: 16px; border: 2px solid #ffffff70; border-top-color: #fff; border-radius: 50%; animation: spin .8s linear infinite; }
.login-footer { min-height: 64px; display: flex; justify-content: space-between; align-items: center; gap: 24px; padding-bottom: 16px; color: var(--color-text-muted); font-size: 12px; }

@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 1050px) {
  .login-header, .login-main, .login-footer { width: calc(100% - 48px); }
  .login-main { gap: 24px; }
  .intro { padding: 40px 24px 24px; }
  .intro h1 { font-size: 30px; }
  .form-area { padding: 32px 24px; }
}

@media (max-width: 780px) {
  .login-header, .login-main, .login-footer { width: min(448px, calc(100% - 40px)); }
  .login-header { min-height: 80px; }
  .header-caption, .intro { display: none; }
  .login-main { display: block; padding: 16px 0 24px; }
  .form-area { padding: 32px 24px; }
  .form-card { max-width: none; }
  .form-card h2 { font-size: 30px; }
  .visibility-button { width: 48px; height: 48px; }
  .login-footer { justify-content: center; min-height: 48px; text-align: center; }
  .login-footer span:last-child { display: none; }
}

@media (max-width: 380px) {
  .login-header, .login-main, .login-footer { width: calc(100% - 32px); }
  .form-area { padding: 24px 16px; }
  .brand { font-size: 22px; }
}

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { transition: none !important; }
  .spinner { animation-duration: 1.5s; }
}
</style>

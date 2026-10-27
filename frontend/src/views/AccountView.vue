<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const currentPassword = ref('')
const newPassword = ref('')
const confirmation = ref('')
const showCurrent = ref(false)
const showNew = ref(false)
const showConfirmation = ref(false)
const submitting = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const errorMessage = ref('')
const errorBox = ref<HTMLElement | null>(null)
const roleLabel = computed(() => auth.user?.role === 'owner' ? 'Chủ trọ' : 'Người thuê')

function clearField(name: string) {
  delete fieldErrors.value[name]
}

async function submit() {
  if (submitting.value) return
  fieldErrors.value = {}
  errorMessage.value = ''
  if (!currentPassword.value) fieldErrors.value.current_password = ['Vui lòng nhập mật khẩu hiện tại.']
  if (!newPassword.value) fieldErrors.value.password = ['Vui lòng nhập mật khẩu mới.']
  else if ([...newPassword.value].length < 8) fieldErrors.value.password = ['Mật khẩu mới cần ít nhất 8 ký tự.']
  else if (new TextEncoder().encode(newPassword.value).length > 72 || newPassword.value.includes('\0')) fieldErrors.value.password = ['Mật khẩu quá dài hoặc chứa ký tự không hợp lệ. Vui lòng chọn mật khẩu khác.']
  if (!confirmation.value) fieldErrors.value.password_confirmation = ['Vui lòng nhập lại mật khẩu mới.']
  else if (confirmation.value !== newPassword.value) fieldErrors.value.password_confirmation = ['Xác nhận mật khẩu mới chưa khớp.']
  if (Object.keys(fieldErrors.value).length) return

  submitting.value = true
  try {
    await auth.changePassword({ current_password: currentPassword.value, password: newPassword.value, password_confirmation: confirmation.value })
    currentPassword.value = ''
    newPassword.value = ''
    confirmation.value = ''
    await router.replace({ name: 'login', query: { reason: 'password-changed' } })
  } catch (error) {
    if (error instanceof ApiError) {
      fieldErrors.value = error.errors ?? {}
      errorMessage.value = error.status === 419
        ? 'Phiên bảo mật đã thay đổi. Vui lòng thử gửi lại yêu cầu.'
        : error.message
    } else errorMessage.value = 'Chưa thể đổi mật khẩu. Vui lòng thử lại.'
    await nextTick()
    errorBox.value?.focus()
  } finally {
    submitting.value = false
  }
}

onBeforeUnmount(() => {
  currentPassword.value = ''
  newPassword.value = ''
  confirmation.value = ''
})
</script>

<template>
  <section class="account-page" aria-labelledby="account-heading">
    <header class="page-heading"><span class="eyebrow">Không gian cá nhân</span><h1 id="account-heading">Tài khoản của bạn</h1><p>Quản lý thông tin đăng nhập và bảo mật tài khoản.</p></header>

    <div class="account-grid">
      <section class="password-card" aria-labelledby="password-heading">
        <header class="card-heading">
          <span class="heading-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><rect x="5" y="10" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M8 10V7a4 4 0 1 1 8 0v3M12 14v3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
          <div><h2 id="password-heading">Đổi mật khẩu</h2><p>Chọn một mật khẩu riêng cho tài khoản của bạn.</p></div>
        </header>
        <div v-if="errorMessage" ref="errorBox" class="error-banner" role="alert" tabindex="-1">{{ errorMessage }}</div>
        <form novalidate :aria-busy="submitting" @submit.prevent="submit">
          <input :value="auth.user?.username" type="text" name="username" autocomplete="username" class="sr-only" tabindex="-1" readonly aria-label="Tên đăng nhập" />
          <div class="field">
            <label for="current-password">Mật khẩu hiện tại <span aria-hidden="true">*</span></label>
            <div class="input-wrap" :class="{ invalid: fieldErrors.current_password?.length }">
              <input id="current-password" v-model="currentPassword" name="current_password" :type="showCurrent ? 'text' : 'password'" autocomplete="current-password" maxlength="255" placeholder="Nhập mật khẩu đang sử dụng" :disabled="submitting" :aria-invalid="!!fieldErrors.current_password?.length" :aria-describedby="fieldErrors.current_password?.length ? 'current-error' : undefined" required @input="clearField('current_password')" />
              <button type="button" class="visibility-button" :aria-label="showCurrent ? 'Ẩn mật khẩu hiện tại' : 'Hiện mật khẩu hiện tại'" :aria-pressed="showCurrent" :disabled="submitting" @click="showCurrent = !showCurrent"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2 10s3-5 8-5 8 5 8 5-3 5-8 5-8-5-8-5Z" stroke="currentColor" stroke-width="1.4"/><circle cx="10" cy="10" r="2.2" stroke="currentColor" stroke-width="1.4"/><path v-if="showCurrent" d="m3 3 14 14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></button>
            </div>
            <p v-if="fieldErrors.current_password?.length" id="current-error" class="field-error">{{ fieldErrors.current_password[0] }}</p>
          </div>
          <div class="field">
            <label for="new-password">Mật khẩu mới <span aria-hidden="true">*</span></label>
            <div class="input-wrap" :class="{ invalid: fieldErrors.password?.length }">
              <input id="new-password" v-model="newPassword" name="password" :type="showNew ? 'text' : 'password'" autocomplete="new-password" maxlength="72" placeholder="Nhập mật khẩu mới" :disabled="submitting" :aria-invalid="!!fieldErrors.password?.length" :aria-describedby="fieldErrors.password?.length ? 'new-password-hint new-error' : 'new-password-hint'" required @input="clearField('password')" />
              <button type="button" class="visibility-button" :aria-label="showNew ? 'Ẩn mật khẩu mới' : 'Hiện mật khẩu mới'" :aria-pressed="showNew" :disabled="submitting" @click="showNew = !showNew"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2 10s3-5 8-5 8 5 8 5-3 5-8 5-8-5-8-5Z" stroke="currentColor" stroke-width="1.4"/><circle cx="10" cy="10" r="2.2" stroke="currentColor" stroke-width="1.4"/><path v-if="showNew" d="m3 3 14 14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></button>
            </div>
            <p id="new-password-hint" class="field-hint">Ít nhất 8 ký tự. Nên kết hợp chữ, số và ký tự đặc biệt.</p>
            <p v-if="fieldErrors.password?.length" id="new-error" class="field-error">{{ fieldErrors.password[0] }}</p>
          </div>
          <div class="field">
            <label for="confirm-password">Xác nhận mật khẩu mới <span aria-hidden="true">*</span></label>
            <div class="input-wrap" :class="{ invalid: fieldErrors.password_confirmation?.length }">
              <input id="confirm-password" v-model="confirmation" name="password_confirmation" :type="showConfirmation ? 'text' : 'password'" autocomplete="new-password" maxlength="72" placeholder="Nhập lại mật khẩu mới" :disabled="submitting" :aria-invalid="!!fieldErrors.password_confirmation?.length" :aria-describedby="fieldErrors.password_confirmation?.length ? 'confirmation-error' : undefined" required @input="clearField('password_confirmation')" />
              <button type="button" class="visibility-button" :aria-label="showConfirmation ? 'Ẩn xác nhận mật khẩu' : 'Hiện xác nhận mật khẩu'" :aria-pressed="showConfirmation" :disabled="submitting" @click="showConfirmation = !showConfirmation"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M2 10s3-5 8-5 8 5 8 5-3 5-8 5-8-5-8-5Z" stroke="currentColor" stroke-width="1.4"/><circle cx="10" cy="10" r="2.2" stroke="currentColor" stroke-width="1.4"/><path v-if="showConfirmation" d="m3 3 14 14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></button>
            </div>
            <p v-if="fieldErrors.password_confirmation?.length" id="confirmation-error" class="field-error">{{ fieldErrors.password_confirmation[0] }}</p>
          </div>
          <div class="session-note"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.3"/><path d="M10 9v5m0-8v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg><p>Sau khi đổi mật khẩu thành công, bạn sẽ đăng xuất và cần đăng nhập lại.</p></div>
          <div class="form-footer"><button class="submit-button" type="submit" :disabled="submitting"><span v-if="submitting" class="spinner" aria-hidden="true"></span><svg v-else viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 10 3 3 7-7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ submitting ? 'Đang lưu mật khẩu…' : 'Lưu mật khẩu mới' }}</button></div>
        </form>
      </section>

      <aside class="account-aside" aria-label="Thông tin tài khoản">
        <div class="identity-card"><div class="avatar" aria-hidden="true">{{ auth.user?.username?.slice(0, 1).toLocaleUpperCase('vi-VN') || 'N' }}</div><span class="role-badge">{{ roleLabel }}</span><h2>{{ auth.user?.username }}</h2><p>Tài khoản đang đăng nhập</p><div class="identity-status"><span></span> Đang hoạt động</div></div>
        <div class="security-tip"><span class="tip-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m12 3 8 3v6c0 5-8 9-8 9S4 17 4 12V6l8-3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="m8 12 3 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></span><h3>Giữ tài khoản an toàn</h3><p>Dùng mật khẩu riêng và không chia sẻ cho người khác. Hãy đăng xuất sau khi sử dụng thiết bị dùng chung.</p></div>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.account-page { width: 100%; max-width: 1120px; margin: 0 auto; color: var(--color-text); }
.page-heading { margin-bottom: 32px; }
.eyebrow { color: var(--color-primary); font-size: 14px; font-weight: 600; }
.page-heading h1 { margin: 8px 0 12px; font-size: 32px; line-height: 1.35; letter-spacing: -.7px; font-weight: 650; }
.page-heading p { margin: 0; color: var(--color-text-secondary); font-size: 15px; line-height: 1.7; }
.account-grid { display: grid; grid-template-columns: minmax(0, 1fr) 288px; align-items: start; gap: 24px; }
.password-card { overflow: hidden; padding: 32px 32px 0; border: 1px solid var(--color-border); border-radius: 16px; background: var(--color-surface); }
.card-heading { display: flex; align-items: center; gap: 16px; margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid var(--color-border); }
.heading-icon { display: grid; place-items: center; flex-shrink: 0; width: 48px; height: 48px; border-radius: 12px; background: var(--color-primary-soft); color: var(--color-primary); }
.heading-icon svg { width: 24px; height: 24px; }
.card-heading h2 { margin: 0 0 8px; font-size: 18px; font-weight: 600; line-height: 1.5; }
.card-heading p { margin: 0; color: var(--color-text-secondary); font-size: 14px; line-height: 1.7; }
.field { max-width: 480px; margin-bottom: 24px; }
.field label { display: block; margin-bottom: 8px; color: var(--color-text); font-size: 14px; font-weight: 600; }
.field label span { margin-left: 4px; color: var(--color-danger); }
.input-wrap { display: flex; align-items: center; min-height: 48px; border: 1px solid var(--color-input-border); border-radius: 10px; background: var(--color-surface); transition: border-color .15s; }
.input-wrap:focus-within { border-color: var(--color-focus); outline: 3px solid var(--color-focus); outline-offset: 2px; }
.input-wrap.invalid { border-color: var(--color-danger); }
.input-wrap input { width: 100%; min-width: 0; min-height: 48px; box-sizing: border-box; padding: 12px 16px; border: 0; border-radius: 10px; outline: 0; background: transparent; color: var(--color-text); font: inherit; font-size: 16px; }
.input-wrap input::placeholder { color: var(--color-text-muted); }
.input-wrap input:disabled { opacity: .65; }
.visibility-button { display: grid; place-items: center; flex-shrink: 0; width: 44px; height: 44px; margin-right: 2px; padding: 0; border: 0; border-radius: 8px; background: transparent; color: var(--color-text-secondary); cursor: pointer; }
.visibility-button svg { width: 20px; height: 20px; }
.visibility-button:hover:not(:disabled) { background: var(--color-primary-soft); color: var(--color-primary); }
.visibility-button:disabled { cursor: not-allowed; opacity: .65; }
.visibility-button:focus-visible, .submit-button:focus-visible { outline: 3px solid var(--color-focus); outline-offset: 3px; }
.field-hint { margin: 8px 0 0; color: var(--color-text-secondary); font-size: 12px; line-height: 1.7; }
.field-error { margin: 8px 0 0; color: var(--color-danger); font-size: 14px; line-height: 1.7; }
.session-note { display: flex; gap: 8px; max-width: 480px; box-sizing: border-box; margin: 8px 0 32px; padding: 16px; border: 1px solid var(--color-border); border-radius: 10px; background: var(--color-bg); color: var(--color-text-secondary); }
.session-note svg { flex-shrink: 0; width: 20px; height: 20px; margin-top: 2px; }
.session-note p { margin: 0; font-size: 14px; line-height: 1.7; }
.form-footer { display: flex; justify-content: flex-end; margin: 0 -32px; padding: 24px 32px; border-top: 1px solid var(--color-border); background: var(--color-surface); }
.submit-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; padding: 12px 20px; border: 1px solid var(--color-primary); border-radius: 10px; background: var(--color-primary); color: #fff; font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; transition: background .15s; }
.submit-button:hover:not(:disabled) { background: var(--color-primary-hover); border-color: var(--color-primary-hover); }
.submit-button:disabled { opacity: .65; cursor: not-allowed; }
.submit-button svg { width: 20px; height: 20px; }
.spinner { width: 16px; height: 16px; border: 2px solid #ffffff70; border-top-color: #fff; border-radius: 50%; animation: spin .8s linear infinite; }
.identity-card { padding: 32px 24px 24px; border: 1px solid var(--color-border); border-radius: 16px; background: var(--color-surface); text-align: center; }
.avatar { display: grid; place-items: center; width: 64px; height: 64px; margin: 0 auto 24px; border-radius: 16px; background: var(--color-primary-soft); color: var(--color-primary); font-size: 28px; font-weight: 600; }
.role-badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 6px; background: var(--color-primary-soft); color: var(--color-primary); font-size: 12px; font-weight: 500; line-height: 1.6; }
.identity-card h2 { margin: 16px 0 8px; color: var(--color-text); font-size: 18px; font-weight: 600; line-height: 1.5; overflow-wrap: anywhere; }
.identity-card p { margin: 0; color: var(--color-text-secondary); font-size: 12px; line-height: 1.7; }
.identity-status { display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--color-border); color: var(--color-success); font-size: 12px; }
.identity-status span { width: 6px; height: 6px; border-radius: 50%; background: var(--color-success); }
.security-tip { margin-top: 24px; padding: 24px; border: 1px solid var(--color-border); border-radius: 16px; background: var(--color-primary-soft); }
.tip-icon { display: block; margin-bottom: 12px; color: var(--color-primary); }
.tip-icon svg { width: 24px; height: 24px; }
.security-tip h3 { margin: 0 0 8px; color: var(--color-text); font-size: 14px; font-weight: 600; line-height: 1.6; }
.security-tip p { margin: 0; color: var(--color-text-secondary); font-size: 14px; line-height: 1.8; }
.error-banner { margin-bottom: 24px; padding: 16px; border: 1px solid var(--color-danger); border-radius: 10px; background: var(--color-danger-soft); color: var(--color-danger); font-size: 14px; line-height: 1.7; }
.error-banner:focus { outline: 3px solid var(--color-focus); outline-offset: 3px; }
.sr-only { position: absolute; height: 1px; width: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

@keyframes spin { to { transform: rotate(360deg); } }

@media (max-width: 1050px) {
  .account-grid { grid-template-columns: minmax(0, 1fr) 256px; }
  .password-card { padding: 24px 24px 0; }
  .form-footer { margin: 0 -24px; padding: 24px; }
}

@media (max-width: 800px) {
  .account-grid { grid-template-columns: minmax(0, 1fr); }
  .account-aside { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
  .security-tip { margin-top: 0; }
  .page-heading h1 { font-size: 28px; }
  .field, .session-note { max-width: none; }
  .visibility-button { width: 48px; height: 48px; }
}

@media (max-width: 560px) {
  .password-card { padding: 24px 16px 0; border-radius: 12px; }
  .card-heading { align-items: flex-start; gap: 12px; }
  .heading-icon { width: 40px; height: 40px; }
  .form-footer { margin: 0 -16px; padding: 24px 16px; }
  .submit-button { width: 100%; }
  .account-aside { grid-template-columns: minmax(0, 1fr); gap: 16px; }
  .identity-card, .security-tip { padding: 24px; border-radius: 12px; }
}

@media (prefers-reduced-motion: reduce) {
  * { transition: none !important; }
  .spinner { animation-duration: 1.5s; }
}
</style>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import { api, toApiError } from '@/lib/api'
import { marketRedirect } from '@/router/navigation'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const enabled = ref(false)
const checked = ref(false)
const busy = ref(false)
const error = ref('')
const fields = ref<Record<string, string[]>>({})
const form = reactive({ full_name: '', contact_phone: '', username: '', password: '', password_confirmation: '' })

onMounted(async () => {
  try {
    const result = await api.get<{ data: { enabled: boolean } }>('/auth/registration')
    enabled.value = result.data.data.enabled
  } catch (e) {
    error.value = toApiError(e).message
  } finally {
    checked.value = true
  }
})

async function register() {
  if (busy.value) return
  error.value = ''
  fields.value = {}
  busy.value = true
  try {
    await auth.register({ ...form, contact_phone: form.contact_phone.replace(/\s/g, '') })
    form.password = ''
    form.password_confirmation = ''
    await router.replace(marketRedirect(route.query.redirect) ?? '/properties')
  } catch (e) {
    const failure = toApiError(e)
    error.value = failure.message
    fields.value = failure.errors
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <main class="registration rb7-theme">
    <RouterLink to="/login" class="registration-brand">
      <img src="/icons/rentbase-mark.svg" width="44" height="44" alt="" />
      <strong>RentBase</strong>
    </RouterLink>

    <section class="record-card registration-panel" aria-labelledby="register-title">
      <h1 id="register-title">Tạo tài khoản</h1>
      <p class="help-text">Tạo tài khoản để tìm phòng hoặc tham gia khu trọ bằng mã mời của chủ trọ.</p>
      <p v-if="error" class="form-error" role="alert">{{ error }}</p>
      <p v-if="!checked" role="status">Đang mở biểu mẫu đăng ký…</p>
      <p v-else-if="!enabled && !error">Bản đang chạy chưa mở đăng ký tài khoản mới.</p>
      <form v-else-if="enabled" class="form-grid" @submit.prevent="register">
        <fieldset class="form-grid" :disabled="busy">
          <OwnerField v-model="form.full_name" label="Họ và tên" name="full_name" autocomplete="name" required :max-length="150" :errors="fields" />
          <OwnerField v-model="form.contact_phone" label="Số điện thoại" name="contact_phone" type="tel" autocomplete="tel" required :max-length="20" :errors="fields" />
          <OwnerField v-model="form.username" label="Tên đăng nhập" name="username" autocomplete="username" required :max-length="80" :errors="fields" hint="Từ 4 ký tự, dùng chữ không dấu, số, dấu chấm hoặc gạch." />
          <OwnerField v-model="form.password" label="Mật khẩu" name="password" type="password" autocomplete="new-password" required :max-length="72" :errors="fields" hint="Ít nhất 8 ký tự." />
          <OwnerField v-model="form.password_confirmation" label="Nhập lại mật khẩu" name="password_confirmation" type="password" autocomplete="new-password" required :max-length="72" :errors="fields" />
        </fieldset>
        <UiButton type="submit" :loading="busy">Tạo tài khoản</UiButton>
      </form>
      <p class="registration-login">Đã có tài khoản? <RouterLink class="record-link" :to="{ name: 'login', query: { redirect: marketRedirect(route.query.redirect) ?? undefined } }">Đăng nhập</RouterLink></p>
    </section>
  </main>
</template>

<style scoped>
.registration { width: min(100%, 620px); margin: 0 auto; padding: clamp(16px, 4vw, 44px); }
.registration-brand { display: inline-flex; align-items: center; gap: 12px; margin-bottom: 24px; text-decoration: none; }
.registration-panel { padding: clamp(20px, 4vw, 36px); }
.registration-panel h1 { margin: 0; font-size: clamp(29px, 5vw, 42px); }
.registration-panel form { margin-top: 22px; }
.registration-panel fieldset { min-width: 0; margin: 0; padding: 0; border: 0; }
.registration-login { margin: 20px 0 0; }
</style>

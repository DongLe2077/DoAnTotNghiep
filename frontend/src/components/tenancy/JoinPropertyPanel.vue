<script setup lang="ts">
import { useLiveRefresh } from '@/composables/useLiveRefresh'
import { onMounted, ref, watch } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import { api, prepareCsrf, toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import { invitationLabels, joinLabels, type JoinRequest, type Paged } from '@/types/property-onboarding'

const emit = defineEmits<{ refreshed: [] }>()
const code = ref('')
const preview = ref<{ property_name: string; code: string } | null>(null)
const history = ref<Paged<JoinRequest> | null>(null)
const error = ref('')
const historyError = ref('')
const notice = ref('')
const busy = ref(false)
const loading = ref(false)

watch(code, () => { preview.value = null; error.value = '' })

async function refresh(page = 1, notifyParent = true) {
  loading.value = true
  historyError.value = ''
  try {
    const response = await api.get<Paged<JoinRequest>>('/properties/join-requests', { params: { page } })
    history.value = response.data
    if (notifyParent) emit('refreshed')
  } catch (e) {
    historyError.value = toApiError(e).message
  } finally {
    loading.value = false
  }
}

async function lookup() {
  if (busy.value || !code.value.trim()) return
  busy.value = true
  error.value = ''
  notice.value = ''
  const submittedCode = code.value.trim()
  try {
    await prepareCsrf()
    const response = await api.post<{ data: { property_name: string } }>('/properties/join-preview', { code: submittedCode })
    if (code.value.trim() === submittedCode) preview.value = { ...response.data.data, code: submittedCode }
  } catch (e) {
    error.value = toApiError(e).message
  } finally {
    busy.value = false
  }
}

async function join() {
  if (busy.value || !preview.value) return
  busy.value = true
  error.value = ''
  try {
    await prepareCsrf()
    await api.post('/properties/join-requests', { code: preview.value.code })
    code.value = ''
    preview.value = null
    notice.value = 'Đã gửi yêu cầu. Bạn có thể theo dõi kết quả duyệt bên dưới.'
    await refresh()
  } catch (e) {
    error.value = toApiError(e).message
  } finally {
    busy.value = false
  }
}

async function withdraw(id: number) {
  if (busy.value) return
  busy.value = true
  historyError.value = ''
  try {
    await prepareCsrf()
    await api.delete('/properties/join-requests/' + id)
    await refresh(history.value?.meta.current_page)
  } catch (e) {
    historyError.value = toApiError(e).message
  } finally {
    busy.value = false
  }
}

onMounted(() => refresh(1, false))
useLiveRefresh('user', () => !loading.value ? refresh() : false)
</script>

<template>
  <section id="join-property" class="record-card form-grid">
    <div>
      <h2>Nhập mã mời</h2>
      <p class="help-text">Dùng mã do chủ trọ gửi để xin tham gia khu trọ.</p>
    </div>
    <form class="form-grid invitation-form" @submit.prevent="lookup">
      <label for="invite-code">Mã mời
        <input id="invite-code" v-model="code" class="ui-input" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="64" required :disabled="busy" placeholder="Dán mã do chủ trọ gửi" />
      </label>
      <UiButton type="submit" variant="secondary" :loading="busy && !preview">Kiểm tra mã</UiButton>
    </form>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <div v-if="preview" class="invite-preview">
      <div><strong>{{ preview.property_name }}</strong><p class="help-text">Nếu đúng khu trọ, hãy gửi yêu cầu để chủ trọ duyệt.</p></div>
      <UiButton :loading="busy" @click="join">Gửi yêu cầu tham gia</UiButton>
    </div>
    <p v-if="notice" role="status">{{ notice }}</p>
  </section>

  <section class="record-card form-grid">
    <div class="history-heading">
      <h2>Yêu cầu của tôi</h2>
      <UiButton variant="secondary" :loading="loading" @click="refresh()">Làm mới</UiButton>
    </div>
    <p v-if="historyError" class="form-error" role="alert">{{ historyError }}</p>
    <p v-else-if="loading && !history" role="status">Đang tải yêu cầu…</p>
    <p v-else-if="!history?.data.length" class="help-text">Bạn chưa gửi yêu cầu tham gia khu trọ.</p>
    <ul v-else class="detail-items">
      <li v-for="item in history.data" :key="item.id">
        <div class="history-heading">
          <strong>{{ item.property_name }}</strong>
          <UiBadge :tone="item.status === 'approved' && item.membership_status === 'approved' ? 'success' : item.status === 'pending' ? 'warning' : 'neutral'">
            {{ item.status === 'approved' && item.membership_status === 'revoked' ? 'Đã thu hồi quyền' : joinLabels[item.status] }}
          </UiBadge>
        </div>
        <p class="help-text">Gửi {{ formatDateTime(item.created_at) }}</p>
        <p v-if="item.status === 'pending' && item.invitation_status !== 'active'" class="help-text">{{ invitationLabels[item.invitation_status] }}. Xin chủ trọ mã mới rồi nhập lại tại đây.</p>
        <UiButton v-if="item.status === 'pending'" variant="ghost" :disabled="busy" @click="withdraw(item.id)">Rút yêu cầu</UiButton>
      </li>
    </ul>
    <OwnerPager v-if="history && history.meta.last_page > 1" :page="history.meta.current_page" :last-page="history.meta.last_page" :total="history.meta.total" :loading="loading" @change="refresh" />
  </section>
</template>

<style scoped>
h2 { margin: 0; }
.invitation-form, .invite-preview, .history-heading { display: flex; align-items: end; flex-wrap: wrap; gap: 12px; justify-content: space-between; }
.invitation-form label { display: grid; gap: 6px; flex: 1 1 220px; min-width: 0; }
.invite-preview { align-items: center; background: var(--color-primary-soft); border-radius: var(--radius-control); padding: 15px; }
.invite-preview p { margin-bottom: 0; }
.history-heading { align-items: center; }
.detail-items li { padding: 12px 0; }
</style>

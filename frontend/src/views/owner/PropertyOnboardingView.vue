<script setup lang="ts">
import { useLiveRefresh } from '@/composables/useLiveRefresh'
import { onMounted, ref } from 'vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import { api, prepareCsrf, toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import { invitationLabels, joinLabels, type Invitation, type JoinStatus, type MembershipAccount, type OwnerJoinRequest, type Paged } from '@/types/property-onboarding'

type Section = 'requests' | 'invitations' | 'members'
type Decision = 'approved' | 'rejected'
type Confirmation = { title: string; description: string; method: 'put' | 'delete' | 'rotate'; invitation?: Invitation; url: string; decision?: Decision; result: string }

const section = ref<Section>('requests')
const requestStatus = ref<JoinStatus | 'all'>('pending')
const requests = ref<Paged<OwnerJoinRequest> | null>(null)
const invitations = ref<Paged<Invitation> | null>(null)
const members = ref<MembershipAccount[]>([])
const pendingCount = ref<number | null>(null)
const error = ref('')
const notice = ref('')
const loading = ref(false)
const saving = ref(false)
const days = ref(7)
const maxApprovals = ref(10)
const newCode = ref('')
const confirm = ref<Confirmation | null>(null)
const confirmError = ref('')

async function load(page = 1) {
  loading.value = true
  error.value = ''
  const selected = section.value
  try {
    if (selected === 'requests') {
      const response = await api.get<Paged<OwnerJoinRequest> & { pending_count: number }>('/owner/join-requests', { params: { page, status: requestStatus.value } })
      requests.value = response.data
      pendingCount.value = response.data.pending_count
    } else if (selected === 'invitations') {
      invitations.value = (await api.get<Paged<Invitation>>('/owner/invitations', { params: { page } })).data
    } else {
      members.value = (await api.get<{ data: MembershipAccount[] }>('/properties/members')).data.data
    }
  } catch (e) {
    error.value = toApiError(e).message
  } finally {
    loading.value = false
  }
}

function changeSection(next: Section) {
  if (loading.value || saving.value || next === section.value) return
  section.value = next
  notice.value = ''
  newCode.value = ''
  void load()
}

async function issue(replace?: Invitation) {
  if (saving.value || loading.value) return
  saving.value = true
  error.value = ''
  notice.value = ''
  newCode.value = ''
  try {
    await prepareCsrf()
    const path = replace ? '/owner/invitations/' + replace.id + '/rotate' : '/owner/invitations'
    const response = await api.post<{ data: Invitation & { code: string } }>(path, { days: days.value, max_approvals: maxApprovals.value })
    newCode.value = response.data.data.code
    notice.value = replace ? 'Đã thu hồi mã cũ và tạo mã mới.' : 'Đã tạo mã mời.'
    await load()
  } catch (e) {
    error.value = toApiError(e).message
  } finally {
    saving.value = false
  }
}

async function copyCode() {
  try {
    await navigator.clipboard.writeText(newCode.value)
    notice.value = 'Đã sao chép mã mời.'
  } catch {
    notice.value = 'Hãy chọn và sao chép mã trong ô bên dưới.'
  }
}

function decide(row: OwnerJoinRequest, decision: Decision) {
  const approve = decision === 'approved'
  confirmError.value = ''
  confirm.value = {
    title: approve ? 'Duyệt yêu cầu tham gia?' : 'Từ chối yêu cầu tham gia?',
    description: approve
      ? 'Hãy đối chiếu tên và số điện thoại trực tiếp với người thuê. Sau khi duyệt, người này được vào khu trọ. Bạn có thể liên kết hồ sơ thuê sau.'
      : 'Người này sẽ thấy yêu cầu đã bị từ chối. Tài khoản vẫn có thể sử dụng RentBase.',
    method: 'put', url: '/owner/join-requests/' + row.id, decision,
    result: approve ? 'Đã duyệt thành viên. Mở hồ sơ người thuê để liên kết khi đã đối chiếu.' : 'Đã từ chối yêu cầu.',
  }
}

function revoke(kind: 'invite' | 'member', id: number) {
  confirmError.value = ''
  confirm.value = kind === 'invite'
    ? { title: 'Thu hồi mã mời?', description: 'Các yêu cầu chờ dùng mã này cần nhập mã mới. Thành viên đã được duyệt giữ quyền hiện tại.', method: 'delete', url: '/owner/invitations/' + id, result: 'Đã thu hồi mã mời.' }
    : { title: 'Thu hồi quyền thành viên?', description: 'Người này sẽ mất quyền vào khu và xem hóa đơn của khu. Hồ sơ thuê và lịch sử thu tiền vẫn được lưu.', method: 'delete', url: '/owner/members/' + id, result: 'Đã thu hồi quyền thành viên.' }
}

function confirmRotation(invitation: Invitation) {
  confirmError.value = ''
  confirm.value = {
    title: 'Đổi mã mời?',
    description: 'Mã cũ sẽ bị thu hồi. Các yêu cầu đang chờ cần nhập mã mới. Hạn và số lượt của mã mới lấy từ biểu mẫu phía trên.',
    method: 'rotate', invitation, url: '', result: '',
  }
}

async function submitDecision() {
  const action = confirm.value
  if (!action || saving.value) return
  if (action.method === 'rotate') {
    confirm.value = null
    await issue(action.invitation)
    return
  }
  saving.value = true
  confirmError.value = ''
  notice.value = ''
  try {
    await prepareCsrf()
    if (action.method === 'put') await api.put(action.url, { decision: action.decision })
    else await api.delete(action.url)
    confirm.value = null
    notice.value = action.result
    await load()
  } catch (e) {
    confirmError.value = toApiError(e).message
  } finally {
    saving.value = false
  }
}

onMounted(() => load())
useLiveRefresh('property', () => !loading.value ? load() : false)
</script>

<template>
  <section class="management-page onboarding-page">
    <header class="management-header">
      <div>
        <h1>Thành viên khu trọ</h1>
        <p>Gửi mã mời, duyệt yêu cầu và quản lý ai được vào khu này.</p>
      </div>
      <UiButton variant="secondary" :loading="loading" :disabled="saving" @click="load()">Làm mới</UiButton>
    </header>

    <div class="button-row section-switch" role="group" aria-label="Mục tiếp nhận">
      <UiButton :variant="section === 'requests' ? 'primary' : 'secondary'" :aria-pressed="section === 'requests'" :disabled="loading || saving" @click="changeSection('requests')">
        Yêu cầu tham gia<span v-if="pendingCount"> ({{ pendingCount }})</span>
      </UiButton>
      <UiButton :variant="section === 'invitations' ? 'primary' : 'secondary'" :aria-pressed="section === 'invitations'" :disabled="loading || saving" @click="changeSection('invitations')">Mã mời</UiButton>
      <UiButton :variant="section === 'members' ? 'primary' : 'secondary'" :aria-pressed="section === 'members'" :disabled="loading || saving" @click="changeSection('members')">Thành viên</UiButton>
    </div>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>
    <p v-if="notice" class="action-notice" role="status">{{ notice }}</p>
    <p v-if="loading" role="status" class="help-text">Đang tải…</p>

    <template v-if="section === 'requests'">
      <label class="filter-control">Trạng thái yêu cầu
        <select v-model="requestStatus" class="ui-select" :disabled="loading || saving" @change="load()">
          <option value="pending">Chờ duyệt</option>
          <option value="approved">Đã duyệt</option>
          <option value="rejected">Đã từ chối</option>
          <option value="withdrawn">Đã rút</option>
          <option value="all">Tất cả</option>
        </select>
      </label>
      <p v-if="!loading && !error && !requests?.data.length" class="record-card">Không có yêu cầu ở trạng thái này.</p>
      <div v-else class="management-grid">
        <article v-for="row in requests?.data" :key="row.id" class="record-card form-grid">
          <div class="record-top">
            <h2>{{ row.full_name || row.username }}</h2>
            <UiBadge :tone="row.status === 'pending' ? 'warning' : row.status === 'approved' ? 'success' : 'neutral'">{{ joinLabels[row.status] }}</UiBadge>
          </div>
          <dl class="record-facts">
            <div><dt>Tên đăng nhập</dt><dd>{{ row.username }}</dd></div>
            <div><dt>Số liên hệ</dt><dd>{{ row.contact_phone || 'Chưa có' }}</dd></div>
            <div><dt>Gửi lúc</dt><dd>{{ formatDateTime(row.created_at) }}</dd></div>
          </dl>
          <template v-if="row.status === 'pending'">
            <p v-if="row.invitation_status !== 'active'" class="help-text">{{ invitationLabels[row.invitation_status] }}. Người gửi cần nhập mã mới trước khi được duyệt.</p>
            <div class="button-row">
              <UiButton :disabled="saving || loading || row.invitation_status !== 'active'" @click="decide(row, 'approved')">Duyệt</UiButton>
              <UiButton variant="secondary" :disabled="saving || loading" @click="decide(row, 'rejected')">Từ chối</UiButton>
            </div>
          </template>
          <RouterLink v-else-if="row.status === 'approved' && row.membership_status === 'approved'" :to="row.tenant_id ? '/owner/tenants/' + row.tenant_id : '/owner/tenants'" class="record-link">
            {{ row.tenant_id ? 'Xem hồ sơ đã liên kết' : 'Mở danh sách hồ sơ để liên kết' }} →
          </RouterLink>
        </article>
      </div>
      <OwnerPager v-if="requests && requests.meta.last_page > 1" :page="requests.meta.current_page" :last-page="requests.meta.last_page" :total="requests.meta.total" :loading="loading" @change="load" />
    </template>

    <template v-else-if="section === 'invitations'">
      <form class="record-card invite-controls" @submit.prevent="issue()">
        <label>Hạn sử dụng (ngày)<input v-model.number="days" class="ui-input" type="number" min="1" max="30" required /></label>
        <label>Số lượt duyệt tối đa<input v-model.number="maxApprovals" class="ui-input" type="number" min="1" max="100" required /></label>
        <UiButton type="submit" :loading="saving" :disabled="loading">Tạo mã mời</UiButton>
      </form>
      <section v-if="newCode" class="record-card form-grid code-reveal">
        <h2>Mã mời mới</h2>
        <p class="help-text">Sao chép trước khi rời mục này. Mã đầy đủ chỉ hiển thị lúc tạo.</p>
        <input :value="newCode" class="ui-input" aria-label="Mã mời mới" readonly />
        <UiButton variant="secondary" @click="copyCode">Sao chép mã</UiButton>
      </section>
      <p v-if="!loading && !error && !invitations?.data.length" class="record-card">Chưa có mã mời nào.</p>
      <div v-else class="management-grid">
        <article v-for="invite in invitations?.data" :key="invite.id" class="record-card form-grid">
          <div class="record-top"><h2>Mã kết thúc bằng {{ invite.suffix }}</h2><UiBadge :tone="invite.status === 'active' ? 'success' : 'neutral'">{{ invitationLabels[invite.status] }}</UiBadge></div>
          <dl class="record-facts">
            <div><dt>Đã dùng</dt><dd>{{ invite.approvals }} / {{ invite.max_approvals }} lượt duyệt</dd></div>
            <div><dt>Hết hạn</dt><dd>{{ formatDateTime(invite.expires_at) }}</dd></div>
          </dl>
          <div v-if="invite.status !== 'revoked'" class="button-row">
            <UiButton variant="secondary" :disabled="saving || loading" @click="confirmRotation(invite)">Đổi mã</UiButton>
            <UiButton variant="ghost" :disabled="saving || loading" @click="revoke('invite', invite.id)">Thu hồi mã</UiButton>
          </div>
        </article>
      </div>
      <OwnerPager v-if="invitations && invitations.meta.last_page > 1" :page="invitations.meta.current_page" :last-page="invitations.meta.last_page" :total="invitations.meta.total" :loading="loading" @change="load" />
    </template>

    <template v-else>
      <p v-if="!loading && !error && !members.length" class="record-card">Chưa có thành viên được duyệt.</p>
      <div v-else class="management-grid">
        <article v-for="member in members" :key="member.id" class="record-card form-grid">
          <h2>{{ member.full_name || member.username }}</h2>
          <span class="help-text">{{ member.username }} <template v-if="member.contact_phone">· {{ member.contact_phone }}</template></span>
          <UiBadge :tone="member.tenant_id ? 'success' : 'neutral'">{{ member.tenant_id ? 'Đã liên kết hồ sơ thuê' : 'Chưa liên kết hồ sơ thuê' }}</UiBadge>
          <div class="button-row">
            <RouterLink class="record-link" :to="member.tenant_id ? '/owner/tenants/' + member.tenant_id : '/owner/tenants'">{{ member.tenant_id ? 'Xem hồ sơ' : 'Chọn hồ sơ thuê' }} →</RouterLink>
            <UiButton variant="secondary" :disabled="saving || loading" @click="revoke('member', member.id)">Thu hồi quyền</UiButton>
          </div>
        </article>
      </div>
    </template>

    <UiDialog :open="!!confirm" :title="confirm?.title ?? ''" :description="confirm?.description" @update:open="value => { if (!value && !saving) confirm = null }">
      <p v-if="confirmError" class="form-error" role="alert">{{ confirmError }}</p>
      <template #actions>
        <UiButton variant="secondary" :disabled="saving" @click="confirm = null">Quay lại</UiButton>
        <UiButton :variant="confirm?.method === 'delete' ? 'danger' : 'primary'" :loading="saving" @click="submitDecision">Xác nhận</UiButton>
      </template>
    </UiDialog>
  </section>
</template>

<style scoped>
.onboarding-page { display: grid; gap: 18px; }
.onboarding-page h2 { margin: 0; overflow-wrap: anywhere; }
.record-top, .invite-controls { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; }
.invite-controls { align-items: end; justify-content: start; }
.filter-control, .invite-controls label { display: grid; gap: 6px; max-width: 270px; }
.action-notice { margin: 0; padding: 12px 16px; background: var(--color-primary-soft); border-radius: var(--radius-control); }
.code-reveal input { max-width: 100%; font-family: monospace; }
.code-reveal button { justify-self: start; }
</style>

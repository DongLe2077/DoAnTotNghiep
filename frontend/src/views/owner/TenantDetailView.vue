<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import TenantEditor from '@/components/owner/TenantEditor.vue'
import { useOwnerApi, useOwnerList } from '@/composables/useOwnerApi'
import { toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import type { Stay, Tenant } from '@/types/owner'
const route = useRoute(), router = useRouter(), id = Number(route.params.id)
const { get, write, busy, error, errors, clearError } = useOwnerApi()
const { items: stays, loading: historyLoading, loadError: historyError, page, lastPage, total, load: loadStays } = useOwnerList<Stay>(`/tenants/${id}/stays`)
const tenant = ref<Tenant | null>(null), loading = ref(true), loadError = ref(''), edit = ref(false), accountOpen = ref(false), deleteOpen = ref(false), notice = ref('')
const account = reactive({ username: '', password: '', password_confirmation: '', is_active: true })
async function load() {
  tenant.value = null; loading.value = true; loadError.value = ''
  try { tenant.value = (await get<{ data: Tenant }>(`/tenants/${id}`)).data }
  catch (caught) { loadError.value = toApiError(caught).message }
  finally { loading.value = false }
}
function editAccount() { clearError(); Object.assign(account, { username: tenant.value?.account?.username ?? '', password: '', password_confirmation: '', is_active: tenant.value?.account?.is_active ?? true }); accountOpen.value = true }
function closeAccount() { if (!busy.value) { account.password = ''; account.password_confirmation = ''; accountOpen.value = false } }
async function saveAccount() {
  try {
    await write('put', `/tenants/${id}/account`, { username: account.username, is_active: account.is_active, ...(account.password || !tenant.value?.account ? { password: account.password, password_confirmation: account.password_confirmation } : {}) })
    closeAccount(); notice.value = 'Đã lưu tài khoản người thuê.'; await load()
  } catch { /* Server errors appear beside fields. */ }
}
async function remove() {
  try { await write('delete', `/tenants/${id}`); await router.replace('/owner/tenants') } catch { /* Show backend conflict. */ }
}
function saved(value: Tenant) { tenant.value = value; edit.value = false; notice.value = 'Đã cập nhật hồ sơ.' }
void load(); void loadStays()
</script>
<template><section class="management-page"><RouterLink class="back-link" to="/owner/tenants">← Danh sách người thuê</RouterLink><UiPageState v-if="loading" kind="loading" /><UiPageState v-else-if="loadError" kind="error" :description="loadError"><UiButton @click="load">Thử lại</UiButton></UiPageState><template v-else-if="tenant"><header class="management-header"><div><span class="eyebrow">Hồ sơ người thuê</span><h1>{{ tenant.full_name }}</h1><p>{{ tenant.current_stay ? `Đang ở phòng ${tenant.current_stay.room?.room_number ?? ''}` : 'Hiện chưa cư trú trong phòng nào' }}</p></div><div class="button-row"><UiButton @click="edit = true">Sửa hồ sơ</UiButton><UiButton v-if="tenant.can_delete" variant="danger" @click="clearError(); deleteOpen = true">Xóa hồ sơ</UiButton></div></header><UiNotice v-if="notice" tone="success">{{ notice }}</UiNotice><div class="detail-sections"><section class="record-card"><h2>Thông tin cá nhân</h2><dl class="record-facts"><div><dt>Họ tên</dt><dd>{{ tenant.full_name }}</dd></div><div><dt>Số điện thoại</dt><dd>{{ tenant.phone_number }}</dd></div><div><dt>Số giấy tờ</dt><dd>{{ tenant.identity_number ?? 'Chưa cung cấp' }}</dd></div><div><dt>Số lần cư trú</dt><dd>{{ tenant.history_count }}</dd></div></dl><RouterLink v-if="tenant.current_stay" class="record-link" :to="`/owner/stays/${tenant.current_stay.occupancy_id}`">Xem đợt thuê hiện tại</RouterLink></section><section class="record-card"><h2>Tài khoản đăng nhập</h2><template v-if="tenant.account"><UiBadge :tone="tenant.account.is_active ? 'success' : 'warning'">{{ tenant.account.is_active ? 'Đang hoạt động' : 'Đã khóa' }}</UiBadge><p>{{ tenant.account.username }}</p></template><p v-else class="help-text">Hồ sơ này chưa có tài khoản. Người thuê vẫn có thể được nhận phòng.</p><UiButton variant="secondary" @click="editAccount">{{ tenant.account ? 'Quản lý tài khoản' : 'Tạo tài khoản' }}</UiButton><p class="help-text">Khóa tài khoản không xóa hồ sơ hoặc lịch sử. Nếu đang là đại diện, cần đổi đại diện trước khi khóa.</p></section><section class="record-card wide"><h2>Lịch sử cư trú</h2><UiPageState v-if="historyLoading" kind="loading" /><UiPageState v-else-if="historyError" kind="error" :description="historyError"><UiButton @click="loadStays()">Thử lại</UiButton></UiPageState><p v-else-if="!stays.length" class="help-text">Chưa có lịch sử cư trú.</p><ul v-else class="detail-items"><li v-for="stay in stays" :key="stay.id"><div class="button-row"><strong>Phòng {{ stay.room?.room_number }}</strong><UiBadge :tone="stay.is_active ? 'success' : 'neutral'">{{ stay.is_active ? 'Đang ở' : 'Đã rời' }}</UiBadge></div><p class="help-text">{{ formatDateTime(stay.moved_in_at) }} → {{ stay.moved_out_at ? formatDateTime(stay.moved_out_at) : 'Hiện tại' }}</p><RouterLink class="record-link" :to="`/owner/stays/${stay.occupancy_id}`">Xem đợt thuê</RouterLink></li></ul><OwnerPager v-if="total > 0" :page="page" :last-page="lastPage" :total="total" :loading="historyLoading" @change="loadStays($event)" /></section></div><TenantEditor :open="edit" :tenant="tenant" @close="edit = false" @saved="saved" /><UiDialog :open="accountOpen" :title="tenant.account ? 'Quản lý tài khoản người thuê' : 'Tạo tài khoản người thuê'" @update:open="value => { if (!value) closeAccount() }"><form class="form-grid" @submit.prevent="saveAccount"><p v-if="error" class="form-error" role="alert">{{ error }}</p><fieldset class="form-grid" :disabled="busy"><OwnerField v-model="account.username" label="Tên đăng nhập" name="username" :errors="errors" :max-length="80" required /><OwnerField v-model="account.password" label="Mật khẩu mới" name="password" :errors="errors" type="password" :max-length="72" :required="!tenant.account" :hint="tenant.account ? 'Để trống nếu giữ mật khẩu hiện tại. Mật khẩu mới từ 8 ký tự.' : 'Mật khẩu từ 8 ký tự, tối đa 72 byte.'" /><OwnerField v-model="account.password_confirmation" label="Xác nhận mật khẩu mới" name="password_confirmation" :errors="errors" type="password" :max-length="72" :required="!!account.password || !tenant.account" /><label><span><input v-model="account.is_active" type="checkbox" /> Cho phép đăng nhập</span></label></fieldset><UiButton type="submit" :loading="busy">Lưu tài khoản</UiButton></form><template #actions><UiButton variant="secondary" :disabled="busy" @click="closeAccount">Đóng</UiButton></template></UiDialog><UiDialog :open="deleteOpen" title="Xóa hồ sơ người thuê" @update:open="value => { if (!busy) deleteOpen = value }"><p>Chỉ xóa hồ sơ chưa có tài khoản hoặc lịch sử cư trú. Bạn muốn xóa hồ sơ {{ tenant.full_name }}?</p><p v-if="error" class="form-error" role="alert">{{ error }}</p><template #actions><UiButton variant="secondary" :disabled="busy" @click="deleteOpen = false">Giữ hồ sơ</UiButton><UiButton variant="danger" :loading="busy" @click="remove">Xác nhận xóa hồ sơ</UiButton></template></UiDialog></template></section></template>

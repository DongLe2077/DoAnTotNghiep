<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerField from './OwnerField.vue'
import OwnerPager from './OwnerPager.vue'
import { useOwnerApi, useOwnerList } from '@/composables/useOwnerApi'
import { formatMoney } from '@/lib/format'
import { formatUnitPrice } from '@/lib/unit-price'
import { toApiError } from '@/lib/api'
import type { Room, Service } from '@/types/owner'
const props = defineProps<{ kind: 'rooms' | 'services' }>()
const isRoom = props.kind === 'rooms', path = `/${props.kind}`
const { get, write, busy, error, errors, clearError } = useOwnerApi()
const { items, loading, loadError, page, lastPage, total, load } = useOwnerList<Room | Service>(path)
const rooms = computed(() => isRoom ? items.value as Room[] : [])
const services = computed(() => !isRoom ? items.value as Service[] : [])
const search = ref(''), status = ref(''), method = ref(''), notice = ref(''), openError = ref('')
const mode = ref<'' | 'create' | 'detail' | 'edit' | 'delete' | 'rate'>(''), opening = ref(false), review = ref(false)
const room = ref<Room | null>(null), service = ref<Service | null>(null)
const form = reactive({ room_number: '', capacity: '1', default_monthly_rent: '', operational_status: 'available', service_code: '', name: '', unit: '', billing_method: 'per_room', default_unit_price: '', is_active: true })
const title = computed(() => mode.value === 'rate' ? 'Giá thuê mặc định chung' : mode.value === 'delete' ? `Xóa ${isRoom ? 'phòng' : 'dịch vụ'}` : `${mode.value === 'create' ? 'Thêm' : mode.value === 'edit' ? 'Sửa' : 'Chi tiết'} ${isRoom ? 'phòng' : 'dịch vụ'}`)
function filter() { void load(1, { q: search.value, ...(status.value ? { [isRoom ? 'occupancy_status' : 'is_active']: status.value } : {}), ...(!isRoom && method.value ? { billing_method: method.value } : {}) }) }
function create() {
  openSequence++; opening.value = false
  clearError(); openError.value = ''; room.value = null; service.value = null; review.value = false
  Object.assign(form, { room_number: '', capacity: '1', default_monthly_rent: '', operational_status: 'available', service_code: '', name: '', unit: '', billing_method: 'per_room', default_unit_price: '', is_active: true })
  mode.value = 'create'
}
let openSequence = 0
async function detail(id: number) {
  const sequence = ++openSequence
  opening.value = true; openError.value = ''; clearError(); room.value = null; service.value = null; mode.value = 'detail'
  try {
    const result = await get<{ data: Room | Service }>(`${path}/${id}`)
    if (sequence !== openSequence || !mode.value) return
    if (isRoom) room.value = result.data as Room
    else service.value = result.data as Service
  } catch (caught) { if (sequence === openSequence) openError.value = toApiError(caught).message }
  finally { if (sequence === openSequence) opening.value = false }
}
function edit() {
  clearError()
  if (room.value) Object.assign(form, room.value, { capacity: String(room.value.capacity) })
  if (service.value) Object.assign(form, service.value)
  mode.value = 'edit'
}
async function rate() {
  const sequence = ++openSequence
  mode.value = 'rate'; review.value = false; opening.value = true; clearError(); openError.value = ''
  try {
    const result = await get<{ data: { default_monthly_rent: string | null; room_count: number } }>('/rooms/rental-rate')
    if (sequence !== openSequence || mode.value !== 'rate') return
    form.default_monthly_rent = result.data.default_monthly_rent ?? ''
    if (!result.data.room_count) openError.value = 'Hãy tạo phòng đầu tiên kèm giá thuê mặc định trước.'
  } catch (caught) { if (sequence === openSequence) openError.value = toApiError(caught).message }
  finally { if (sequence === openSequence) opening.value = false }
}
function close() { if (!busy.value) { mode.value = ''; openSequence++; opening.value = false; clearError() } }
async function save() {
  if (busy.value) return
  if (mode.value === 'rate' && !review.value) { review.value = true; return }
  try {
    if (mode.value === 'delete') await write('delete', `${path}/${(room.value ?? service.value)!.id}`)
    else if (mode.value === 'rate') await write('put', '/rooms/rental-rate', { default_monthly_rent: form.default_monthly_rent })
    else {
      const payload = isRoom ? { room_number: form.room_number, capacity: Number(form.capacity), operational_status: form.operational_status, ...(mode.value === 'create' && form.default_monthly_rent ? { default_monthly_rent: form.default_monthly_rent } : {}) }
        : { service_code: form.service_code, name: form.name, unit: form.unit, billing_method: form.billing_method, default_unit_price: form.default_unit_price, is_active: form.is_active }
      await write(mode.value === 'create' ? 'post' : 'put', mode.value === 'create' ? path : `${path}/${(room.value ?? service.value)!.id}`, payload)
    }
    notice.value = mode.value === 'delete' ? 'Đã xóa mục chưa có lịch sử.' : mode.value === 'rate' ? 'Đã cập nhật giá mặc định. Giá của các đợt thuê hiện có được giữ nguyên.' : 'Đã lưu thông tin.'
    close(); await load()
  } catch { /* The form keeps its values and displays server field errors. */ }
}
void load()
</script>
<template>
  <section class="management-page">
    <header class="management-header"><div><span class="eyebrow">Không gian chủ trọ</span><h1>{{ isRoom ? 'Quản lý phòng' : 'Dịch vụ' }}</h1><p>{{ isRoom ? 'Theo dõi sức chứa, người đang ở và tình trạng từng phòng.' : 'Danh mục dịch vụ và mức giá mặc định cho lần áp dụng mới.' }}</p></div><div class="button-row"><UiButton v-if="isRoom" variant="secondary" @click="rate">Giá thuê chung</UiButton><UiButton @click="create">{{ isRoom ? 'Thêm phòng' : 'Thêm dịch vụ' }}</UiButton></div></header>
    <UiNotice v-if="notice" tone="success">{{ notice }}</UiNotice>
    <form class="management-toolbar" @submit.prevent="filter"><label>{{ isRoom ? 'Tìm số phòng' : 'Tìm tên hoặc mã dịch vụ' }}<input v-model="search" class="ui-input" maxlength="100" /></label><label>Trạng thái<select v-model="status" class="ui-select"><option value="">Tất cả</option><template v-if="isRoom"><option value="vacant">Phòng trống</option><option value="occupied">Đang có người ở</option><option value="maintenance">Bảo trì</option></template><template v-else><option value="1">Đang áp dụng mới</option><option value="0">Đã tắt</option></template></select></label><label v-if="!isRoom">Cách tính<select v-model="method" class="ui-select"><option value="">Tất cả</option><option value="metered">Theo chỉ số</option><option value="per_room">Theo phòng</option></select></label><UiButton type="submit" variant="secondary" :loading="loading">Tìm và lọc</UiButton></form>
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="loadError" kind="error" :description="loadError"><UiButton @click="load()">Thử lại</UiButton></UiPageState>
    <UiPageState v-else-if="!items.length" title="Chưa có kết quả phù hợp" description="Bạn có thể thay đổi bộ lọc hoặc thêm mới." />
    <div v-else class="management-grid">
      <article v-for="item in rooms" :key="item.id" class="record-card"><header><h2>Phòng {{ item.room_number }}</h2><UiBadge :tone="item.occupancy_status === 'vacant' ? 'success' : item.occupancy_status === 'maintenance' ? 'warning' : 'info'">{{ { vacant: 'Phòng trống', occupied: 'Có người ở', maintenance: 'Bảo trì' }[item.occupancy_status] }}</UiBadge></header><dl class="record-facts"><div><dt>Người ở / Sức chứa</dt><dd>{{ item.resident_count }} / {{ item.capacity }} người</dd></div><div><dt>Giá mặc định / tháng</dt><dd class="ui-money">{{ formatMoney(item.default_monthly_rent) }}</dd></div></dl><footer><UiButton variant="secondary" @click="detail(item.id)">Xem phòng {{ item.room_number }}</UiButton><RouterLink class="record-link" :to="{ path: '/owner/stays', query: { room_id: item.id } }">Lịch sử thuê</RouterLink></footer></article>
      <article v-for="item in services" :key="item.id" class="record-card"><header><div><h2>{{ item.name }}</h2><span class="help-text">{{ item.service_code }}</span></div><UiBadge :tone="item.is_active ? 'success' : 'neutral'">{{ item.is_active ? 'Đang áp dụng mới' : 'Đã tắt' }}</UiBadge></header><dl class="record-facts"><div><dt>Cách tính</dt><dd>{{ item.billing_method === 'metered' ? 'Theo chỉ số' : 'Theo phòng' }}</dd></div><div><dt>Đơn giá mặc định</dt><dd>{{ formatUnitPrice(item.default_unit_price) }} / {{ item.unit }}</dd></div></dl><footer><UiButton variant="secondary" @click="detail(item.id)">Xem dịch vụ {{ item.name }}</UiButton></footer></article>
    </div>
    <OwnerPager v-if="!loadError && total > 0" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" />
    <p v-if="!isRoom" class="help-text">Đổi giá hoặc tắt danh mục không thay đổi những khoảng dịch vụ đã chốt cho người thuê.</p>
    <UiDialog :open="!!mode" :title="title" @update:open="value => { if (!value) close() }">
      <UiPageState v-if="opening" kind="loading" />
      <UiPageState v-else-if="openError" kind="error" :description="openError" />
      <template v-else-if="mode === 'detail'">
        <dl v-if="room" class="record-facts"><div><dt>Số phòng</dt><dd>{{ room.room_number }}</dd></div><div><dt>Sức chứa</dt><dd>{{ room.capacity }} người</dd></div><div><dt>Người đang ở</dt><dd>{{ room.resident_count }} người</dd></div><div><dt>Giá mặc định / tháng</dt><dd>{{ formatMoney(room.default_monthly_rent) }}</dd></div><div><dt>Vận hành</dt><dd>{{ room.operational_status === 'available' ? 'Sẵn sàng sử dụng' : 'Bảo trì' }}</dd></div></dl>
        <dl v-if="service" class="record-facts"><div><dt>Tên dịch vụ</dt><dd>{{ service.name }}</dd></div><div><dt>Mã</dt><dd>{{ service.service_code }}</dd></div><div><dt>Đơn vị</dt><dd>{{ service.unit }}</dd></div><div><dt>Cách tính</dt><dd>{{ service.billing_method === 'metered' ? 'Theo chỉ số' : 'Theo phòng' }}</dd></div><div><dt>Giá mặc định</dt><dd>{{ formatUnitPrice(service.default_unit_price) }}</dd></div><div><dt>Áp dụng mới</dt><dd>{{ service.is_active ? 'Đang bật' : 'Đã tắt' }}</dd></div></dl>
        <div class="button-row"><UiButton @click="edit">Sửa thông tin</UiButton><UiButton v-if="(room ?? service)?.can_delete" variant="danger" @click="mode = 'delete'; clearError()">Xóa</UiButton></div><p v-if="!(room ?? service)?.can_delete" class="help-text">Mục đã có lịch sử sử dụng nên được giữ lại và không thể xóa.</p>
      </template>
      <form v-else class="form-grid" @submit.prevent="save">
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
        <template v-if="mode === 'delete'"><p>Bạn muốn xóa {{ room ? `phòng ${room.room_number}` : service?.name }}? Chỉ xóa được mục chưa có lịch sử sử dụng.</p></template>
        <template v-else-if="mode === 'rate'">
          <OwnerField v-if="!review" v-model="form.default_monthly_rent" label="Giá thuê mặc định (VNĐ/tháng)" name="default_monthly_rent" :errors="errors" required hint="Nhập số không có dấu phân cách, tối đa 2 chữ số thập phân." />
          <div v-else class="review-box"><h3>Xem lại giá thuê chung</h3><p>Giá mới: <strong>{{ formatMoney(form.default_monthly_rent) }} / tháng</strong></p><p>Áp dụng mặc định cho tất cả phòng. Giá của các đợt thuê đã nhận được giữ nguyên.</p></div>
          <p v-if="review && errors.default_monthly_rent" class="field-error">{{ errors.default_monthly_rent[0] }}</p>
        </template>
        <fieldset v-else :disabled="busy" class="form-grid">
          <template v-if="isRoom"><OwnerField v-model="form.room_number" label="Số phòng" name="room_number" :errors="errors" :max-length="30" required /><OwnerField v-model="form.capacity" label="Sức chứa (người)" name="capacity" :errors="errors" type="number" min="1" max="65535" step="1" required /><OwnerField v-if="mode === 'create'" v-model="form.default_monthly_rent" label="Giá thuê mặc định (VNĐ/tháng)" name="default_monthly_rent" :errors="errors" hint="Phòng đầu tiên cần giá. Những phòng sau có thể để trống để dùng giá chung." /><label>Vận hành<select v-model="form.operational_status" class="ui-select"><option value="available">Sẵn sàng sử dụng</option><option value="maintenance">Bảo trì</option></select><span v-if="errors.operational_status" class="field-error">{{ errors.operational_status[0] }}</span></label><p class="help-text">Bảo trì không tự chuyển người thuê đi hoặc kết thúc đợt thuê.</p></template>
          <template v-else><OwnerField v-model="form.service_code" label="Mã dịch vụ" name="service_code" :errors="errors" :max-length="40" required /><OwnerField v-model="form.name" label="Tên dịch vụ" name="name" :errors="errors" :max-length="100" required /><OwnerField v-model="form.unit" label="Đơn vị tính" name="unit" :errors="errors" :max-length="30" required hint="Ví dụ: kWh, m³, phòng/tháng" /><label>Cách tính<select v-model="form.billing_method" class="ui-select"><option value="per_room">Theo phòng</option><option value="metered">Theo chỉ số</option></select><span v-if="errors.billing_method" class="field-error">{{ errors.billing_method[0] }}</span></label><OwnerField v-model="form.default_unit_price" label="Đơn giá mặc định (VNĐ)" name="default_unit_price" :errors="errors" required hint="Nhập số không có dấu phân cách, tối đa 4 chữ số thập phân." /><label><span><input v-model="form.is_active" type="checkbox" /> Cho phép áp dụng mới</span></label></template>
        </fieldset>
        <div class="button-row"><UiButton v-if="review" variant="secondary" :disabled="busy" @click="review = false">Chỉnh lại</UiButton><UiButton type="submit" :variant="mode === 'delete' ? 'danger' : 'primary'" :loading="busy">{{ mode === 'delete' ? 'Xác nhận xóa' : mode === 'rate' && !review ? 'Xem lại thay đổi' : 'Lưu thông tin' }}</UiButton></div>
      </form>
      <template #actions><UiButton variant="secondary" :disabled="busy" @click="close">Đóng</UiButton></template>
    </UiDialog>
  </section>
</template>

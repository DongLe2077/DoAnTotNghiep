<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { keepFocusInDialog } from '@/lib/dialog'

type RoomStatus = 'occupied' | 'available' | 'maintenance'
type Room = {
  code: string
  floor: string
  residents: number
  capacity: number
  area: number
  rent: string
  status: RoomStatus
}

const rooms: Room[] = [
  { code: 'A101', floor: 'Tầng 1', residents: 2, capacity: 3, area: 24, rent: '3.200.000', status: 'occupied' },
  { code: 'A102', floor: 'Tầng 1', residents: 1, capacity: 2, area: 20, rent: '2.800.000', status: 'occupied' },
  { code: 'A201', floor: 'Tầng 2', residents: 0, capacity: 2, area: 20, rent: '2.800.000', status: 'available' },
  { code: 'A202', floor: 'Tầng 2', residents: 0, capacity: 3, area: 24, rent: '3.200.000', status: 'maintenance' },
]
const statusLabels: Record<RoomStatus, string> = {
  occupied: 'Đang có người',
  available: 'Còn trống',
  maintenance: 'Đang sửa chữa',
}
const search = ref('')
const status = ref<RoomStatus | ''>('')
const visibleRooms = computed(() => {
  const query = search.value.trim().toLocaleLowerCase('vi')
  return rooms.filter(room => (!status.value || room.status === status.value)
    && (!query || `${room.code} ${room.floor}`.toLocaleLowerCase('vi').includes(query)))
})
const detailDialog = ref<HTMLDialogElement | null>(null)
const addDialog = ref<HTMLDialogElement | null>(null)
const roomNameInput = ref<HTMLInputElement | null>(null)
const selectedRoom = ref<Room | null>(null)
const form = ref({ code: '', capacity: '2', rent: '' })
const formChecked = ref(false)

function resetFilters() {
  search.value = ''
  status.value = ''
}

async function showDetails(room: Room) {
  selectedRoom.value = room
  await nextTick()
  detailDialog.value?.showModal()
}

async function showAddRoom() {
  form.value = { code: '', capacity: '2', rent: '' }
  formChecked.value = false
  addDialog.value?.showModal()
  await nextTick()
  roomNameInput.value?.focus()
}
</script>

<template>
  <section class="rooms-preview" aria-labelledby="rooms-preview-title">
    <header class="rooms-heading">
      <div>
        <p class="section-eyebrow">Không gian của bạn</p>
        <h2 id="rooms-preview-title">Quản lý phòng</h2>
        <p class="heading-description">Nắm rõ tình trạng từng phòng, dễ dàng sắp xếp việc cho thuê.</p>
      </div>
      <button class="room-button room-button-primary" type="button" @click="showAddRoom">
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" /></svg>
        Thêm phòng
      </button>
    </header>

    <dl class="room-summary" aria-label="Tổng hợp phòng minh họa">
      <div class="room-summary-item"><dt>Tổng số phòng</dt><dd>4 <span>phòng</span><p>Không gian được quản lý</p></dd></div>
      <div class="room-summary-item"><dt><span class="status-dot occupied" />Đang có người</dt><dd>2 <span>phòng</span><p>3 người đang lưu trú</p></dd></div>
      <div class="room-summary-item"><dt><span class="status-dot available" />Còn trống</dt><dd>1 <span>phòng</span><p>Sẵn sàng nhận người thuê</p></dd></div>
      <div class="room-summary-item"><dt><span class="status-dot maintenance" />Đang sửa chữa</dt><dd>1 <span>phòng</span><p>Cần hoàn tất trước khi cho thuê</p></dd></div>
    </dl>

    <div class="room-list-panel">
      <div class="room-list-heading"><h3>Danh sách phòng <span>04</span></h3><p>Dữ liệu minh họa</p></div>
      <form class="room-filters" role="search" @submit.prevent>
        <div class="room-search">
          <label class="room-sr-only" for="preview-room-search">Tìm phòng theo tên hoặc tầng</label>
          <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.7" /><path d="m16 16 4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg>
          <input id="preview-room-search" v-model="search" type="search" placeholder="Tìm tên phòng, tầng…" autocomplete="off" />
        </div>
        <div class="room-status-filter"><label class="room-sr-only" for="preview-room-status">Trạng thái phòng</label><select id="preview-room-status" v-model="status"><option value="">Tất cả trạng thái</option><option value="occupied">Đang có người</option><option value="available">Còn trống</option><option value="maintenance">Đang sửa chữa</option></select></div>
        <button v-if="search || status" class="room-reset" type="button" @click="resetFilters">Xóa bộ lọc</button>
      </form>

      <p class="room-sr-only" role="status" aria-live="polite">Tìm thấy {{ visibleRooms.length }} phòng.</p>
      <table v-if="visibleRooms.length" class="room-table">
        <caption class="room-sr-only">Danh sách phòng minh họa, giá thuê tính cho toàn phòng mỗi tháng</caption>
        <thead><tr><th scope="col">Phòng</th><th scope="col">Người đang ở</th><th scope="col">Giá thuê / tháng</th><th scope="col">Trạng thái</th><th scope="col"><span class="room-sr-only">Thao tác</span></th></tr></thead>
        <tbody>
          <tr v-for="room in visibleRooms" :key="room.code">
            <th scope="row"><div class="room-name-cell"><span class="room-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M5 21h14M7 21V4.5L17 3v18M10 12h.01M11 21v-5h3v5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg></span><span><strong>Phòng {{ room.code }}</strong><small>{{ room.floor }} · {{ room.area }} m²</small></span></div></th>
            <td data-label="Người đang ở"><span class="room-residents">{{ room.residents }}<span> / {{ room.capacity }} người</span></span></td>
            <td data-label="Giá thuê / tháng"><span class="room-price">{{ room.rent }} <span>₫</span></span></td>
            <td data-label="Trạng thái"><span class="room-status" :class="room.status"><span class="status-dot" :class="room.status" />{{ statusLabels[room.status] }}</span></td>
            <td class="room-action"><button class="room-detail-link" type="button" :aria-label="`Xem chi tiết phòng ${room.code}`" @click="showDetails(room)">Xem chi tiết<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg></button></td>
          </tr>
        </tbody>
      </table>
      <div v-else class="room-empty"><span class="empty-symbol" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none"><circle cx="10.5" cy="10.5" r="6.5" stroke="currentColor" stroke-width="1.5" /><path d="m16 16 4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" /></svg></span><h4>Chưa tìm thấy phòng phù hợp</h4><p>Thử tên phòng khác hoặc bỏ bộ lọc trạng thái.</p><button type="button" class="room-button room-button-secondary" @click="resetFilters">Xem tất cả phòng</button></div>
      <div v-if="visibleRooms.length" class="room-list-footer">Hiển thị {{ visibleRooms.length }} trong {{ rooms.length }} phòng</div>
    </div>

    <dialog ref="detailDialog" class="room-dialog" aria-labelledby="room-detail-title" @keydown="keepFocusInDialog">
      <template v-if="selectedRoom">
        <div class="room-dialog-heading"><div><p class="section-eyebrow">Thông tin phòng · Minh họa</p><h3 id="room-detail-title">Phòng {{ selectedRoom.code }}</h3></div><button type="button" class="room-close" aria-label="Đóng chi tiết phòng" @click="detailDialog?.close()"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg></button></div>
        <span class="room-status" :class="selectedRoom.status"><span class="status-dot" :class="selectedRoom.status" />{{ statusLabels[selectedRoom.status] }}</span>
        <dl class="room-detail-grid"><div><dt>Vị trí</dt><dd>{{ selectedRoom.floor }}</dd></div><div><dt>Diện tích</dt><dd>{{ selectedRoom.area }} m²</dd></div><div><dt>Người đang ở</dt><dd>{{ selectedRoom.residents }} / {{ selectedRoom.capacity }} người</dd></div><div><dt>Giá thuê toàn phòng / tháng</dt><dd>{{ selectedRoom.rent }} ₫</dd></div></dl>
        <p class="room-dialog-note">Đây là dữ liệu minh họa để xem trước bố cục. Thông tin phòng thực tế sẽ được kết nối ở bước tiếp theo.</p>
        <div class="room-dialog-actions"><button type="button" class="room-button room-button-primary" @click="detailDialog?.close()">Đóng chi tiết</button></div>
      </template>
    </dialog>

    <dialog ref="addDialog" class="room-dialog" aria-labelledby="room-add-title" @keydown="keepFocusInDialog">
      <div class="room-dialog-heading"><div><p class="section-eyebrow">Biểu mẫu minh họa</p><h3 id="room-add-title">Thêm phòng</h3></div><button type="button" class="room-close" aria-label="Đóng biểu mẫu thêm phòng" @click="addDialog?.close()"><svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" /></svg></button></div>
      <p class="room-dialog-note">Bạn có thể thử nhập thông tin. Bản mẫu này chưa lưu hoặc thay đổi dữ liệu.</p>
      <form class="room-add-form" @submit.prevent="formChecked = true" @input="formChecked = false">
        <label for="preview-new-room-name">Tên phòng <span aria-hidden="true">*</span><input id="preview-new-room-name" ref="roomNameInput" v-model="form.code" required maxlength="30" placeholder="Ví dụ: A203" /></label>
        <div class="room-form-columns"><label for="preview-new-room-capacity">Số người tối đa <span aria-hidden="true">*</span><input id="preview-new-room-capacity" v-model="form.capacity" type="number" min="1" max="30" step="1" required /></label><label for="preview-new-room-rent">Giá thuê / tháng (VNĐ) <span aria-hidden="true">*</span><input id="preview-new-room-rent" v-model="form.rent" type="number" min="0" step="1000" required placeholder="2.800.000" /></label></div>
        <p v-if="formChecked" class="room-form-feedback" role="status">Biểu mẫu đã được kiểm tra. Chưa có phòng nào được tạo vì đây là giao diện minh họa.</p>
        <div class="room-dialog-actions"><button type="button" class="room-button room-button-secondary" @click="addDialog?.close()">Đóng</button><button type="submit" class="room-button room-button-primary">Kiểm tra biểu mẫu</button></div>
      </form>
    </dialog>
  </section>
</template>

<style scoped>
.rooms-preview{padding:28px;background:var(--color-bg,#f7f8f5);color:var(--color-text,#233b32);border-radius:16px;min-width:0}
.rooms-heading{display:flex;align-items:center;justify-content:space-between;gap:24px;margin-bottom:25px}.section-eyebrow{font-size:12px;font-weight:600;color:var(--color-text-secondary,#53665c);margin:0 0 6px}.rooms-heading h2{font-size:26px;letter-spacing:-.7px;line-height:1.3;margin:0;font-weight:650}.heading-description{font-size:14px;color:var(--color-text-secondary,#53665c);margin:8px 0 0;max-width:540px}.room-button{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:10px 16px;border:1px solid transparent;border-radius:9px;font-size:14px;font-weight:600;text-decoration:none;white-space:nowrap;transition:background .15s}.room-button-primary{color:#fff;background:var(--color-primary,#235c4b)}.room-button-primary:hover{background:var(--color-primary-hover,#194a3c)}.room-button-secondary{color:var(--color-text,#233b32);border-color:var(--color-border,#dce4dc);background:var(--color-surface,#fff)}.room-button-secondary:hover{background:var(--color-primary-soft,#edf4ee)}
.room-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:0 0 24px}.room-summary-item{background:var(--color-surface,#fff);border:1px solid var(--color-border,#dce4dc);border-radius:12px;padding:18px}.room-summary dt{display:flex;align-items:center;gap:7px;font-size:13px;color:var(--color-text-secondary,#53665c);font-weight:500}.room-summary dd{margin:12px 0 4px;font-size:30px;line-height:1.25;font-weight:650;letter-spacing:-.6px}.room-summary dd span{font-size:13px;font-weight:400;letter-spacing:0;color:var(--color-text-secondary,#53665c);margin-left:3px}.room-summary p{margin:4px 0 0;font-size:12px;font-weight:400;letter-spacing:0;line-height:1.6;color:var(--color-text-muted,#66766b)}.status-dot{display:inline-block;flex-shrink:0;width:7px;height:7px;border-radius:50%;background:currentColor}.status-dot.occupied{background:#2e6a54}.status-dot.available{background:#6b8172}.status-dot.maintenance{background:#9c6b21}
.room-list-panel{border:1px solid var(--color-border,#dce4dc);border-radius:12px;background:var(--color-surface,#fff);overflow:hidden}.room-list-heading{padding:20px 20px 0;display:flex;gap:12px;align-items:center;justify-content:space-between}.room-list-heading h3{font-size:16px;font-weight:600;margin:0}.room-list-heading h3 span{display:inline-block;padding:1px 7px;margin-left:7px;font-size:12px;font-weight:500;background:var(--color-bg,#f7f8f5);border:1px solid var(--color-border,#dce4dc);border-radius:5px;vertical-align:middle}.room-list-heading p{font-size:12px;color:var(--color-text-muted,#66766b);margin:0}.room-filters{display:flex;align-items:center;flex-wrap:wrap;gap:12px;padding:18px 20px 20px}.room-search{position:relative;flex:1;min-width:190px;max-width:370px}.room-search svg{position:absolute;left:13px;top:13px;color:var(--color-text-muted,#66766b);pointer-events:none}.room-search input,.room-status-filter select,.room-add-form input{height:46px;background:var(--color-surface,#fff);border:1px solid var(--color-border,#dce4dc);border-radius:8px;color:var(--color-text,#233b32);font-size:14px;width:100%}.room-search input{padding:10px 12px 10px 40px}.room-search input::placeholder,.room-add-form input::placeholder{color:var(--color-text-muted,#66766b)}.room-status-filter select{padding:9px 34px 9px 12px}.room-reset{min-height:44px;border:0;background:none;color:var(--color-primary,#235c4b);font-size:13px;font-weight:600;padding:8px}.room-reset:hover{text-decoration:underline}.room-table{border-collapse:collapse;width:100%;text-align:left;font-size:14px}.room-table thead{background:var(--color-bg,#f7f8f5);color:var(--color-text-secondary,#53665c);font-size:12px}.room-table th,.room-table td{padding:16px 18px;border-bottom:1px solid var(--color-border,#dce4dc)}.room-table thead th{padding-top:12px;padding-bottom:12px;font-weight:500}.room-table tbody th{font-weight:400}.room-table tbody tr:last-child th,.room-table tbody tr:last-child td{border-bottom:0}.room-name-cell{display:flex;align-items:center;gap:11px}.room-icon{width:39px;height:43px;border:1px solid var(--color-border,#dce4dc);border-radius:8px;display:grid;place-items:center;background:var(--color-bg,#f7f8f5);color:var(--color-text-secondary,#53665c);flex-shrink:0}.room-name-cell strong{display:block;font-size:14px;font-weight:600;white-space:nowrap}.room-name-cell small{display:block;font-size:12px;color:var(--color-text-muted,#66766b);margin-top:3px}.room-residents{font-weight:600;white-space:nowrap}.room-residents span{font-weight:400;color:var(--color-text-secondary,#53665c)}.room-price{font-weight:600;white-space:nowrap;font-variant-numeric:tabular-nums}.room-price span{font-size:12px;font-weight:500;color:var(--color-text-secondary,#53665c)}.room-status{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:500;border-radius:6px;padding:5px 8px;white-space:nowrap}.room-status.occupied{background:#edf4ee;color:#295d45}.room-status.available{background:#f0f3f0;color:#4b6252}.room-status.maintenance{background:#faf2e6;color:#845716}.room-detail-link{display:inline-flex;align-items:center;justify-content:flex-end;gap:5px;min-height:44px;padding:8px 0;border:0;background:none;color:var(--color-primary,#235c4b);font-size:13px;font-weight:600;white-space:nowrap}.room-detail-link:hover{text-decoration:underline}.room-action{text-align:right}.room-list-footer{border-top:1px solid var(--color-border,#dce4dc);padding:13px 20px;color:var(--color-text-muted,#66766b);font-size:12px}.room-empty{text-align:center;padding:40px 20px 42px;border-top:1px solid var(--color-border,#dce4dc)}.empty-symbol{display:grid;place-items:center;width:56px;height:56px;background:var(--color-bg,#f7f8f5);border-radius:14px;margin:0 auto 15px;color:var(--color-text-secondary,#53665c)}.room-empty h4{font-size:16px;margin:0 0 7px;font-weight:600}.room-empty p{font-size:14px;margin:0 0 20px;color:var(--color-text-secondary,#53665c)}
.room-dialog{width:min(520px,calc(100vw - 32px));max-height:calc(100dvh - 40px);border:1px solid var(--color-border,#dce4dc);border-radius:16px;padding:25px;color:var(--color-text,#233b32);background:var(--color-surface,#fff);box-shadow:0 16px 80px #233b3221;overflow:auto}.room-dialog::backdrop{background:#152c2470}.room-dialog-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;margin-bottom:18px}.room-dialog-heading h3{font-size:23px;line-height:1.3;margin:0;font-weight:650}.room-close{border:0;background:transparent;border-radius:8px;min-width:44px;min-height:44px;display:grid;place-items:center;margin:-7px -8px 0 0;color:var(--color-text-secondary,#53665c)}.room-close:hover{background:var(--color-bg,#f7f8f5)}.room-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px 18px;margin:25px 0}.room-detail-grid dt{font-size:12px;color:var(--color-text-secondary,#53665c);margin-bottom:6px}.room-detail-grid dd{font-size:15px;font-weight:600;margin:0}.room-dialog-note{font-size:13px;color:var(--color-text-secondary,#53665c);line-height:1.7;background:var(--color-bg,#f7f8f5);padding:12px 14px;border-radius:8px;margin:0 0 18px}.room-dialog-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:24px}.room-add-form label{display:flex;flex-direction:column;gap:7px;font-size:13px;font-weight:600;margin-bottom:17px;position:relative}.room-add-form label>span{color:var(--color-danger,#a43c32);position:absolute;top:0;right:0}.room-add-form input{padding:10px 12px;font-weight:400}.room-form-columns{display:grid;grid-template-columns:1fr 1fr;gap:16px}.room-form-feedback{font-size:13px;border-left:3px solid var(--color-primary,#235c4b);background:var(--color-primary-soft,#edf4ee);padding:12px 14px;margin:0;border-radius:0 6px 6px 0}.room-sr-only{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
:is(button,input,select):focus-visible{outline:3px solid var(--color-focus,#25745a);outline-offset:3px}
@media(max-width:1100px){.room-summary-item{padding:15px}.room-summary p{font-size:12px}.room-table th,.room-table td{padding-left:13px;padding-right:13px}.room-name-cell{gap:8px}.room-icon{display:none}}
@media(max-width:850px){.rooms-preview{padding:20px}.rooms-heading{align-items:flex-start}.room-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.room-table thead{display:none}.room-table,.room-table tbody{display:block}.room-table tbody{padding:0 16px 8px}.room-table tr{display:grid;grid-template-columns:1fr 1fr;padding:16px 0;border-top:1px solid var(--color-border,#dce4dc);column-gap:12px;row-gap:14px}.room-table th,.room-table td{border:0;padding:0}.room-table tbody th{grid-column:1 / -1}.room-icon{display:grid}.room-table td[data-label]::before{content:attr(data-label);display:block;margin-bottom:5px;font-size:12px;color:var(--color-text-muted,#66766b)}.room-action{align-self:end;text-align:right}.room-detail-link{padding:3px 0;min-height:44px}.room-filters{gap:10px}.room-search{max-width:none}.room-status-filter{flex-shrink:0}}
@media(max-width:500px){.rooms-preview{padding:16px 12px;border-radius:12px}.rooms-heading{display:block;margin-bottom:21px}.rooms-heading h2{font-size:24px}.heading-description{font-size:13px;line-height:1.7}.rooms-heading>.room-button{margin-top:16px;min-height:44px}.room-summary{gap:10px;margin-bottom:18px}.room-summary-item{padding:13px 12px;min-height:124px}.room-summary dt{font-size:12px;gap:5px}.room-summary dd{font-size:27px;margin-top:10px}.room-summary p{line-height:1.5;font-size:12px}.room-list-heading{padding:16px 14px 0}.room-list-heading h3{font-size:15px}.room-list-heading p{font-size:12px}.room-filters{padding:16px 14px}.room-search{flex-basis:100%;min-width:0}.room-status-filter{flex:1}.room-list-footer{padding-left:15px}.room-dialog{padding:20px}.room-dialog-actions .room-button{flex:1}.room-detail-grid{gap:20px 14px}.room-form-columns{grid-template-columns:1fr;gap:0}}
.room-search input, .room-status-filter select, .room-add-form input { border-color: var(--color-input-border); }
@media (max-width: 640px) { .room-search input, .room-status-filter select, .room-add-form input { font-size: 16px; min-height: 48px; } }
</style>

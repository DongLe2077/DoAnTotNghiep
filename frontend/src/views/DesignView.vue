<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiField from '@/components/ui/UiField.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiEmptyState from '@/components/ui/UiEmptyState.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import OwnerRoomsPreview from '@/design/OwnerRoomsPreview.vue'
import TenantRoomPreview from '@/design/TenantRoomPreview.vue'
import { formatDate, formatMoney } from '@/lib/format'

const route = useRoute()
const view = computed(() => route.query.view === 'owner' || route.query.view === 'tenant' ? route.query.view : 'system')
const tabs = [{ id: 'system', label: 'Thiết kế chung' }, { id: 'owner', label: 'Mẫu chủ trọ' }, { id: 'tenant', label: 'Mẫu người thuê' }]
const palette = [
  { name: 'Xanh an tâm', value: '#235C4B', purpose: 'Nút chính và điểm nhấn', color: 'var(--color-primary)' },
  { name: 'Trắng ấm', value: '#F7F8F5', purpose: 'Nền thoáng, dịu mắt', color: 'var(--color-bg)' },
  { name: 'Xanh rất nhạt', value: '#EDF4EE', purpose: 'Vùng được chọn', color: 'var(--color-primary-soft)' },
  { name: 'Mực xanh', value: '#233B32', purpose: 'Nội dung dễ đọc', color: 'var(--color-text)' },
  { name: 'Vàng thận trọng', value: '#835D1E', purpose: 'Nhắc việc cần chú ý', color: 'var(--color-warning)' },
  { name: 'Đỏ rõ ràng', value: '#A43C32', purpose: 'Lỗi và thao tác cần cân nhắc', color: 'var(--color-danger)' },
]
const name = ref('')
const amount = ref('2500000.00')
const nameError = ref('')
const formNotice = ref('')
const showConfirmation = ref(false)
const displayState = ref<'empty' | 'loading' | 'error'>('empty')
const stateOptions = [{ id: 'empty', label: 'Chưa có dữ liệu' }, { id: 'loading', label: 'Đang tải' }, { id: 'error', label: 'Lỗi kết nối' }] as const

function tryForm() {
  nameError.value = name.value.trim() ? '' : 'Vui lòng nhập tên phòng trước khi tiếp tục.'
  formNotice.value = nameError.value ? '' : 'Biểu mẫu hợp lệ. Đây là thao tác thử, thông tin chưa được lưu vào hệ thống.'
}
</script>

<template>
  <div class="design-page">
    <a class="skip-link" href="#design-content">Đến nội dung chính</a>
    <header class="design-header">
      <RouterLink class="design-brand" to="/design" aria-label="Nhà Trọ — Thiết kế chung">
        <span aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m4 10 8-6 8 6v10H4V10Zm5 10v-7h6v7" /></svg></span>
        <div><strong>Nhà Trọ</strong><small>Không gian quản lý của bạn</small></div>
      </RouterLink>
      <RouterLink class="application-link" to="/login">Mở ứng dụng <span aria-hidden="true">↗</span></RouterLink>
    </header>

    <main id="design-content" class="design-content">
      <section v-if="view === 'system'" class="design-intro">
        <p class="eyebrow">Hướng thiết kế đề xuất</p>
        <h1>Gọn gàng, rõ ràng<br class="desktop-break" /> và an tâm.</h1>
        <p>Ít chi tiết hơn, dễ sử dụng hơn. Mỗi màn hình có thứ tự rõ ràng để chủ trọ và người thuê tìm được việc mình cần.</p>
        <div class="design-principles"><span>Chữ dễ đọc</span><span>Thao tác rõ ràng</span><span>Khoảng thở vừa đủ</span></div>
      </section>
      <section v-else class="design-intro design-intro--compact">
        <p class="eyebrow">Xem trước bố cục</p>
        <h1>{{ view === 'owner' ? 'Không gian chủ trọ' : 'Không gian người thuê' }}</h1>
        <p>{{ view === 'owner' ? 'Thông tin có thứ tự, thao tác quản lý dễ tìm.' : 'Thông tin nơi ở rõ ràng, thuận tiện ngay trên điện thoại.' }}</p>
      </section>

      <nav class="design-navigation" aria-label="Các mẫu giao diện">
        <RouterLink v-for="tab in tabs" :key="tab.id" :to="{ path: '/design', query: tab.id === 'system' ? {} : { view: tab.id } }" :aria-current="view === tab.id ? 'page' : undefined" :class="{ selected: view === tab.id }">{{ tab.label }}</RouterLink>
      </nav>

      <template v-if="view === 'system'">
        <section class="design-section" aria-labelledby="palette-title">
          <div class="section-heading"><div><p class="section-number">01 · Màu sắc</p><h2 id="palette-title">Một bảng màu vừa đủ</h2></div><p>Xanh trầm tạo điểm tựa. Nền sáng giúp thông tin có khoảng thở.</p></div>
          <div class="palette-grid"><article v-for="color in palette" :key="color.value" class="swatch-card"><div class="swatch" :style="{ background: color.color }" aria-hidden="true" /><div><h3>{{ color.name }}</h3><code>{{ color.value }}</code><p>{{ color.purpose }}</p></div></article></div>
        </section>

        <section class="design-section type-section" aria-labelledby="type-title">
          <div class="section-heading"><div><p class="section-number">02 · Kiểu chữ</p><h2 id="type-title">Be Vietnam Pro</h2></div><p>Một kiểu chữ xuyên suốt, hiển thị rõ dấu tiếng Việt.</p></div>
          <div class="type-grid"><div class="type-specimen"><p class="type-heading">Mọi việc đều có chỗ của mình.</p><p class="type-body">Thông tin phòng, hóa đơn và dịch vụ được sắp xếp để bạn dễ tìm, dễ hiểu và dễ thao tác.</p><p class="type-caption">Ă Â Ê Ô Ơ Ư · ă â ê ô ơ ư · 0123456789</p></div><dl class="type-scale"><div><dt>Tiêu đề trang</dt><dd>28–36 px · đậm vừa</dd></div><div><dt>Nội dung chính</dt><dd>14–16 px · thoáng dòng</dd></div><div><dt>Ghi chú</dt><dd>Từ 12 px · đủ tương phản</dd></div><div><dt>Số tiền</dt><dd class="ui-money">{{ formatMoney('2500000.00') }}</dd></div></dl></div>
        </section>

        <section class="design-section" aria-labelledby="controls-title">
          <div class="section-heading"><div><p class="section-number">03 · Thao tác</p><h2 id="controls-title">Nhìn là biết cần làm gì</h2></div><p>Một nút chính cho mỗi nhóm công việc. Nút phụ luôn nhẹ hơn.</p></div>
          <div class="controls-grid"><article class="ui-card"><h3>Nút và trạng thái</h3><div class="button-examples"><UiButton @click="showConfirmation = true">Lưu thay đổi</UiButton><UiButton variant="secondary" @click="formNotice = 'Thao tác phụ chỉ là ví dụ, không thay đổi dữ liệu.'">Thao tác phụ</UiButton><UiButton disabled>Chưa khả dụng</UiButton></div><p class="component-note">Nút cao tối thiểu 44 px, tăng thành 48 px trên điện thoại. Có dấu focus khi dùng bàn phím.</p><div class="badge-examples"><UiBadge tone="success">Đang ở</UiBadge><UiBadge>Phòng trống</UiBadge><UiBadge tone="warning">Chờ xử lý</UiBadge><UiBadge tone="danger">Cần kiểm tra</UiBadge></div><p class="component-note">Màu luôn đi cùng nhãn chữ, giúp đọc được trạng thái ngay cả khi khó phân biệt màu.</p></article>
            <article class="ui-card"><h3>Biểu mẫu dễ hiểu</h3><form novalidate @submit.prevent="tryForm"><UiField id="design-room-name" v-slot="{ describedBy, invalid }" label="Tên phòng" required :error="nameError" hint="Tên ngắn, dễ nhận biết, ví dụ A101."><input id="design-room-name" v-model="name" class="ui-input" placeholder="Nhập tên phòng" required :aria-describedby="describedBy" :aria-invalid="invalid" @input="nameError = ''; formNotice = ''" /></UiField><UiField id="design-room-price" v-slot="{ describedBy }" label="Giá thuê toàn phòng" :hint="`Hiển thị: ${formatMoney(amount)}`"><input id="design-room-price" v-model="amount" class="ui-input" inputmode="decimal" :aria-describedby="describedBy" /></UiField><div class="form-actions"><p>Dữ liệu minh họa · Không lưu vào hệ thống</p><UiButton type="submit">Kiểm tra biểu mẫu</UiButton></div></form></article>
          </div>
          <UiNotice v-if="formNotice" class="form-feedback" tone="info">{{ formNotice }}</UiNotice>
        </section>

        <section class="design-section" aria-labelledby="feedback-title">
          <div class="section-heading"><div><p class="section-number">04 · Phản hồi</p><h2 id="feedback-title">Luôn biết chuyện gì đang xảy ra</h2></div><p>Thông báo ngắn, có hướng xử lý. Không để người dùng phải đoán.</p></div>
          <div class="notice-grid"><UiNotice tone="success" title="Mẫu thông báo thành công">Đã cập nhật thông tin. Bạn có thể tiếp tục công việc.</UiNotice><UiNotice tone="warning" title="Mẫu thông báo cần chú ý">Vui lòng kiểm tra số tiền và người nhận trước khi xác nhận.</UiNotice></div>
          <div class="state-card ui-card"><div class="state-switch" aria-label="Xem mẫu trạng thái"><UiButton v-for="option in stateOptions" :key="option.id" :variant="displayState === option.id ? 'secondary' : 'ghost'" :aria-pressed="displayState === option.id" @click="displayState = option.id">{{ option.label }}</UiButton></div><UiEmptyState v-if="displayState === 'empty'" title="Chưa có thông tin ở đây" description="Khi có dữ liệu, nội dung sẽ xuất hiện tại đây." /><UiEmptyState v-else-if="displayState === 'loading'" tone="loading" title="Đang tải thông tin…" description="Bạn chờ một chút nhé. Đây là ví dụ trạng thái đang tải." /><UiEmptyState v-else tone="error" title="Chưa tải được thông tin" description="Kết nối có thể đang gián đoạn. Bạn có thể thử lại."><UiButton variant="secondary" @click="displayState = 'empty'">Thử lại bản mẫu</UiButton></UiEmptyState></div>
        </section>

        <section class="design-next ui-card"><div><h2>Xem thiết kế trong từng vai trò</h2><p>Cùng một phong cách, sắp xếp theo việc mỗi người cần làm.</p></div><div><RouterLink to="/design?view=owner">Mẫu chủ trọ <span aria-hidden="true">→</span></RouterLink><RouterLink to="/design?view=tenant">Mẫu người thuê <span aria-hidden="true">→</span></RouterLink></div></section>
      </template>
      <template v-else>
        <UiNotice class="sample-notice" tone="info" title="Bản mẫu giao diện">Các phòng, giá và thời gian dưới đây là dữ liệu minh họa. Thao tác thử không ghi vào hệ thống nhà trọ.</UiNotice>
        <OwnerRoomsPreview v-if="view === 'owner'" />
        <TenantRoomPreview v-else />
      </template>
    </main>

    <footer class="design-footer"><span>Nhà Trọ · Tối giản để dễ sử dụng</span><span>Thống nhất màu sắc, chữ và cách tương tác</span></footer>

    <UiDialog v-model:open="showConfirmation" title="Kiểm tra trước khi lưu" description="Mẫu hộp thoại giúp người dùng xem lại thông tin trước một thao tác quan trọng."><dl class="confirmation-summary"><div><dt>Phòng minh họa</dt><dd>A101</dd></div><div><dt>Giá thuê toàn phòng</dt><dd class="ui-money">{{ formatMoney('2500000.00') }}</dd></div><div><dt>Ngày áp dụng minh họa</dt><dd>{{ formatDate('2026-11-01') }}</dd></div></dl><p class="component-note">Đây là bản mẫu. Chưa có thông tin nào được lưu.</p><template #actions><UiButton variant="secondary" @click="showConfirmation = false">Quay lại</UiButton><UiButton @click="showConfirmation = false; formNotice = 'Đã đóng bản mẫu xác nhận. Không có dữ liệu được lưu.'">Đóng bản mẫu</UiButton></template></UiDialog>
  </div>
</template>

<style scoped>
.design-page { min-height: 100dvh; background: var(--color-bg); }
.skip-link { position: fixed; top: -80px; left: 16px; z-index: 100; padding: 12px; color: #fff; background: var(--color-primary); border-radius: 10px; }
.skip-link:focus { top: 12px; }
.design-header, .design-content, .design-footer { width: min(1248px, calc(100% - 80px)); margin: 0 auto; }
.design-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 100px; border-bottom: 1px solid var(--color-border); }
.design-brand { display: inline-flex; align-items: center; gap: 12px; text-decoration: none; }
.design-brand > span { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 12px; color: #fff; background: var(--color-primary); }
.design-brand svg { width: 26px; height: 26px; stroke: currentColor; stroke-width: 1.7; stroke-linejoin: round; }
.design-brand strong { display: block; font-size: 20px; font-weight: 600; line-height: 1.4; }
.design-brand small { display: block; margin-top: 3px; color: var(--color-text-secondary); font-size: 12px; }
.application-link { display: flex; align-items: center; gap: 14px; min-height: 44px; font-size: 14px; font-weight: 500; text-decoration: none; color: var(--color-primary); }
.design-intro { max-width: 760px; padding: 56px 0 36px; }
.eyebrow { margin: 0 0 16px; font-size: 14px; font-weight: 500; color: var(--color-primary); }
h1 { margin: 0; font-size: clamp(32px, 4vw, 48px); line-height: 1.3; font-weight: 600; letter-spacing: -1.3px; }
.design-intro > p:not(.eyebrow) { max-width: 650px; margin: 20px 0; color: var(--color-text-secondary); font-size: 16px; }
.design-intro--compact { max-width: none; padding: 28px 0 24px; }
.design-intro--compact .eyebrow { margin-bottom: 8px; font-size: 13px; }
.design-intro--compact h1 { font-size: 32px; letter-spacing: -.7px; }
.design-intro--compact > p:not(.eyebrow) { margin: 12px 0 0; font-size: 14px; }
.design-principles { display: flex; flex-wrap: wrap; gap: 16px 24px; margin-top: 24px; color: var(--color-text-secondary); font-size: 13px; }
.design-principles > span::before { content: '✓'; margin-right: 8px; color: var(--color-primary); }
.design-navigation { display: flex; gap: 8px; padding: 8px; border: 1px solid var(--color-border); border-radius: 14px; background: var(--color-surface); }
.design-navigation > a { display: grid; place-items: center; min-height: 48px; padding: 10px 24px; border-radius: 9px; color: var(--color-text-secondary); text-decoration: none; font-size: 14px; font-weight: 500; }
.design-navigation > a.selected { color: var(--color-primary); background: var(--color-primary-soft); }
.design-navigation > a:hover { background: var(--color-bg); }
.design-section { margin-top: 48px; }
.section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 24px; }
.section-number { margin: 0 0 8px; color: var(--color-text-secondary); font-size: 13px; }
h2 { margin: 0; font-size: 24px; font-weight: 600; line-height: 1.5; letter-spacing: -.4px; }
.section-heading > p { max-width: 370px; margin: 0; color: var(--color-text-secondary); font-size: 14px; }
.palette-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 16px; }
.swatch-card { min-width: 0; overflow: hidden; border: 1px solid var(--color-border); border-radius: 12px; background: var(--color-surface); }
.swatch { height: 84px; border-bottom: 1px solid var(--color-border); }
.swatch-card > div:last-child { padding: 16px 14px; }
h3 { margin: 0 0 16px; font-size: 16px; font-weight: 600; }
.swatch-card h3 { margin: 0 0 4px; font-size: 14px; }
.swatch-card code { color: var(--color-text-secondary); font-size: 12px; }
.swatch-card p { min-height: 42px; margin: 8px 0 0; color: var(--color-text-secondary); font-size: 12px; }
.type-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 32px; padding: 32px; border: 1px solid var(--color-border); border-radius: 16px; background: var(--color-surface); }
.type-heading { margin: 0 0 20px; font-size: 28px; line-height: 1.5; font-weight: 600; }
.type-body { max-width: 540px; margin: 0 0 24px; font-size: 16px; color: var(--color-text-secondary); }
.type-caption { margin: 0; font-size: 14px; color: var(--color-text-secondary); }
.type-scale { display: grid; align-content: center; gap: 16px; margin: 0; padding-left: 32px; border-left: 1px solid var(--color-border); }
.type-scale > div { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; font-size: 14px; }
.type-scale dt { color: var(--color-text-secondary); }
.type-scale dd { margin: 0; }
.controls-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.button-examples, .badge-examples { display: flex; flex-wrap: wrap; gap: 12px; }
.component-note { margin: 16px 0 24px; color: var(--color-text-secondary); font-size: 13px; }
.badge-examples { margin-top: 28px; }
.controls-grid form { display: grid; gap: 24px; }
.form-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; }
.form-actions p { max-width: 200px; margin: 0; font-size: 12px; color: var(--color-text-secondary); }
.form-feedback { margin-top: 20px; }
.notice-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
.state-card { margin-top: 24px; padding-bottom: 8px; }
.state-switch { display: flex; flex-wrap: wrap; gap: 8px; }
.design-next { display: flex; align-items: center; justify-content: space-between; gap: 24px; margin: 48px 0; }
.design-next h2 { font-size: 20px; }
.design-next p { margin: 8px 0 0; color: var(--color-text-secondary); font-size: 14px; }
.design-next > div:last-child { display: flex; flex-wrap: wrap; gap: 16px; }
.design-next a { display: flex; align-items: center; gap: 16px; min-height: 44px; color: var(--color-primary); font-size: 14px; font-weight: 500; text-decoration: none; }
.sample-notice { margin: 24px 0 32px; }
.design-footer { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; margin-top: 48px; padding: 24px 0; border-top: 1px solid var(--color-border); color: var(--color-text-secondary); font-size: 12px; }
.confirmation-summary { display: grid; gap: 12px; margin: 0; }
.confirmation-summary > div { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; font-size: 14px; }
.confirmation-summary dt { color: var(--color-text-secondary); }
.confirmation-summary dd { margin: 0; }
@media (max-width: 1050px) {
  .palette-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
  .type-grid { grid-template-columns: 1fr; }
  .type-scale { border-left: 0; padding-left: 0; border-top: 1px solid var(--color-border); padding-top: 24px; }
  .controls-grid { grid-template-columns: 1fr; }
}
@media (max-width: 700px) {
  .design-header, .design-content, .design-footer { width: calc(100% - 32px); }
  .design-header { min-height: 88px; }
  .design-brand small { max-width: 140px; }
  .application-link { gap: 8px; font-size: 13px; }
  .design-intro { padding: 32px 0 28px; }
  h1 { font-size: 32px; letter-spacing: -.8px; }
  .desktop-break { display: none; }
  .design-intro > p:not(.eyebrow) { font-size: 15px; }
  .design-principles { gap: 12px 16px; font-size: 12px; }
  .design-navigation { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 4px; padding: 6px; }
  .design-navigation > a { padding: 10px 6px; font-size: 13px; text-align: center; }
  .design-section { margin-top: 32px; }
  .section-heading { display: block; margin-bottom: 20px; }
  .section-heading > p { margin-top: 12px; }
  h2 { font-size: 22px; }
  .palette-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
  .swatch { height: 64px; }
  .type-grid { padding: 24px 20px; gap: 24px; }
  .type-heading { font-size: 24px; }
  .ui-card { padding: 20px; }
  .notice-grid { grid-template-columns: 1fr; gap: 16px; }
  .state-switch { gap: 4px; }
  .state-switch :deep(button) { padding: 10px; font-size: 12px; }
  .design-next { display: block; }
  .design-next > div:last-child { margin-top: 20px; }
  .design-footer { margin-top: 32px; }
}
</style>

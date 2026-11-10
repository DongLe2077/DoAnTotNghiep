<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import { usePwaInstall } from '@/lib/pwa-install'

defineProps<{ compact?: boolean }>()
const { available, busy, hidden, message, failed, requestInstall } = usePwaInstall()
const open = ref(false)
const guideContent = ref<HTMLElement | null>(null)
const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (/Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1)
const platform = ref<'android' | 'ios' | 'desktop'>(ios ? 'ios' : /Android/.test(navigator.userAgent) ? 'android' : 'desktop')
const temporaryAddress = location.hostname.endsWith('.trycloudflare.com')
const secure = window.isSecureContext
watch(hidden, value => { if (value) open.value = false })
watch(platform, async () => {
  if (!open.value) return
  await nextTick()
  const scroller = guideContent.value?.parentElement
  if (scroller) scroller.scrollTop = 0
})
</script>

<template>
  <button v-if="!hidden" type="button" class="install-trigger" :class="{ 'install-trigger--compact': compact }" aria-label="Cài ứng dụng Nhà Trọ" title="Cài ứng dụng Nhà Trọ" @click="open = true">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m-4-4 4 4 4-4M5 16v5h14v-5" /></svg>
    <span>Cài ứng dụng</span>
  </button>
  <UiDialog v-model:open="open" reset-scroll class="pwa-install-dialog" title="Cài Nhà Trọ trên thiết bị" description="Cài từ website để có biểu tượng Nhà Trọ trên màn hình chính.">
    <div ref="guideContent" class="install-guide-content">
    <p v-if="!secure" class="install-warning" role="status">Để cài ứng dụng, hãy mở trang bằng địa chỉ HTTPS được cung cấp.</p>
    <p v-if="temporaryAddress" class="install-warning">Đây là địa chỉ thử tạm thời. Nếu địa chỉ thay đổi hoặc máy chủ dừng, biểu tượng đã cài sẽ không mở được; bạn cần cài lại từ địa chỉ mới.</p>
    <div v-if="available || busy" class="native-install"><UiButton :loading="busy" @click="requestInstall">{{ busy ? 'Đang chờ trình duyệt…' : 'Cài trên thiết bị này' }}</UiButton><p class="install-note">Nhấn nút này rồi xác nhận trong hộp cài của trình duyệt.</p></div>
    <p v-if="message" :role="failed ? 'alert' : 'status'" class="install-feedback">{{ message }}</p>
    <fieldset class="platform-picker"><legend>Thiết bị của bạn</legend><div><button v-for="item in ([['android', 'Android'], ['ios', 'iPhone / iPad'], ['desktop', 'Máy tính']] as const)" :key="item[0]" type="button" :aria-pressed="platform === item[0]" @click="platform = item[0]">{{ item[1] }}</button></div></fieldset>
    <ol v-if="platform === 'ios'" class="install-steps">
      <li>Mở địa chỉ này bằng <strong>Safari</strong>.</li>
      <li>Nhấn <strong>Chia sẻ</strong> (có thể nằm trong menu trang), rồi chọn <strong>Thêm vào MH chính</strong>.</li>
      <li>Bật <strong>Mở dưới dạng ứng dụng web</strong> nếu có, rồi nhấn <strong>Thêm</strong>.</li>
      <li>Mở biểu tượng <strong>Nhà Trọ</strong> trên màn hình chính và đăng nhập.</li>
    </ol>
    <ol v-else-if="platform === 'android'" class="install-steps">
      <li>Nhấn <strong>Đóng hướng dẫn</strong> bên dưới để trở về trang đang mở trong Chrome.</li>
      <li>Nhấn <strong>menu Chrome ở cuối thanh địa chỉ</strong>: nút ba chấm hoặc nút mũi tên cập nhật.
        <span class="chrome-menu-example" aria-hidden="true"><span class="menu-dot-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="2" /><circle cx="12" cy="12" r="2" /><circle cx="12" cy="19" r="2" /></svg></span><span>hoặc</span><span class="menu-update-icon"><svg viewBox="0 0 24 24"><path d="M12 19V5m-6 6 6-6 6 6" /></svg></span></span>
      </li>
      <li>Chọn <strong>Cài đặt ứng dụng</strong> hoặc <strong>Thêm vào màn hình chính</strong>. Nếu menu dùng tiếng Anh, tìm <strong>Install app</strong> hoặc <strong>Add to Home screen</strong>.</li>
      <li>Khi Chrome hiện hộp xác nhận, nhấn <strong>Cài đặt / Install</strong> hoặc <strong>Thêm / Add</strong>.</li>
      <li>Về <strong>màn hình chính Android</strong>, tìm biểu tượng ngôi nhà xanh <strong>Nhà Trọ</strong> rồi mở và đăng nhập.</li>
    </ol>
    <ol v-else class="install-steps">
      <li>Mở trang bằng <strong>Chrome</strong> hoặc <strong>Edge</strong>.</li>
      <li>Chọn biểu tượng cài trên thanh địa chỉ hoặc tìm <strong>Cài đặt ứng dụng</strong> trong menu trình duyệt.</li>
      <li>Xác nhận cài, rồi mở <strong>Nhà Trọ</strong> từ danh sách ứng dụng.</li>
    </ol>
    <template v-if="platform === 'android'">
      <p class="install-note bluestacks-note"><strong>Nếu dùng BlueStacks:</strong> nút về màn hình chính là hình ngôi nhà trên thanh BlueStacks màu tím, cạnh mũi tên quay lại.</p>
      <details class="install-help"><summary>Không thấy mục cài trong Chrome?</summary><p>Cuộn xuống trong menu. Nếu có <strong>Cài đặt và tạo lối tắt</strong> (<strong>Install and create shortcut</strong>), mở mục đó rồi chọn <strong>Cài đặt / Install</strong>.</p><p>Mở trang bằng Chrome thông thường và giữ kết nối mạng. Tên mục có thể khác theo phiên bản.</p></details>
    </template>
    <p class="install-note">Ứng dụng vẫn cần Internet để đăng nhập, xem dữ liệu mới và lưu thao tác.</p>
    </div>
    <template #actions><UiButton variant="secondary" @click="open = false">Đóng hướng dẫn</UiButton></template>
  </UiDialog>
</template>

<style scoped>
.install-trigger { display: inline-flex; align-items: center; justify-content: center; gap: 8px; flex-shrink: 0; min-height: 44px; padding: 10px 12px; color: var(--color-primary); background: var(--color-primary-soft); border: 1px solid var(--color-border); border-radius: 10px; font-size: 14px; font-weight: 600; }
.install-trigger:hover { border-color: var(--color-primary); }
.install-trigger svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.pwa-install-dialog[open] { display: flex; flex-direction: column; }
.pwa-install-dialog :deep(header), .pwa-install-dialog :deep(.description), .pwa-install-dialog :deep(footer) { flex-shrink: 0; }
.pwa-install-dialog :deep(.content) { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding-right: 4px; scrollbar-gutter: stable; }
.install-guide-content { min-width: 0; }
.install-note { margin: 12px 0; color: var(--color-text-secondary); font-size: 14px; }
.install-warning { padding: 12px 14px; border: 1px solid #e5d3ae; border-radius: 10px; color: var(--color-warning); background: var(--color-warning-soft); font-size: 14px; }
.native-install { margin: 0 0 20px; }
.install-feedback { color: var(--color-text-secondary); font-size: 14px; }
.platform-picker { min-width: 0; margin: 0 0 16px; padding: 0; border: 0; }
.platform-picker legend { margin-bottom: 10px; font-size: 14px; font-weight: 600; }
.platform-picker > div { display: flex; flex-wrap: wrap; gap: 8px; }
.platform-picker button { min-height: 44px; padding: 8px 12px; border: 1px solid var(--color-border); border-radius: 10px; color: var(--color-text-secondary); background: var(--color-surface); font-size: 13px; }
.platform-picker button[aria-pressed='true'] { border-color: var(--color-primary); color: var(--color-primary); background: var(--color-primary-soft); font-weight: 600; }
.install-steps { padding-left: 24px; font-size: 14px; line-height: 1.8; }
.install-steps li { padding-left: 4px; margin: 12px 0; }
.chrome-menu-example { display: flex; align-items: center; gap: 10px; margin-top: 8px; color: var(--color-text-secondary); font-size: 13px; }
.chrome-menu-example > span:first-child, .menu-update-icon { display: grid; place-items: center; width: 32px; height: 32px; }
.menu-dot-icon { border: 1px solid var(--color-border); border-radius: 8px; background: var(--color-surface); }
.menu-dot-icon svg { width: 20px; height: 20px; fill: currentColor; }
.menu-update-icon { border-radius: 50%; color: #fff; background: #d83b30; }
.menu-update-icon svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.bluestacks-note { padding: 12px; border-radius: 10px; background: var(--color-primary-soft); }
.install-help { margin: 16px 0; color: var(--color-text-secondary); font-size: 14px; }
.install-help summary { min-height: 44px; padding: 8px 0; color: var(--color-primary); font-weight: 600; cursor: pointer; }
.install-help p { margin: 8px 0; }
@media (max-width: 640px) { .install-trigger { min-height: 48px; } .install-trigger--compact { width: 44px; padding: 10px; border-color: transparent; background: transparent; } .install-trigger--compact span { display: none; } }
</style>

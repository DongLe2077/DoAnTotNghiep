<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { reloadBlockReason } from '@/lib/reload-safety'
import { usePwaRuntime } from '@/lib/pwa-runtime'

const { connection, updateAvailable, offlineReady, updateMessage, registrationError, updateLocked, applyUpdate } = usePwaRuntime()
const root = ref<HTMLElement | null>(null), reason = ref(''), later = ref(false), applying = ref(false), readyDismissed = ref(false)
const networkText = computed(() => connection.value === 'offline' ? 'Thiết bị đang mất mạng. Nội dung đã mở có thể chưa cập nhật; cần mạng để lưu thao tác.' : connection.value === 'unavailable' ? 'Chưa liên hệ được máy chủ. Kết quả thao tác vừa gửi có thể cần đối chiếu; không tự gửi lại.' : 'Đã có lại tín hiệu kết nối. Bạn có thể tải lại khi hoàn tất công việc để lấy dữ liệu mới.')
let timer: ReturnType<typeof setInterval>, observer: ResizeObserver
function refresh() { reason.value = reloadBlockReason() }
function reload() { refresh(); if (!reason.value) location.reload() }
async function update() { if (applying.value) return; applying.value = true; try { await applyUpdate() } finally { applying.value = false } }
onMounted(() => {
  timer = setInterval(refresh, 500); refresh()
  observer = new ResizeObserver(() => document.documentElement.style.setProperty('--pwa-notice-height', `${root.value?.getBoundingClientRect().height ?? 0}px`))
  if (root.value) observer.observe(root.value)
})
onBeforeUnmount(() => { clearInterval(timer); observer?.disconnect() })
</script>
<template>
  <div ref="root" class="pwa-notices rb7-theme">
    <div v-if="updateLocked" class="pwa-notice" role="status">Đang chuyển sang phiên bản mới. Vui lòng chờ…</div>
    <template v-else>
      <section v-if="connection !== 'online'" class="pwa-notice pwa-notice--network" aria-label="Trạng thái kết nối"><div><p role="status">{{ networkText }}</p><small v-if="reason && connection !== 'offline'">{{ reason }}</small></div><button type="button" :disabled="connection === 'offline' || !!reason" @click="reload">Tải lại trang</button></section>
      <section v-if="updateAvailable" class="pwa-notice" aria-label="Cập nhật ứng dụng"><div><strong>Có phiên bản mới</strong><p v-if="!later">{{ updateMessage || 'Lưu công việc đang nhập trước khi cập nhật.' }}</p><small v-if="!later && reason">{{ reason }}</small></div><button v-if="later" type="button" @click="later = false">Xem cập nhật</button><template v-else><button type="button" class="pwa-update-button" :disabled="!!reason || applying || connection === 'offline'" @click="update">{{ applying ? 'Đang cập nhật…' : 'Cập nhật ngay' }}</button><button type="button" @click="later = true">Để sau</button></template></section>
      <div v-else-if="offlineReady && !readyDismissed && connection === 'online'" class="pwa-notice pwa-notice--ready" role="status"><p>Đã sẵn sàng mở lại giao diện khi mất mạng. Dữ liệu và thao tác vẫn cần kết nối.</p><button type="button" aria-label="Đóng thông báo sẵn sàng" @click="readyDismissed = true">Đã hiểu</button></div>
      <div v-if="registrationError" class="pwa-notice" role="status">Chưa chuẩn bị được bản mở lại khi mất mạng. Bạn vẫn có thể dùng website khi có kết nối.</div>
    </template>
  </div>
</template>
<style scoped>
.pwa-notices { position: sticky; top: env(safe-area-inset-top, 0px); z-index: 45; }
.pwa-notice { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; padding: 12px max(16px, env(safe-area-inset-left, 0px)); border-bottom: 1px solid var(--color-border); color: var(--color-primary); background: var(--color-primary-soft); font-size: 14px; }
.pwa-notice > div { flex: 1 1 240px; }.pwa-notice p { margin: 0; }.pwa-notice small { display: block; margin-top: 4px; }.pwa-notice button { flex: 0 0 auto; min-height: 44px; padding: 8px 12px; border: 1px solid var(--color-input-border); border-radius: 10px; color: var(--color-primary); background: var(--color-surface); font-size: 13px; font-weight: 600; }.pwa-notice button:disabled { opacity: .6; }.pwa-notice--network { color: var(--color-warning); background: var(--color-warning-soft); }.pwa-notice--ready { font-size: 13px; }.pwa-notice--ready p { flex: 1 1 240px; }
.pwa-notice .pwa-update-button { border-color: var(--color-primary); color: #fff; background: var(--color-primary); }
.pwa-notice .pwa-update-button:hover:not(:disabled) { background: var(--color-primary-hover); }
</style>

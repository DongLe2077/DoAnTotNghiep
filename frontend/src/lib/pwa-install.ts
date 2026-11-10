import { computed, readonly, ref } from 'vue'

interface InstallPromptEvent extends Event {
  prompt(): Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

const available = ref(false)
const standalone = ref(false)
const installed = ref(false)
const busy = ref(false)
const message = ref('')
const failed = ref(false)
let pending: InstallPromptEvent | null = null
let initialized = false

// A single listener survives login/role navigation. Native prompts can only be used once.
export function initializePwaInstall() {
  if (initialized) return
  initialized = true
  const display = window.matchMedia('(display-mode: standalone)')
  const updateDisplay = () => {
    standalone.value = display.matches || (navigator as Navigator & { standalone?: boolean }).standalone === true
    if (standalone.value) { pending = null; available.value = false }
  }
  updateDisplay()
  display.addEventListener('change', updateDisplay)
  window.addEventListener('beforeinstallprompt', event => {
    const prompt = event as InstallPromptEvent
    if (standalone.value || installed.value || typeof prompt.prompt !== 'function') return
    event.preventDefault()
    pending = prompt
    available.value = true
    message.value = ''
    failed.value = false
  })
  window.addEventListener('appinstalled', () => {
    installed.value = true
    pending = null
    available.value = false
  })
}

async function requestInstall() {
  if (!pending || busy.value) return
  const event = pending
  pending = null
  available.value = false
  busy.value = true
  message.value = ''
  failed.value = false
  try {
    // Invoke directly from the user's click, before awaiting anything else.
    await event.prompt()
    const choice = await event.userChoice
    message.value = choice.outcome === 'accepted'
      ? 'Đã gửi yêu cầu cài. Khi hoàn tất, bạn có thể mở Nhà Trọ từ biểu tượng ứng dụng.'
      : 'Bạn đã để việc cài lại sau. Vẫn có thể sử dụng website bình thường.'
  } catch {
    failed.value = true
    message.value = 'Chưa thể mở lời mời cài. Bạn có thể dùng menu trình duyệt theo hướng dẫn bên dưới.'
  } finally {
    busy.value = false
  }
}

export function usePwaInstall() {
  return {
    available: readonly(available), busy: readonly(busy), message: readonly(message), failed: readonly(failed),
    hidden: computed(() => standalone.value || installed.value), requestInstall,
  }
}

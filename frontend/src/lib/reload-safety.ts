import { ref } from 'vue'

export const activeRequests = ref(0)
export const updateLocked = ref(false)
const blockers = new Set<() => boolean>()
const edited = new Map<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement, string>()
let ownerId: number | null = null
export function setReloadOwner(id: number | null) { ownerId = id }
export function registerReloadBlocker(blocked: () => boolean) { blockers.add(blocked); return () => { blockers.delete(blocked) } }
function value(element: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement) {
  return element instanceof HTMLInputElement && ['checkbox', 'radio'].includes(element.type) ? String(element.checked) : element.value
}
export function initializeFormSafety() {
  document.addEventListener('focusin', event => {
    const element = event.target
    if ((element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement) && element.closest('form') && !edited.has(element)) edited.set(element, value(element))
  })
}
export function clearFormSafety() { edited.clear() }
export function reloadBlockReason(): string {
  if (activeRequests.value) return 'Đang liên hệ máy chủ. Vui lòng chờ thao tác hoàn tất.'
  if (document.querySelector('dialog[open], #app-navigation.is-open')) return 'Hãy hoàn tất hoặc đóng hộp thoại và menu đang mở.'
  for (const blocker of blockers) if (blocker()) return 'Bạn có thông tin chưa lưu. Hãy lưu hoặc rời màn hình đang nhập trước.'
  for (const [element, original] of edited) {
    if (!element.isConnected) { edited.delete(element); continue }
    if (value(element) !== original) return 'Bạn đang nhập thông tin. Hãy hoàn tất hoặc trở về trang tổng quan trước.'
  }
  if (ownerId !== null) {
    try {
      for (const storage of [localStorage, sessionStorage]) for (let index = 0; index < storage.length; index++) {
        const key = storage.key(index) ?? ''
        if (new RegExp(`^rental(?:\\.(?:cash|bulk-meter|maintenance-expense)\\.pending\\.v1:|:pending-[a-z-]+:)${ownerId}(?::|$)`).test(key)) return 'Có lần gửi đang chờ đối chiếu. Hãy xử lý bản chờ trước khi cập nhật.'
      }
    } catch { return 'Chưa kiểm tra được bản chờ trong trình duyệt. Hãy giữ trang này và kiểm tra lại.' }
  }
  return ''
}

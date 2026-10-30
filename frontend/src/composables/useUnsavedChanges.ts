import { onBeforeUnmount, onMounted, toValue, type MaybeRefOrGetter } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

export function useUnsavedChanges(dirty: MaybeRefOrGetter<boolean>, message = 'Bạn có thông tin chưa lưu. Rời trang và bỏ các thay đổi này?') {
  const auth = useAuthStore()
  const scope = auth.user ? `${auth.user.id}:${auth.user.role}` : ''
  // Expired/logout sessions must always leave; stale forms never block account changes.
  const shouldWarn = () => !!scope && !!auth.user && `${auth.user.id}:${auth.user.role}` === scope && toValue(dirty)
  function beforeUnload(event: BeforeUnloadEvent) {
    if (!shouldWarn()) return
    event.preventDefault()
    event.returnValue = ''
  }
  onBeforeRouteLeave(() => !shouldWarn() || window.confirm(message))
  onMounted(() => window.addEventListener('beforeunload', beforeUnload))
  onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))
}

import { createApp, watch } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { router } from '@/router'
import { useAuthStore } from '@/stores/auth'
import { onSessionExpired } from '@/lib/api'
import { getRoleHome } from '@/router/navigation'
import { initializePwaInstall } from '@/lib/pwa-install'
import { initializePwaRuntime } from '@/lib/pwa-runtime'
import { clearFormSafety, initializeFormSafety, setReloadOwner } from '@/lib/reload-safety'
import { initializeMobileViewport } from '@/lib/mobile-viewport'
import './styles/main.css'
import './styles/management.css'
import './styles/tenant.css'
import './redesign/workspace.css'

initializePwaInstall()
initializePwaRuntime()
initializeFormSafety()
initializeMobileViewport()
const app = createApp(App)
app.use(createPinia())
const auth = useAuthStore()
watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', () => {
  setReloadOwner(auth.user?.role === 'owner' ? auth.user.id : null)
  clearFormSafety()
}, { immediate: true, flush: 'sync' })
router.afterEach(clearFormSafety)

onSessionExpired(() => {
  const hadSession = auth.isAuthenticated
  auth.clearSession()
  if (hadSession && router.currentRoute.value.meta.requiresAuth) {
    void router.replace({ name: 'login', query: { reason: 'session-expired' } })
  }
})

let lastCheck = 0
async function syncSession() {
  try {
    const user = await auth.initialize(true)
    const route = router.currentRoute.value
    if (!user && route.meta.requiresAuth) await router.replace({ name: 'login', query: { reason: 'session-expired' } })
    else if (user && (route.name === 'login' || (route.meta.role && route.meta.role !== user.role))) await router.replace(getRoleHome(user.role))
  } catch {
    // A temporary network error is not proof of logout; the user can retry.
  }
}
auth.listenForSessionChanges(() => { void syncSession() })
function checkOnReturn() {
  if (document.visibilityState !== 'visible' || Date.now() - lastCheck < 15_000 || !auth.isAuthenticated) return
  lastCheck = Date.now()
  void syncSession()
}
window.addEventListener('focus', checkOnReturn)
document.addEventListener('visibilitychange', checkOnReturn)

app.use(router)
app.mount('#app')

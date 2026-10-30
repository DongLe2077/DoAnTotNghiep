<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import { api, ApiError, toApiError } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const props = defineProps<{ path: string; filename: string; label: string }>()
const auth = useAuthStore(), busy = ref(false), error = ref('')
let alive = true, sequence = 0, controller: AbortController | null = null
function invalidate() { sequence++; controller?.abort(); controller = null; busy.value = false; error.value = '' }
watch(() => props.path, invalidate)
watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', invalidate, { flush: 'sync' })
onBeforeUnmount(() => { alive = false; invalidate() })
async function download() {
  if (busy.value || !auth.user) return
  const current = ++sequence, scope = `${auth.user.id}:${auth.user.role}`
  busy.value = true; error.value = ''; controller = new AbortController()
  try {
    const result = await api.get<Blob>(props.path, { responseType: 'blob', timeout: 60_000, signal: controller.signal })
    if (!alive || current !== sequence || !auth.user || `${auth.user.id}:${auth.user.role}` !== scope) return
    if (!(result.data instanceof Blob) || !String(result.headers['content-type']).toLowerCase().startsWith('application/pdf') || await result.data.slice(0, 5).text() !== '%PDF-') throw new ApiError(502, 'Chưa nhận được tệp PDF hợp lệ. Hãy thử tải lại.')
    if (!alive || current !== sequence || !auth.user || `${auth.user.id}:${auth.user.role}` !== scope) return
    const url = URL.createObjectURL(result.data), link = document.createElement('a')
    link.href = url; link.download = props.filename.replace(/[^A-Za-z0-9._-]/g, '_'); link.style.display = 'none'
    document.body.appendChild(link); link.click(); link.remove()
    window.setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (caught) { if (alive && current === sequence) error.value = toApiError(caught).message }
  finally { if (alive && current === sequence) { busy.value = false; controller = null } }
}
</script>

<template><div class="pdf-download"><UiButton variant="secondary" :loading="busy" @click="download">{{ label }}</UiButton><p v-if="error" class="form-error" role="alert">{{ error }}</p></div></template>
<style scoped>.pdf-download { display: inline-grid; gap: 8px; min-width: 0; max-width: 100%; }.pdf-download p { margin: 0; max-width: 400px; font-size: 13px; }</style>

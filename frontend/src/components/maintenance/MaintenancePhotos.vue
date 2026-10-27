<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { MaintenancePhoto } from '@/types/maintenance'

const props = withDefaults(defineProps<{ requestId: number; photos: MaintenancePhoto[]; audience?: 'owner' | 'tenant' }>(), { audience: 'owner' })
const auth = useAuthStore()
type ImageEntry = { id: number; url: string; error: string; loading: boolean }
const entries = ref<ImageEntry[]>([]), preview = ref<ImageEntry | null>(null)
const controllers = new Set<AbortController>()
let sequence = 0, alive = true
function identity() { return auth.user ? `${auth.user.id}:${auth.user.role}` : '' }
function clear() {
  sequence++
  controllers.forEach(controller => controller.abort()); controllers.clear()
  preview.value = null
  entries.value.forEach(entry => { if (entry.url) URL.revokeObjectURL(entry.url) })
  entries.value = []
}
async function load() {
  clear()
  if (auth.user?.role !== props.audience || !Number.isSafeInteger(props.requestId) || props.requestId < 1) return
  const scope = sequence, account = identity()
  const photos = props.photos.filter(photo => Number.isSafeInteger(photo.id) && photo.id > 0).slice(0, 5)
  entries.value = photos.map(photo => ({ id: photo.id, url: '', error: '', loading: true }))
  await Promise.all(photos.map(async photo => {
    const controller = new AbortController(); controllers.add(controller)
    try {
      // The path is constructed from numeric IDs. Response-provided URLs are never followed.
      const result = await api.get<Blob>(`/${props.audience}/maintenance-requests/${props.requestId}/photos/${photo.id}`, { responseType: 'blob', signal: controller.signal })
      if (!alive || scope !== sequence || account !== identity()) return
      if (!(result.data instanceof Blob) || !['image/jpeg', 'image/png', 'image/webp'].includes(result.data.type) || result.data.size > 5 * 1024 * 1024) throw new Error('Invalid image')
      const entry = entries.value.find(item => item.id === photo.id)
      if (entry) entry.url = URL.createObjectURL(result.data)
    } catch {
      if (alive && scope === sequence && account === identity()) {
        const entry = entries.value.find(item => item.id === photo.id)
        if (entry) entry.error = 'Chưa thể xem ảnh này. Ảnh có thể đã không còn khả dụng.'
      }
    } finally {
      controllers.delete(controller)
      if (alive && scope === sequence) {
        const entry = entries.value.find(item => item.id === photo.id)
        if (entry) entry.loading = false
      }
    }
  }))
}
watch([() => props.requestId, () => props.photos.map(photo => photo.id).join(','), () => props.audience, identity], () => { void load() }, { immediate: true, flush: 'sync' })
onBeforeUnmount(() => { alive = false; clear() })
</script>

<template>
  <section class="record-card maintenance-photos">
    <header><div><h2>Ảnh người thuê gửi</h2><p class="help-text">Ảnh chỉ hiển thị cho người có quyền xem yêu cầu.</p></div><UiButton v-if="entries.some(entry => entry.error)" variant="ghost" @click="load">Tải lại ảnh</UiButton></header>
    <p v-if="!entries.length" class="help-text">Người gửi chưa đính kèm ảnh.</p>
    <ul v-else class="photo-grid">
      <li v-for="(entry, index) in entries" :key="entry.id">
        <p v-if="entry.loading" class="photo-state" role="status">Đang tải ảnh {{ index + 1 }}…</p>
        <p v-else-if="entry.error" class="photo-state">{{ entry.error }}</p>
        <button v-else class="photo-button" :aria-label="`Xem ảnh báo hỏng ${index + 1}`" @click="preview = entry"><img :src="entry.url" :alt="`Ảnh báo hỏng ${index + 1}`" /><span>Ảnh {{ index + 1 }} · Bấm để xem</span></button>
      </li>
    </ul>
    <UiDialog :open="!!preview" title="Ảnh báo hỏng" @update:open="value => { if (!value) preview = null }"><img v-if="preview?.url" class="photo-preview" :src="preview.url" alt="Ảnh báo hỏng phóng to" /><template #actions><UiButton variant="secondary" @click="preview = null">Đóng ảnh</UiButton></template></UiDialog>
  </section>
</template>

<style scoped>
.photo-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; list-style: none; margin: 0; padding: 0; }
.photo-button { width: 100%; padding: 0; overflow: hidden; border: 1px solid var(--color-border); border-radius: 12px; background: var(--color-bg); color: var(--color-primary); }
.photo-button img { display: block; width: 100%; height: 160px; object-fit: cover; }
.photo-button span { display: block; padding: 12px 8px; font-size: 13px; }
.photo-state { display: grid; align-content: center; min-height: 160px; padding: 16px; background: var(--color-bg); border-radius: 12px; font-size: 13px; }
.photo-preview { display: block; max-width: 100%; max-height: 60dvh; margin: 0 auto; object-fit: contain; }
</style>

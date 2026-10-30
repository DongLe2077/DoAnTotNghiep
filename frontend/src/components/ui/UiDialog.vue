<script setup lang="ts">
import { nextTick, onMounted, ref, useId, watch } from 'vue'
import { keepFocusInDialog } from '@/lib/dialog'

const props = defineProps<{ open: boolean; title: string; description?: string }>()
const emit = defineEmits<{ 'update:open': [value: boolean] }>()
const dialog = ref<HTMLDialogElement | null>(null)
const titleId = useId()
const descriptionId = useId()
async function syncOpen() {
  await nextTick()
  if (props.open && !dialog.value?.open) dialog.value?.showModal()
  else if (!props.open && dialog.value?.open) dialog.value.close()
}
watch(() => props.open, syncOpen)
onMounted(syncOpen)
</script>

<template>
  <dialog ref="dialog" class="ui-dialog" :aria-labelledby="titleId" :aria-describedby="description ? descriptionId : undefined" @keydown="keepFocusInDialog" @cancel.prevent="emit('update:open', false)" @close="emit('update:open', false)">
    <header><h2 :id="titleId">{{ title }}</h2><button type="button" class="close" aria-label="Đóng hộp thoại" @click="emit('update:open', false)">×</button></header>
    <p v-if="description" :id="descriptionId" class="description">{{ description }}</p>
    <div class="content"><slot /></div>
    <footer v-if="$slots.actions"><slot name="actions" /></footer>
  </dialog>
</template>

<style scoped>
.ui-dialog { width: min(560px, calc(100% - 32px)); max-height: calc(100dvh - 48px); padding: 24px; border: 1px solid var(--color-border); border-radius: var(--radius-card); color: var(--color-text); background: var(--color-surface); box-shadow: var(--shadow-popover); }
.ui-dialog::backdrop { background: rgb(23 45 34 / 35%); }
@supports (transition-behavior: allow-discrete) {
  .ui-dialog { opacity: 0; transform: translateY(8px); transition: opacity var(--motion-normal, 180ms) var(--motion-ease, ease), transform var(--motion-normal, 180ms) var(--motion-ease, ease), display var(--motion-normal, 180ms) allow-discrete, overlay var(--motion-normal, 180ms) allow-discrete; }
  .ui-dialog[open] { opacity: 1; transform: translateY(0); }
  .ui-dialog::backdrop { background: rgb(23 45 34 / 0%); transition: background var(--motion-normal, 180ms), display var(--motion-normal, 180ms) allow-discrete, overlay var(--motion-normal, 180ms) allow-discrete; }
  .ui-dialog[open]::backdrop { background: rgb(23 45 34 / 35%); }
  @starting-style {
    .ui-dialog[open] { opacity: 0; transform: translateY(8px); }
    .ui-dialog[open]::backdrop { background: rgb(23 45 34 / 0%); }
  }
}
header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
h2 { margin: 4px 0; font-size: 22px; font-weight: 600; }
.close { flex-shrink: 0; width: 44px; height: 44px; border: 0; border-radius: 10px; color: var(--color-text-secondary); background: var(--color-bg); font-size: 26px; line-height: 1; }
.description { margin: 8px 0 24px; color: var(--color-text-secondary); font-size: 14px; }
.content { margin-top: 16px; }
footer { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--color-border); }
@media (max-width: 480px) { .ui-dialog { padding: 20px; } h2 { font-size: 20px; } }
</style>

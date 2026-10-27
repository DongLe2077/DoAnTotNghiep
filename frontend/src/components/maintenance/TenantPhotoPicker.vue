<script setup lang="ts">
import { onBeforeUnmount, ref, useId, watch } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import { photoSelectionError } from '@/lib/tenant-photos'
const props = withDefaults(defineProps<{ modelValue: File[]; existing?: number; disabled?: boolean }>(), { existing: 0, disabled: false })
const emit = defineEmits<{ 'update:modelValue': [files: File[]] }>()
const id = useId(), fileInput = ref<HTMLInputElement | null>(null), error = ref(''), previews = ref<{ url: string; name: string; failed: boolean }[]>([])
function clear() { previews.value.forEach(item => { if (item.url) URL.revokeObjectURL(item.url) }); previews.value = [] }
watch(() => props.modelValue, files => { clear(); previews.value = files.map(file => ({ name: file.name, url: URL.createObjectURL(file), failed: false })) }, { immediate: true })
function choose(event: Event) {
  const input = event.target as HTMLInputElement
  const files = [...props.modelValue, ...Array.from(input.files ?? [])]
  error.value = photoSelectionError(files, props.existing); input.value = ''
  if (!error.value) emit('update:modelValue', files)
}
function remove(index: number) { error.value = ''; emit('update:modelValue', props.modelValue.filter((_, position) => position !== index)) }
onBeforeUnmount(clear)
</script>
<template><section class="tenant-photo-picker"><label :for="id">Ảnh sự cố <span class="help-text">(không bắt buộc)</span></label><p :id="`${id}-hint`" class="help-text">Tối đa 5 ảnh mỗi yêu cầu; JPG/PNG/WebP, tối đa 5 MB và 4096 × 4096 px mỗi ảnh. Đã có {{ existing }}/5 ảnh.</p><input :id="id" ref="fileInput" class="photo-native-input" tabindex="-1" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple :disabled="disabled || existing + modelValue.length >= 5" :aria-describedby="error ? `${id}-error` : `${id}-hint`" :aria-invalid="!!error" @change="choose" /><div class="button-row"><UiButton variant="secondary" :disabled="disabled || existing + modelValue.length >= 5" :aria-describedby="`${id}-hint`" @click="fileInput?.click()">Chọn ảnh từ thiết bị</UiButton><span class="help-text">{{ modelValue.length ? `Đã chọn ${modelValue.length} ảnh` : 'Chưa chọn ảnh' }}</span></div><p v-if="error" :id="`${id}-error`" class="field-error" role="alert">{{ error }}</p><ul v-if="previews.length" class="photo-preview-list"><li v-for="(item, index) in previews" :key="item.url"><img v-if="!item.failed" :src="item.url" :alt="`Ảnh đã chọn ${index + 1}`" @error="item.failed = true" /><p v-else class="help-text">Chưa xem trước được ảnh này.</p><span>{{ item.name }}</span><UiButton variant="ghost" :disabled="disabled" :aria-label="`Bỏ ảnh ${index + 1}`" @click="remove(index)">Bỏ ảnh</UiButton></li></ul></section></template>
<style scoped>
.tenant-photo-picker > label { font-size: 14px; font-weight: 600; }
.photo-native-input { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; pointer-events: none; }
.photo-preview-list { list-style: none; padding: 0; margin: 16px 0 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; }
.photo-preview-list li { min-width: 0; display: grid; align-content: start; padding: 8px; border: 1px solid var(--color-border); border-radius: 12px; }
.photo-preview-list img { width: 100%; height: 120px; object-fit: cover; border-radius: 8px; }
.photo-preview-list li > span { margin: 8px 0; font-size: 12px; overflow-wrap: anywhere; }
</style>

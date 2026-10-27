<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import { useOwnerApi } from '@/composables/useOwnerApi'
import { toApiError } from '@/lib/api'
import type { PageResult } from '@/types/owner'
type Choice = { id: number; label: string }
const props = defineProps<{ modelValue: number[]; label: string; path: string; labelKey: string; multiple?: boolean; filters?: Record<string, unknown>; error?: string; initial?: Choice[]; allowedIds?: number[]; requireAccount?: boolean; anyAccount?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: number[]]; choose: [value: Record<string, unknown>] }>()
const client = useOwnerApi()
const id = useId(), search = ref(''), options = ref<Record<string, unknown>[]>([]), selected = ref<Choice[]>(props.initial ?? [])
const loading = ref(false), error = ref(''), page = ref(1), last = ref(1)
let sequence = 0, mounted = true
const visibleOptions = computed(() => options.value.filter(item => (!props.allowedIds || props.allowedIds.includes(Number(item.id))) && (!props.anyAccount || (item.account && typeof item.account === 'object')) && (!props.requireAccount || (item.account && typeof item.account === 'object' && 'is_active' in item.account && item.account.is_active))))
async function load(next = 1) {
  const request = ++sequence; loading.value = true; error.value = ''
  if (next === 1) options.value = []
  try {
    const result = await client.get<PageResult<Record<string, unknown>>>(props.path, { ...props.filters, q: search.value, per_page: 15, page: next })
    if (!mounted || request !== sequence) return
    options.value = next === 1 ? result.data : [...options.value, ...result.data]
    page.value = result.meta.current_page; last.value = result.meta.last_page
  } catch (caught) { if (mounted && request === sequence) { options.value = []; error.value = toApiError(caught).message } }
  finally { if (mounted && request === sequence) loading.value = false }
}
function choose(item: Record<string, unknown>) {
  const value = Number(item.id)
  const exists = props.modelValue.includes(value)
  if (!props.multiple) selected.value = []
  if (!exists) selected.value = [...selected.value.filter(choice => choice.id !== value), { id: value, label: String(item[props.labelKey]) }]
  const ids = props.multiple ? exists ? props.modelValue.filter(id => id !== value) : [...props.modelValue, value] : exists ? [] : [value]
  selected.value = selected.value.filter(item => ids.includes(item.id))
  emit('update:modelValue', ids); emit('choose', item)
}
watch(() => props.modelValue.slice(), ids => { selected.value = selected.value.filter(choice => ids.includes(choice.id)) })
watch([() => props.path, () => JSON.stringify(props.filters)], () => { void load() }, { immediate: true })
onBeforeUnmount(() => { mounted = false; sequence++ })
</script>
<template>
  <fieldset class="remote-picker"><legend>{{ label }}</legend>
    <div v-if="selected.some(item => modelValue.includes(item.id))" class="picker-selected">Đã chọn: {{ selected.filter(item => modelValue.includes(item.id)).map(item => item.label).join(', ') }} <UiButton variant="ghost" @click="emit('update:modelValue', [])">Bỏ chọn</UiButton></div>
    <div class="picker-search"><label class="sr-only" :for="id">Tìm {{ label.toLocaleLowerCase('vi-VN') }}</label><input :id="id" v-model="search" class="ui-input" placeholder="Nhập tên hoặc mã để tìm" @keydown.enter.prevent="load()" /><UiButton variant="secondary" :loading="loading" @click="load()">Tìm</UiButton></div>
    <p v-if="error" role="alert">{{ error }} <UiButton variant="ghost" @click="load()">Thử lại</UiButton></p>
    <p v-else-if="loading" role="status">Đang tải lựa chọn…</p>
    <p v-else-if="!visibleOptions.length">Không có lựa chọn phù hợp trong các kết quả đã tải{{ page < last ? '; hãy tải thêm hoặc tìm theo tên' : '' }}.</p>
    <div class="picker-options"><label v-for="item in visibleOptions" :key="Number(item.id)"><input :type="multiple ? 'checkbox' : 'radio'" :name="id" :checked="modelValue.includes(Number(item.id))" @change="choose(item)" /><span>{{ item[labelKey] }}<small v-if="item.phone_number"> · {{ item.phone_number }}</small></span></label></div>
    <UiButton v-if="page < last" variant="ghost" :loading="loading" @click="load(page + 1)">Tải thêm lựa chọn</UiButton>
    <p v-if="props.error" class="field-error" role="alert">{{ props.error }}</p>
  </fieldset>
</template>

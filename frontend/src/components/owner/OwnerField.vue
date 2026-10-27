<script setup lang="ts">
import { computed, useId } from 'vue'
import UiField from '@/components/ui/UiField.vue'
const props = withDefaults(defineProps<{ modelValue: string | number; label: string; name: string; errors?: Record<string, string[]>; hint?: string; required?: boolean; type?: string; maxLength?: number; min?: string; max?: string; step?: string }>(), { type: 'text' })
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const id = useId()
const error = computed(() => props.errors?.[props.name]?.[0])
</script>
<template>
  <UiField :id="id" :label="label" :error="error" :hint="hint" :required="required" v-slot="{ describedBy, invalid }">
    <input :id="id" :name="name" class="ui-input" :type="type" :value="modelValue" :required="required" :maxlength="maxLength" :min="min" :max="max" :step="step" :aria-describedby="describedBy" :aria-invalid="invalid" :autocomplete="type === 'password' ? 'new-password' : 'off'" @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)" />
  </UiField>
</template>

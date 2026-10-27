<script setup lang="ts">
withDefaults(defineProps<{ id: string; label: string; hint?: string; error?: string; required?: boolean }>(), { required: false })
</script>

<template>
  <div class="ui-field">
    <label :for="id">{{ label }}<span v-if="required" class="required"> (bắt buộc)</span></label>
    <slot :describedBy="error ? `${id}-error` : hint ? `${id}-hint` : undefined" :invalid="!!error" />
    <p v-if="error" :id="`${id}-error`" class="error" role="alert">{{ error }}</p>
    <p v-else-if="hint" :id="`${id}-hint`" class="hint">{{ hint }}</p>
  </div>
</template>

<style scoped>
.ui-field { display: grid; align-content: start; gap: 8px; min-width: 0; }
label { color: var(--color-text); font-size: 14px; font-weight: 500; }
.required { color: var(--color-text-secondary); font-size: 12px; font-weight: 400; }
p { margin: 0; font-size: 13px; }
.hint { color: var(--color-text-secondary); }
.error { color: var(--color-danger); }
</style>

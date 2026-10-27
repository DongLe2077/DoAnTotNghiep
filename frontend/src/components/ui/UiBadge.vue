<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'neutral' | 'success' | 'warning' | 'danger' | 'info' }>(), { tone: 'neutral' })
</script>

<template>
  <span class="ui-badge" :class="`ui-badge--${tone}`"><span aria-hidden="true" /><slot /></span>
</template>

<style scoped>
.ui-badge { display: inline-flex; align-items: center; gap: 7px; width: fit-content; padding: 5px 10px; border-radius: 6px; color: var(--color-text-secondary); background: #f0f2ee; font-size: 12px; font-weight: 500; line-height: 1.6; white-space: nowrap; }
.ui-badge > span { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.ui-badge--success { color: var(--color-success); background: var(--color-success-soft); }
.ui-badge--warning { color: var(--color-warning); background: var(--color-warning-soft); }
.ui-badge--danger { color: var(--color-danger); background: var(--color-danger-soft); }
.ui-badge--info { color: var(--color-info); background: var(--color-info-soft); }
</style>

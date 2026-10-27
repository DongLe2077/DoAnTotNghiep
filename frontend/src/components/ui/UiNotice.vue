<script setup lang="ts">
withDefaults(defineProps<{ tone?: 'info' | 'success' | 'warning' | 'danger'; title?: string }>(), { tone: 'info' })
</script>

<template>
  <div class="ui-notice" :class="`ui-notice--${tone}`" :role="tone === 'danger' ? 'alert' : 'status'">
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path v-if="tone === 'success'" d="m8 12 3 3 5-6" /><path v-else d="M12 7v6m0 4v.01" /></svg>
    <div><strong v-if="title">{{ title }}</strong><div><slot /></div></div>
  </div>
</template>

<style scoped>
.ui-notice { display: flex; align-items: flex-start; gap: 12px; padding: 16px; border-radius: var(--radius-control); color: var(--color-info); background: var(--color-info-soft); font-size: 14px; }
.ui-notice > svg { flex-shrink: 0; width: 20px; height: 20px; margin-top: 2px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.ui-notice strong { display: block; margin-bottom: 4px; font-weight: 600; }
.ui-notice--success { color: var(--color-success); background: var(--color-success-soft); }
.ui-notice--warning { color: var(--color-warning); background: var(--color-warning-soft); }
.ui-notice--danger { color: var(--color-danger); background: var(--color-danger-soft); }
</style>

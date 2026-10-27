<script setup lang="ts">
withDefaults(defineProps<{ title: string; description?: string; tone?: 'empty' | 'error' | 'loading' }>(), { tone: 'empty' })
</script>

<template>
  <div class="empty-state" :role="tone === 'error' ? 'alert' : 'status'">
    <div v-if="tone === 'loading'" class="skeleton" aria-hidden="true"><span /><span /><span /></div>
    <span v-else class="symbol" :class="{ 'symbol--error': tone === 'error' }" aria-hidden="true"><svg viewBox="0 0 24 24"><path v-if="tone === 'error'" d="M12 8v5m0 4v.01M3 20h18L12 3 3 20Z" /><path v-else d="M4 4h16v16H4V4Zm4 5h8m-8 5h5" /></svg></span>
    <h3>{{ title }}</h3><p v-if="description">{{ description }}</p>
    <div v-if="$slots.default" class="actions"><slot /></div>
  </div>
</template>

<style scoped>
.empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 220px; padding: 32px 24px; text-align: center; }
.symbol { display: grid; place-items: center; width: 48px; height: 48px; margin-bottom: 16px; color: var(--color-primary); background: var(--color-primary-soft); border-radius: 12px; }
.symbol--error { color: var(--color-danger); background: var(--color-danger-soft); }
.symbol svg { width: 24px; height: 24px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
h3 { margin: 0 0 8px; font-size: 16px; font-weight: 600; }
p { max-width: 400px; margin: 0; color: var(--color-text-secondary); font-size: 14px; }
.actions { margin-top: 20px; }
.skeleton { display: grid; gap: 8px; width: min(100%, 240px); margin-bottom: 20px; }
.skeleton span { height: 12px; background: #e5ebe3; border-radius: 4px; }
.skeleton span:last-child { width: 65%; }
</style>

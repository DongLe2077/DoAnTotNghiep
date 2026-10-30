<script setup lang="ts">
withDefaults(defineProps<{ layout?: 'dashboard' | 'cards' | 'list' }>(), { layout: 'cards' })
</script>

<template>
  <div class="ui-skeleton" :class="`ui-skeleton--${layout}`" aria-hidden="true">
    <div v-for="item in layout === 'dashboard' ? 3 : layout === 'list' ? 4 : 2" :key="item" class="skeleton-card">
      <span class="skeleton-line skeleton-line--label" /><span class="skeleton-line skeleton-line--value" /><span class="skeleton-line skeleton-line--detail" />
    </div>
    <div v-if="layout === 'dashboard'" class="skeleton-chart"><span class="skeleton-line skeleton-line--label" /><div class="skeleton-columns"><span v-for="bar in 6" :key="bar" :style="{ height: `${28 + (bar % 3) * 19}%` }" /></div></div>
  </div>
</template>

<style scoped>
.ui-skeleton { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; width: 100%; text-align: left; }
.ui-skeleton--dashboard { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.ui-skeleton--list { grid-template-columns: 1fr; }
.skeleton-card, .skeleton-chart { border: 1px solid var(--color-border); border-radius: var(--radius-card); padding: 24px; background: var(--color-surface); }
.skeleton-line { display: block; border-radius: 5px; background: #e8eee8; }
.skeleton-line--label { width: 55%; height: 14px; }
.skeleton-line--value { width: 70%; height: 32px; margin: 20px 0; }
.skeleton-line--detail { width: 88%; height: 12px; }
.skeleton-chart { grid-column: 1 / -1; }
.skeleton-columns { display: flex; align-items: end; gap: 7%; height: 160px; border-bottom: 1px solid var(--color-border); margin-top: 20px; }
.skeleton-columns > span { flex: 1; background: #e8eee8; border-radius: 5px 5px 0 0; }
@media (prefers-reduced-motion: no-preference) { .skeleton-line, .skeleton-columns > span { animation: skeleton-pulse 1.6s ease-in-out infinite alternate; } }
@keyframes skeleton-pulse { from { opacity: .5; } to { opacity: 1; } }
@media (max-width: 700px) { .ui-skeleton, .ui-skeleton--dashboard { grid-template-columns: 1fr; } .skeleton-card, .skeleton-chart { padding: 20px; } }
</style>

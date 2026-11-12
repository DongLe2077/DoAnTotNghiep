<script setup lang="ts">
withDefaults(defineProps<{ layout?: 'dashboard' | 'cards' | 'list' | 'invoices' | 'invoice-detail' | 'report-form' }>(), { layout: 'cards' })
</script>

<template>
  <div class="ui-skeleton" :class="`ui-skeleton--${layout}`" aria-hidden="true">
    <div v-for="item in layout === 'dashboard' || layout === 'list' ? 4 : layout === 'invoices' ? 3 : layout === 'report-form' ? 1 : 2" :key="item" class="skeleton-card">
      <span class="skeleton-line skeleton-line--label" /><span class="skeleton-line skeleton-line--value" /><span class="skeleton-line skeleton-line--detail" />
      <span v-if="layout === 'report-form'" class="skeleton-line skeleton-line--field" />
    </div>
  </div>
</template>

<style scoped>
.ui-skeleton { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; width: 100%; text-align: left; }
.ui-skeleton--dashboard, .ui-skeleton--invoices, .ui-skeleton--invoice-detail { gap: 14px; }
.ui-skeleton--list, .ui-skeleton--invoices, .ui-skeleton--report-form { grid-template-columns: 1fr; }
.skeleton-card { border: 1px solid var(--color-border); border-radius: var(--radius-card); padding: 24px; background: var(--color-surface); }
.skeleton-line { display: block; border-radius: 5px; background: var(--color-border); }
.skeleton-line--label { width: 55%; height: 14px; }
.skeleton-line--value { width: 70%; height: 32px; margin: 20px 0; }
.skeleton-line--detail { width: 88%; height: 12px; }
.ui-skeleton--dashboard .skeleton-card { min-height: 176px; }
.ui-skeleton--invoices .skeleton-card { min-height: 206px; }
.ui-skeleton--invoice-detail .skeleton-card { min-height: 225px; }
.skeleton-line--field { width: 100%; height: 112px; margin-top: 20px; }
@media (prefers-reduced-motion: no-preference) { .skeleton-line { animation: skeleton-pulse 1.6s ease-in-out infinite alternate; } }
@keyframes skeleton-pulse { from { opacity: .5; } to { opacity: 1; } }
@media (max-width: 700px) { .ui-skeleton { grid-template-columns: 1fr; } .skeleton-card { padding: 20px; } }
</style>

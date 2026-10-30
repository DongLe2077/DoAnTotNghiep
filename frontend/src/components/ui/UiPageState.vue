<script setup lang="ts">
import { computed, useId } from 'vue'
import UiSkeleton from '@/components/ui/UiSkeleton.vue'

type PageStateKind = 'loading' | 'empty' | 'error' | 'forbidden' | 'not-found'

const props = withDefaults(defineProps<{
  kind?: PageStateKind
  title?: string
  description?: string
  headingLevel?: 'h1' | 'h2' | 'h3'
  skeleton?: 'dashboard' | 'cards' | 'list'
}>(), { kind: 'empty', headingLevel: 'h2' })

const defaults: Record<PageStateKind, { title: string; description: string }> = {
  loading: { title: 'Đang tải thông tin…', description: 'Vui lòng chờ một chút.' },
  empty: { title: 'Chưa có thông tin', description: 'Thông tin sẽ xuất hiện tại đây khi có dữ liệu.' },
  error: { title: 'Chưa thể tải thông tin', description: 'Bạn có thể thử lại sau ít phút.' },
  forbidden: { title: 'Bạn không có quyền truy cập', description: 'Nội dung này chưa được cấp quyền cho tài khoản của bạn.' },
  'not-found': { title: 'Không tìm thấy trang', description: 'Đường dẫn có thể đã thay đổi hoặc không còn được sử dụng.' },
}
const titleText = computed(() => props.title ?? defaults[props.kind].title)
const descriptionText = computed(() => props.description ?? defaults[props.kind].description)
const headingId = `page-state-${useId()}`
</script>

<template>
  <section class="page-state" :class="[`page-state--${kind}`, { 'page-state--skeleton': kind === 'loading' && skeleton }]" :aria-labelledby="headingId" :role="kind === 'error' ? 'alert' : kind === 'loading' ? 'status' : undefined" aria-live="polite">
    <span v-if="kind !== 'loading' || !skeleton" class="page-state__symbol" aria-hidden="true">
      <svg v-if="kind === 'loading'" class="page-state__spinner" viewBox="0 0 32 32"><circle cx="16" cy="16" r="11" /><path d="M16 5a11 11 0 0 1 11 11" /></svg>
      <svg v-else viewBox="0 0 32 32">
        <template v-if="kind === 'forbidden'"><rect x="7" y="14" width="18" height="14" rx="3" /><path d="M11 14V9a5 5 0 0 1 10 0v5m-5 6v3" /></template>
        <template v-else-if="kind === 'not-found'"><path d="M19 5H8a2 2 0 0 0-2 2v19a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V12L19 5Zm0 0v7h7M11 18h5m-5 5h8" /><circle cx="24" cy="22" r="5" /><path d="m28 26 3 3" /></template>
        <template v-else-if="kind === 'error'"><path d="M16 11v7m0 5v.1M13.5 5.5 3 24a2 2 0 0 0 1.7 3h22.6a2 2 0 0 0 1.7-3L18.5 5.5a2.9 2.9 0 0 0-5 0Z" /></template>
        <template v-else><rect x="6" y="5" width="20" height="23" rx="3" /><path d="M11 12h10m-10 5h10m-10 5h6" /></template>
      </svg>
    </span>
    <component :is="headingLevel" :id="headingId" class="page-state__title">{{ titleText }}</component>
    <p v-if="descriptionText" class="page-state__description">{{ descriptionText }}</p>
    <UiSkeleton v-if="kind === 'loading' && skeleton" :layout="skeleton" />
    <div v-if="$slots.actions || $slots.default" class="page-state__actions"><slot name="actions"><slot /></slot></div>
  </section>
</template>

<style scoped>
.page-state { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 260px; padding: 40px 24px; text-align: center; color: var(--color-text); }
.page-state__symbol { display: grid; place-items: center; flex-shrink: 0; width: 64px; height: 64px; margin-bottom: 24px; border-radius: 18px; color: var(--color-primary); background: var(--color-primary-soft); }
.page-state__symbol svg { width: 32px; height: 32px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.page-state--error .page-state__symbol { color: var(--color-danger); background: var(--color-danger-soft); }
.page-state__title { max-width: 620px; margin: 0; font-size: 24px; font-weight: 600; line-height: 1.45; letter-spacing: -.4px; overflow-wrap: anywhere; }
.page-state__description { max-width: 480px; margin: 12px 0 0; color: var(--color-text-secondary); font-size: 15px; line-height: 1.8; overflow-wrap: anywhere; }
.page-state__actions { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 12px; width: 100%; margin-top: 28px; }
.page-state__spinner { animation: state-spin 1s linear infinite; }
.page-state__spinner circle { opacity: .18; }
.page-state--skeleton { padding: 0; align-items: stretch; text-align: left; gap: 12px; }
.page-state--skeleton .page-state__title { font-size: 17px; letter-spacing: 0; }
.page-state--skeleton .page-state__description { margin: 0 0 8px; }
@keyframes state-spin { to { transform: rotate(360deg); } }
@media (max-width: 560px) { .page-state { padding: 32px 20px; } .page-state__title { font-size: 22px; } .page-state__actions { flex-direction: column; align-items: stretch; } }
@media (prefers-reduced-motion: reduce) { .page-state__spinner { animation: none; } }
</style>

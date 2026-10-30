<script setup lang="ts">
withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger'
  type?: 'button' | 'submit' | 'reset'
  loading?: boolean
  disabled?: boolean
}>(), { variant: 'primary', type: 'button', loading: false, disabled: false })
</script>

<template>
  <button :type="type" class="ui-button" :class="`ui-button--${variant}`" :disabled="disabled || loading" :aria-busy="loading || undefined">
    <span v-if="loading" class="ui-button__spinner" aria-hidden="true" /><slot />
  </button>
</template>

<style scoped>
.ui-button { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; padding: 11px 18px; border: 1px solid transparent; border-radius: var(--radius-control); font-size: 14px; font-weight: 600; line-height: 1.5; text-decoration: none; transition: background var(--motion-fast) ease, border-color var(--motion-fast) ease, box-shadow var(--motion-fast) ease; }
.ui-button:active:not(:disabled) { box-shadow: inset 0 0 0 2px rgb(0 0 0 / 10%); }
.ui-button--primary { color: #fff; background: var(--color-primary); }
.ui-button--primary:hover:not(:disabled) { background: var(--color-primary-hover); }
.ui-button--secondary { color: var(--color-primary); background: var(--color-surface); border-color: var(--color-input-border); }
.ui-button--secondary:hover:not(:disabled), .ui-button--ghost:hover:not(:disabled) { background: var(--color-primary-soft); }
.ui-button--ghost { color: var(--color-primary); background: transparent; }
.ui-button--danger { color: #fff; background: var(--color-danger); }
.ui-button--danger:hover:not(:disabled) { background: #8b3028; }
.ui-button:disabled { opacity: .6; }
.ui-button__spinner { width: 16px; height: 16px; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: button-spin .7s linear infinite; }
@keyframes button-spin { to { transform: rotate(360deg); } }
@media (max-width: 640px) { .ui-button { min-height: 48px; } }
</style>

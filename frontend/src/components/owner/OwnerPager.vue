<script setup lang="ts">
import UiButton from '@/components/ui/UiButton.vue'
defineProps<{ page: number; lastPage: number; total: number; loading?: boolean }>()
defineEmits<{ change: [page: number] }>()
</script>
<template><nav class="owner-pagination" aria-label="Phân trang"><span>{{ total }} kết quả · Trang {{ page }} / {{ lastPage }}</span><div class="button-row"><UiButton variant="secondary" :disabled="loading || page <= 1" @click="$emit('change', page - 1)">Trang trước</UiButton><UiButton variant="secondary" :disabled="loading || page >= lastPage" @click="$emit('change', page + 1)">Trang sau</UiButton></div></nav></template>

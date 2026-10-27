<script setup lang="ts">
import { RouterView } from 'vue-router'
import UiPageState from '@/components/ui/UiPageState.vue'
</script>

<template>
  <RouterView v-slot="{ Component }">
    <component :is="Component" v-if="Component" />
    <main v-else class="boot-screen" aria-live="polite">
      <UiPageState kind="loading" title="Đang mở không gian của bạn…" description="Đang kiểm tra phiên đăng nhập và chuẩn bị nội dung." />
    </main>
  </RouterView>
</template>

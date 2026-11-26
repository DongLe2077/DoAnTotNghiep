<script setup lang="ts">
import { RouterView, useRoute } from 'vue-router'
import UiPageState from '@/components/ui/UiPageState.vue'
import PwaStatus from '@/components/pwa/PwaStatus.vue'
import { updateLocked } from '@/lib/reload-safety'

const route = useRoute()
</script>

<template>
  <PwaStatus v-if="route.name !== 'redesign-preview'" />
  <div :inert="updateLocked ? true : undefined">
  <RouterView v-slot="{ Component }">
    <component :is="Component" v-if="Component" />
    <main v-else class="boot-screen rb7-theme" aria-live="polite">
      <UiPageState kind="loading" title="Đang mở không gian của bạn…" description="Đang kiểm tra phiên đăng nhập và chuẩn bị nội dung." />
    </main>
  </RouterView>
  </div>
</template>

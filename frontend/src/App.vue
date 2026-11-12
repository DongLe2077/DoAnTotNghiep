<script setup lang="ts">
import { RouterView } from 'vue-router'
import UiPageState from '@/components/ui/UiPageState.vue'
import PwaStatus from '@/components/pwa/PwaStatus.vue'
import { updateLocked } from '@/lib/reload-safety'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <PwaStatus :class="{ 'rentbase-graphite': auth.isAuthenticated }" />
  <div :inert="updateLocked ? true : undefined">
  <RouterView v-slot="{ Component }">
    <component :is="Component" v-if="Component" />
    <main v-else class="boot-screen" aria-live="polite">
      <UiPageState kind="loading" title="Đang mở không gian của bạn…" description="Đang kiểm tra phiên đăng nhập và chuẩn bị nội dung." />
    </main>
  </RouterView>
  </div>
</template>

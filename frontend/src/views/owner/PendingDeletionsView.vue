<script setup lang="ts">
import { ref } from 'vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import ScheduledDeletionPurgeDialog from '@/components/owner/ScheduledDeletionPurgeDialog.vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import { useOwnerApi, useOwnerList } from '@/composables/useOwnerApi'
import { formatDateTime } from '@/lib/notifications'

type Job = { id: number; label: string; purge_at: string; problem: string | null; cleaning_photos: boolean; impact: { label: string; count: number }[] }
const { items, loading, loadError, page, lastPage, total, load } = useOwnerList<Job>('/scheduled-deletions')
const { write, busy, error, clearError } = useOwnerApi()
const notice = ref(window.history.state?.deletionConfirmed === true ? 'Đã xóa vĩnh viễn mục đã chọn.' : '')
if (notice.value) window.history.replaceState({ ...window.history.state, deletionConfirmed: null }, '')
const purgeJobId = ref<number | null>(null)

async function cancel(job: Job) {
  if (busy.value) return
  notice.value = ''
  try {
    await write('delete', '/scheduled-deletions/' + job.id)
    notice.value = 'Đã hủy xóa ' + job.label + '.'
    await load()
  } catch { /* The API error is displayed above the list. */ }
}

function openPurge(id: number) {
  notice.value = ''
  clearError()
  purgeJobId.value = id
}

async function onPurged(label: string) {
  purgeJobId.value = null
  notice.value = 'Đã xóa vĩnh viễn ' + label + '.'
  await load()
}

void load()
</script>

<template>
  <section class="management-page">
    <header class="management-header">
      <div>
        <h1>Chờ xóa</h1>
        <p>Dữ liệu được lên lịch xóa sau 3 ngày. Bạn có thể hủy lịch hoặc xác nhận xóa ngay.</p>
      </div>
      <UiButton variant="secondary" :loading="loading" @click="load()">Làm mới</UiButton>
    </header>
    <UiNotice v-if="notice" tone="success">{{ notice }}</UiNotice>
    <UiNotice v-if="error" tone="danger">{{ error }}</UiNotice>
    <UiPageState v-if="loading" kind="loading" />
    <UiPageState v-else-if="loadError" kind="error" :description="loadError" />
    <UiPageState v-else-if="!items.length" title="Chưa có mục chờ xóa" />
    <div v-else class="rb7-record-list">
      <article v-for="job in items" :key="job.id" class="record-card rb7-record-row">
        <div class="deletion-summary">
          <h2>{{ job.label }}</h2>
          <p v-if="job.cleaning_photos" class="help-text">Đã xóa dữ liệu, đang dọn ảnh.</p>
          <p v-else-if="job.problem" class="form-error">{{ job.problem }}</p>
          <p v-else class="help-text">Thời điểm xóa <time :datetime="job.purge_at">{{ formatDateTime(job.purge_at) }}</time></p>
        </div>
        <ul class="deletion-impact" aria-label="Phạm vi đã lên lịch">
          <li v-for="item in job.impact" :key="item.label">{{ item.label }} · {{ item.count }}</li>
        </ul>
        <footer v-if="!job.cleaning_photos">
          <UiButton variant="secondary" :disabled="busy" @click="cancel(job)">Hủy xóa</UiButton>
          <UiButton v-if="!job.problem" variant="danger" :disabled="busy" @click="openPurge(job.id)">Xóa vĩnh viễn ngay</UiButton>
        </footer>
      </article>
    </div>
    <OwnerPager v-if="total" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" />
    <ScheduledDeletionPurgeDialog :job-id="purgeJobId" @close="purgeJobId = null" @deleted="onPurged" />
  </section>
</template>

<style scoped>
.deletion-summary h2 { margin: 0 0 5px; }
.deletion-summary p { margin: 0; }
.deletion-impact { display: flex; flex-wrap: wrap; gap: 6px; padding: 0; list-style: none; }
.deletion-impact li {
  padding: 3px 8px;
  border-radius: 6px;
  color: var(--color-primary);
  background: var(--color-primary-soft);
  font-size: 13px;
}
</style>

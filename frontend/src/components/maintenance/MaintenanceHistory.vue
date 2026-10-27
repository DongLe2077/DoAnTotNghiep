<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import { useOwnerApi } from '@/composables/useOwnerApi'
import { useTenantApi } from '@/composables/useTenantApi'
import { useAuthStore } from '@/stores/auth'
import { toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import { maintenanceStatusLabel, type MaintenanceHistoryEntry } from '@/types/maintenance'
import type { PageResult } from '@/types/owner'

const props = withDefaults(defineProps<{ requestId: number; audience?: 'owner' | 'tenant'; refreshKey?: string }>(), { audience: 'owner', refreshKey: '' })
const client = props.audience === 'tenant' ? useTenantApi() : useOwnerApi(), auth = useAuthStore()
const items = ref<MaintenanceHistoryEntry[]>([]), loading = ref(false), error = ref(''), status = ref(0)
const page = ref(1), lastPage = ref(1), total = ref(0), baseline = ref<string | null>(null)
let alive = true, sequence = 0
function clear() { sequence++; items.value = []; baseline.value = null; total.value = 0; error.value = '' }
watch(() => auth.user ? `${auth.user.id}:${auth.user.role}` : '', clear, { flush: 'sync' })
onBeforeUnmount(() => { alive = false; clear() })
async function load(next = page.value) {
  const current = ++sequence
  items.value = []; baseline.value = null; error.value = ''; status.value = 0; loading.value = true
  try {
    const result = await client.get<PageResult<MaintenanceHistoryEntry> & { history_context: { baseline_recorded_at: string | null } }>(`/maintenance-requests/${props.requestId}/history`, { page: next, per_page: 10 })
    if (!alive || current !== sequence) return
    if (next > result.meta.last_page) { await load(result.meta.last_page); return }
    items.value = result.data; page.value = result.meta.current_page; lastPage.value = result.meta.last_page; total.value = result.meta.total
    baseline.value = result.history_context.baseline_recorded_at
  } catch (caught) { if (alive && current === sequence) { const normalized = toApiError(caught); error.value = normalized.message; status.value = normalized.status } }
  finally { if (alive && current === sequence) loading.value = false }
}
watch([() => props.requestId, () => props.refreshKey], () => { void load(1) }, { immediate: true })
function actionLabel(item: MaintenanceHistoryEntry) {
  return item.action === 'submitted' ? 'Đã gửi yêu cầu' : item.action === 'baseline' ? 'Mốc bắt đầu lưu lịch sử' : 'Cập nhật xử lý'
}
</script>

<template>
  <section class="record-card maintenance-history" aria-label="Lịch sử xử lý sửa chữa">
    <header class="history-heading"><div><h2>Lịch sử xử lý</h2><p class="help-text">Các lần cập nhật được giữ lại, mới nhất ở trên.</p></div><UiButton variant="secondary" :loading="loading" @click="load()">Làm mới lịch sử</UiButton></header>
    <UiPageState v-if="loading" kind="loading" heading-level="h3" title="Đang tải lịch sử xử lý…" />
    <UiPageState v-else-if="error" :kind="[403, 404].includes(status) ? 'not-found' : 'error'" heading-level="h3" :description="[403, 404].includes(status) ? 'Bạn không có quyền xem lịch sử yêu cầu này hoặc yêu cầu không còn khả dụng.' : error"><UiButton v-if="![403, 404].includes(status)" @click="load()">Thử lại lịch sử</UiButton></UiPageState>
    <template v-else>
      <UiNotice v-if="baseline" tone="info">Lịch sử chi tiết được lưu từ {{ formatDateTime(baseline) }}. Những cập nhật trước mốc này chỉ còn trạng thái và ghi chú gần nhất.</UiNotice>
      <UiPageState v-if="!items.length" heading-level="h3" title="Chưa có lịch sử được ghi nhận" description="Các lần cập nhật xử lý tiếp theo sẽ xuất hiện tại đây." />
      <ol v-else class="history-list" aria-label="Dòng thời gian xử lý">
        <li v-for="item in items" :key="item.id"><article>
          <header><h3>{{ actionLabel(item) }}</h3><time>{{ formatDateTime(item.created_at) }}</time></header>
          <p class="history-actor">{{ item.actor ? `Người thực hiện: ${item.actor.label}` : 'Ghi nhận trạng thái có sẵn; chưa xác định người thực hiện các cập nhật trước đó.' }}</p>
          <p class="history-status">{{ item.before.status ? `${maintenanceStatusLabel(item.before.status)} → ` : '' }}{{ maintenanceStatusLabel(item.after.status) }}</p>
          <p v-if="item.after.resolution" class="history-note">{{ `Ghi chú: ${item.after.resolution}` }}</p>
          <p v-else-if="item.before.resolution" class="history-note">Đã xóa nội dung xử lý trước đó.</p>
          <details v-if="item.before.resolution && item.before.resolution !== item.after.resolution"><summary>Ghi chú trước khi cập nhật</summary><p class="history-note">{{ `Ghi chú trước: ${item.before.resolution}` }}</p></details>
        </article></li>
      </ol>
      <OwnerPager v-if="total > 10" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" />
    </template>
  </section>
</template>

<style scoped>
.history-heading { flex-wrap: wrap; }
.history-heading h2 { margin-bottom: 6px; }
.history-list { list-style: none; padding: 0; margin: 20px 0 0; }
.history-list > li { position: relative; padding: 0 0 28px 24px; border-left: 2px solid var(--color-border); margin-left: 5px; }
.history-list > li::before { content: ''; position: absolute; left: -6px; top: 8px; width: 10px; height: 10px; border-radius: 50%; background: var(--color-primary); }
.history-list > li:last-child { padding-bottom: 0; }
.history-list article > header { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 8px 20px; }
.history-list h3 { margin: 0; font-size: 16px; }
.history-list time, .history-actor { color: var(--color-text-secondary); font-size: 13px; }
.history-actor { margin: 8px 0; }
.history-status { color: var(--color-primary); font-size: 14px; margin: 10px 0; }
.history-note { white-space: pre-wrap; overflow-wrap: anywhere; font-size: 14px; line-height: 1.8; }
details { margin-top: 12px; }
summary { cursor: pointer; color: var(--color-text-secondary); font-size: 13px; padding: 6px 0; }
@media (max-width: 600px) { .history-list > li { padding-left: 18px; } }
</style>

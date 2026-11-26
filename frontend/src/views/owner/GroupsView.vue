<script setup lang="ts">
import ScheduledDeleteButton from '@/components/owner/ScheduledDeleteButton.vue'
import { ref } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import UiDialog from '@/components/ui/UiDialog.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import OwnerField from '@/components/owner/OwnerField.vue'
import { useOwnerApi, useOwnerList } from '@/composables/useOwnerApi'
import type { Group } from '@/types/owner'
const { write, busy, error, errors, clearError } = useOwnerApi()
const { items, loading, loadError, page, lastPage, total, load } = useOwnerList<Group>('/rental-groups')
const search = ref(''), open = ref(false), selected = ref<Group | null>(null), code = ref('')
function edit(group: Group | null) { selected.value = group; code.value = group?.group_code ?? ''; clearError(); open.value = true }
async function save() {
  if (busy.value) return
  const group = selected.value
  try {
    await write(group ? 'put' : 'post', group ? `/rental-groups/${group.id}` : '/rental-groups', { group_code: code.value })
    open.value = false
    await load()
  } catch { /* Keep the dialog open with the API error. */ }
}
void load()
</script>
<template><section class="management-page"><RouterLink class="back-link" to="/owner/stays">← Quá trình thuê</RouterLink><header class="management-header"><div><h1>Nhóm thuê</h1><p>Nhóm có thể gồm một người. Các thành viên được ghi theo từng đợt thuê.</p></div><UiButton @click="edit(null)">Tạo nhóm thuê</UiButton></header><form class="management-toolbar" @submit.prevent="load(1, { q: search })"><label>Tìm mã nhóm<input v-model="search" class="ui-input" maxlength="100" /></label><UiButton type="submit" variant="secondary">Tìm</UiButton></form><UiPageState v-if="loading" kind="loading" /><UiPageState v-else-if="loadError" kind="error" :description="loadError"><UiButton @click="load()">Tải lại</UiButton></UiPageState><UiPageState v-else-if="!items.length" title="Chưa có nhóm phù hợp" /><div v-else class="management-grid"><article v-for="group in items" :key="group.id" class="record-card"><h2>{{ group.group_code }}</h2><p class="help-text">{{ group.history_count }} đợt thuê</p><footer class="button-row">
  <UiButton variant="secondary" @click="edit(group)">Sửa nhóm thuê {{ group.group_code }}</UiButton>
  <ScheduledDeleteButton kind="groups" :id="group.id" :label="group.group_code" button-label="Xóa nhóm thuê" variant="ghost" />
</footer></article></div><OwnerPager v-if="total" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" /><UiDialog :open="open" :title="selected ? 'Sửa nhóm thuê' : 'Tạo nhóm thuê'" @update:open="value => { if (!busy) open = value }"><form class="form-grid" @submit.prevent="save"><p v-if="error" class="form-error" role="alert">{{ error }}</p><OwnerField v-model="code" label="Mã nhóm" name="group_code" :errors="errors" :max-length="40" required /><div class="button-row"><UiButton type="submit" :loading="busy">Lưu nhóm</UiButton><ScheduledDeleteButton v-if="selected" kind="groups" :id="selected.id" :label="selected.group_code" button-label="Xóa nhóm thuê" /></div></form><template #actions><UiButton variant="secondary" :disabled="busy" @click="open = false">Đóng</UiButton></template></UiDialog></section></template>

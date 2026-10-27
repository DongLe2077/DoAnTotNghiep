<script setup lang="ts">
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
const search = ref(''), open = ref(false), selected = ref<Group | null>(null), code = ref(''), deleting = ref(false)
function edit(group: Group | null) { selected.value = group; code.value = group?.group_code ?? ''; deleting.value = false; clearError(); open.value = true }
async function save() { try { await write(deleting.value ? 'delete' : selected.value ? 'put' : 'post', selected.value ? `/rental-groups/${selected.value.id}` : '/rental-groups', deleting.value ? undefined : { group_code: code.value }); open.value = false; await load() } catch { /* Surface validation/conflict. */ } }
void load()
</script>
<template><section class="management-page"><RouterLink class="back-link" to="/owner/stays">← Quá trình thuê</RouterLink><header class="management-header"><div><h1>Nhóm thuê</h1><p>Nhóm có thể gồm một người. Các thành viên được ghi theo từng đợt thuê.</p></div><UiButton @click="edit(null)">Tạo nhóm thuê</UiButton></header><form class="management-toolbar" @submit.prevent="load(1, { q: search })"><label>Tìm mã nhóm<input v-model="search" class="ui-input" maxlength="100" /></label><UiButton type="submit" variant="secondary">Tìm</UiButton></form><UiPageState v-if="loading" kind="loading" /><UiPageState v-else-if="loadError" kind="error" :description="loadError"><UiButton @click="load()">Thử lại</UiButton></UiPageState><UiPageState v-else-if="!items.length" title="Chưa có nhóm phù hợp" /><div v-else class="management-grid"><article v-for="group in items" :key="group.id" class="record-card"><h2>{{ group.group_code }}</h2><p class="help-text">{{ group.history_count }} đợt thuê</p><UiButton variant="secondary" @click="edit(group)">Quản lý nhóm {{ group.group_code }}</UiButton></article></div><OwnerPager v-if="total" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" /><UiDialog :open="open" :title="deleting ? 'Xóa nhóm thuê' : selected ? 'Sửa nhóm thuê' : 'Tạo nhóm thuê'" @update:open="value => { if (!busy) open = value }"><form class="form-grid" @submit.prevent="save"><p v-if="error" class="form-error" role="alert">{{ error }}</p><p v-if="deleting">Xóa nhóm {{ selected?.group_code }} chưa có lịch sử thuê?</p><OwnerField v-else v-model="code" label="Mã nhóm" name="group_code" :errors="errors" :max-length="40" required /><div class="button-row"><UiButton type="submit" :variant="deleting ? 'danger' : 'primary'" :loading="busy">{{ deleting ? 'Xác nhận xóa nhóm' : 'Lưu nhóm' }}</UiButton><UiButton v-if="selected?.can_delete && !deleting" variant="danger" :disabled="busy" @click="deleting = true; clearError()">Xóa nhóm</UiButton></div></form><template #actions><UiButton variant="secondary" :disabled="busy" @click="open = false">Đóng</UiButton></template></UiDialog></section></template>

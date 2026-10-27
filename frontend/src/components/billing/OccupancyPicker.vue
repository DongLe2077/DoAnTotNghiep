<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue'
import UiButton from '@/components/ui/UiButton.vue'
import RemotePicker from '@/components/owner/RemotePicker.vue'
import { useOwnerApi } from '@/composables/useOwnerApi'
import { toApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/notifications'
import type { Occupancy, PageResult } from '@/types/owner'
const props = defineProps<{ modelValue: number | null }>()
const emit = defineEmits<{ 'update:modelValue': [value: number | null]; choose: [value: Occupancy] }>()
const client = useOwnerApi(), id = useId(), rooms = ref<number[]>([]), status = ref(''), items = ref<Occupancy[]>([]), loading = ref(false), error = ref(''), page = ref(1), last = ref(1)
const selected = ref<Occupancy | null>(null), expanded = ref(!props.modelValue)
let sequence = 0, detailSequence = 0, alive = true
const label = computed(() => selected.value ? `Phòng ${selected.value.room.room_number} · Nhóm ${selected.value.group.group_code} · từ ${formatDateTime(selected.value.started_at)}` : '')
async function load(next = 1) { const current = ++sequence; loading.value = true; error.value = ''; items.value = []; try { const result = await client.get<PageResult<Occupancy>>('/occupancies', { per_page: 15, page: next, ...(rooms.value[0] ? { room_id: rooms.value[0] } : {}), ...(status.value ? { status: status.value } : {}) }); if (alive && current === sequence) { items.value = result.data; page.value = result.meta.current_page; last.value = result.meta.last_page } } catch (caught) { if (alive && current === sequence) error.value = toApiError(caught).message } finally { if (alive && current === sequence) loading.value = false } }
function choose(item: Occupancy) { selected.value = item; emit('update:modelValue', item.id); emit('choose', item); expanded.value = false }
watch(() => props.modelValue, async value => { const current = ++detailSequence; if (!value) { selected.value = null; expanded.value = true; return } if (selected.value?.id === value) return; error.value = ''; selected.value = null; try { const result = await client.get<{ data: Occupancy }>(`/occupancies/${value}`); if (alive && current === detailSequence && props.modelValue === value) { selected.value = result.data; emit('choose', result.data) } } catch (caught) { if (alive && current === detailSequence) error.value = toApiError(caught).message } }, { immediate: true })
void load()
onBeforeUnmount(() => { alive = false; sequence++; detailSequence++ })
</script>
<template><fieldset class="remote-picker"><legend>Đợt thuê cần tính tiền</legend><div v-if="selected" class="picker-selected">{{ label }} <UiButton variant="ghost" @click="expanded = !expanded">Đổi đợt thuê</UiButton></div><div v-if="expanded" class="form-grid"><RemotePicker v-model="rooms" label="Tìm phòng" path="/rooms" label-key="room_number" /><label>Trạng thái đợt thuê<select v-model="status" class="ui-select"><option value="">Tất cả</option><option value="active">Đang thuê</option><option value="ended">Đã kết thúc</option></select></label><UiButton variant="secondary" :loading="loading" @click="load()">Tìm đợt thuê</UiButton><p v-if="loading" role="status">Đang tải đợt thuê…</p><p v-else-if="!items.length && !error">Chưa có đợt thuê phù hợp.</p><div class="picker-options"><label v-for="item in items" :key="item.id"><input type="radio" :name="id" :checked="modelValue === item.id" @change="choose(item)" /><span>Phòng {{ item.room.room_number }} · Nhóm {{ item.group.group_code }}<small> · {{ formatDateTime(item.started_at) }} · {{ item.is_active ? 'Đang thuê' : 'Đã kết thúc' }}</small></span></label></div><div class="button-row"><UiButton variant="ghost" :disabled="loading || page <= 1" @click="load(page - 1)">Đợt trước</UiButton><span>{{ page }} / {{ last }}</span><UiButton variant="ghost" :disabled="loading || page >= last" @click="load(page + 1)">Đợt sau</UiButton></div></div><p v-if="error" class="field-error" role="alert">{{ error }} <UiButton variant="ghost" @click="load()">Thử lại</UiButton></p></fieldset></template>

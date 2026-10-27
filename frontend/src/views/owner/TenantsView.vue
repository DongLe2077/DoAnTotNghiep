<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import UiButton from '@/components/ui/UiButton.vue'
import UiBadge from '@/components/ui/UiBadge.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import OwnerPager from '@/components/owner/OwnerPager.vue'
import TenantEditor from '@/components/owner/TenantEditor.vue'
import { useOwnerList } from '@/composables/useOwnerApi'
import type { Tenant } from '@/types/owner'
const router = useRouter(), search = ref(''), residing = ref(''), creating = ref(false)
const { items, loading, loadError, page, lastPage, total, load } = useOwnerList<Tenant>('/tenants')
function filter() { void load(1, { q: search.value, ...(residing.value ? { is_residing: residing.value } : {}) }) }
function saved(tenant: Tenant) { void router.push(`/owner/tenants/${tenant.id}`) }
void load()
</script>
<template><section class="management-page"><header class="management-header"><div><span class="eyebrow">Không gian chủ trọ</span><h1>Người thuê</h1><p>Quản lý hồ sơ, tài khoản và lịch sử cư trú tại một nơi.</p></div><UiButton @click="creating = true">Thêm người thuê</UiButton></header><form class="management-toolbar" @submit.prevent="filter"><label>Tìm họ tên, số điện thoại hoặc giấy tờ<input v-model="search" class="ui-input" maxlength="100" /></label><label>Cư trú<select v-model="residing" class="ui-select"><option value="">Tất cả</option><option value="1">Đang ở</option><option value="0">Hiện chưa ở</option></select></label><UiButton type="submit" variant="secondary" :loading="loading">Tìm và lọc</UiButton></form><UiPageState v-if="loading" kind="loading" /><UiPageState v-else-if="loadError" kind="error" :description="loadError"><UiButton @click="load()">Thử lại</UiButton></UiPageState><UiPageState v-else-if="!items.length" title="Chưa có người thuê phù hợp" description="Thay đổi bộ lọc hoặc tạo hồ sơ mới để nhận phòng." /><div v-else class="management-grid"><article v-for="tenant in items" :key="tenant.id" class="record-card"><header><h2>{{ tenant.full_name }}</h2><UiBadge :tone="tenant.current_stay ? 'success' : 'neutral'">{{ tenant.current_stay ? 'Đang ở' : 'Hiện chưa ở' }}</UiBadge></header><dl class="record-facts"><div><dt>Số điện thoại</dt><dd>{{ tenant.phone_number }}</dd></div><div><dt>Phòng hiện tại</dt><dd>{{ tenant.current_stay?.room?.room_number ?? 'Chưa có phòng' }}</dd></div><div><dt>Tài khoản</dt><dd>{{ tenant.account ? tenant.account.is_active ? tenant.account.username : `${tenant.account.username} · Đã khóa` : 'Chưa tạo' }}</dd></div></dl><footer><RouterLink class="record-link" :to="`/owner/tenants/${tenant.id}`">Xem hồ sơ {{ tenant.full_name }}</RouterLink></footer></article></div><OwnerPager v-if="!loadError && total > 0" :page="page" :last-page="lastPage" :total="total" :loading="loading" @change="load($event)" /><TenantEditor :open="creating" @close="creating = false" @saved="saved" /></section></template>

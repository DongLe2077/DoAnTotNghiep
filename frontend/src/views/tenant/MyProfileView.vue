<script setup lang="ts">
import UiButton from '@/components/ui/UiButton.vue'
import UiPageState from '@/components/ui/UiPageState.vue'
import UiNotice from '@/components/ui/UiNotice.vue'
import { useTenantResource } from '@/composables/useTenantApi'
import type { MyProfile } from '@/types/tenant'
const { data: profile, loading, error, status, load } = useTenantResource<MyProfile>('/profile')
void load()
</script>
<template><section class="management-page tenant-page"><header class="management-header"><div><span class="eyebrow">Thông tin riêng của bạn</span><h1>Thông tin cá nhân</h1><p>Hồ sơ được chủ trọ cập nhật trong quá trình thuê.</p></div><UiButton variant="secondary" :loading="loading" @click="load">Làm mới hồ sơ</UiButton></header><UiPageState v-if="loading" kind="loading" /><UiPageState v-else-if="error" :kind="status === 404 ? 'not-found' : 'error'" :title="status === 404 ? 'Chưa có hồ sơ liên kết' : 'Chưa tải được hồ sơ'" :description="status === 404 ? 'Liên hệ chủ trọ để kiểm tra hồ sơ gắn với tài khoản của bạn.' : error"><UiButton @click="load">Thử lại</UiButton></UiPageState><template v-else-if="profile"><article class="record-card"><h2>{{ profile.full_name }}</h2><dl class="record-facts"><div><dt>Họ và tên</dt><dd>{{ profile.full_name }}</dd></div><div><dt>Số điện thoại</dt><dd>{{ profile.phone_number }}</dd></div><div><dt>Số giấy tờ</dt><dd>{{ profile.identity_number || 'Chưa được bổ sung' }}</dd></div><div><dt>Tên đăng nhập</dt><dd>{{ profile.account.username }}</dd></div></dl></article><UiNotice tone="info">Nếu thông tin chưa đúng, hãy liên hệ chủ trọ để cập nhật. Hồ sơ và giấy tờ này chỉ hiển thị cho tài khoản của bạn và chủ trọ.</UiNotice></template><div class="tenant-quick-links"><RouterLink to="/account">Tài khoản / Đổi mật khẩu →</RouterLink><RouterLink to="/tenant/stays">Lịch sử cư trú →</RouterLink><RouterLink to="/notifications">Thông báo của bạn →</RouterLink></div></section></template>

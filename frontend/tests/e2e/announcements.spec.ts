import { expect, test, type APIRequestContext, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'
const password = 'E2e-Password-2077!', shots = resolve(process.cwd(), '.cache/e2e/screenshots')
mkdirSync(shots, { recursive: true })
test.describe.configure({ timeout: 120_000 })
test.beforeEach(({ page }) => page.setDefaultTimeout(15_000))
async function write(request: APIRequestContext, path: string, data: unknown, method = 'POST') {
  await request.get('/api/auth/csrf-cookie'); const cookie = (await request.storageState()).cookies.find(c => c.name === 'XSRF-TOKEN')!
  const response = await request.fetch(`/api${path}`, { method, data, headers: { 'X-XSRF-TOKEN': decodeURIComponent(cookie.value) } })
  expect(response.ok(), await response.text()).toBe(true); return (await response.json()).data
}
async function login(page: Page, username = 'e2e_owner') {
  await page.goto('/login'); await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username); await page.getByLabel('Mật khẩu', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click(); await expect(page).toHaveURL(username === 'e2e_owner' ? '/owner' : '/tenant')
}
async function context(request: APIRequestContext, tag: string, members = false) {
  async function person(suffix: string) {
    const tenant = await write(request, '/owner/tenants', { full_name: `${tag} ${suffix}`, phone_number: '0900123456' })
    const username = `${tag.toLowerCase().replaceAll('-', '_')}_${suffix.toLowerCase()}`
    await write(request, `/owner/tenants/${tenant.id}/account`, { username, password, password_confirmation: password, is_active: true }, 'PUT')
    return { ...tenant, username }
  }
  const tenant = await person('Rep'), member = members ? await person('Member') : null
  const room = await write(request, '/owner/rooms', { room_number: tag, capacity: 3 })
  const group = await write(request, '/owner/rental-groups', { group_code: tag })
  const stay = await write(request, '/owner/occupancies', { room_id: room.id, rental_group_id: group.id, tenant_ids: [tenant.id, ...(member ? [member.id] : [])], representative_tenant_id: tenant.id, started_at: '2026-01-01 00:00:00' })
  return { tenant, member, room, stay }
}
async function fill(page: Page, title: string, body = 'Lịch kiểm tra nước vào sáng mai.\nXin cảm ơn.') {
  await page.goto('/owner/announcements/new'); await page.getByLabel('Tiêu đề thông báo (bắt buộc)').fill(title); await page.getByLabel('Nội dung thông báo', { exact: false }).fill(body)
}
async function selectRoom(page: Page, number: string) {
  await page.getByRole('combobox', { name: 'Gửi đến', exact: true }).selectOption('rooms')
  const picker = page.getByRole('group', { name: 'Phòng nhận thông báo', exact: true })
  await picker.getByRole('textbox').fill(number); await picker.getByRole('button', { name: 'Tìm', exact: true }).click(); await picker.getByRole('checkbox', { name: number, exact: true }).check()
}
async function preview(page: Page) { await page.getByRole('button', { name: 'Xem trước và người nhận', exact: true }).click(); await expect(page.getByRole('heading', { name: 'Kiểm tra trước khi gửi', exact: true })).toBeVisible() }
async function send(page: Page) { await page.getByRole('checkbox', { name: 'Tôi đã kiểm tra nội dung và người nhận.' }).check(); await page.getByRole('button', { name: 'Xác nhận gửi thông báo', exact: true }).click() }

test('owner previews all room members, rejects changed audience, reconciles lost send after reload, and tracks tenant reading', async ({ page, browser, baseURL }) => {
  await login(page); const c = await context(page.request, 'ANN-ROOM', true)
  const title = 'Lịch kiểm tra nước phòng ANN-ROOM', body = 'Sáng mai kiểm tra van nước.\n<script>window.announcementInjected = true</script>\nXin cảm ơn.'
  await fill(page, title, body); await selectRoom(page, c.room.room_number)
  const roomPicker = page.getByRole('group', { name: 'Phòng nhận thông báo', exact: true })
  await roomPicker.getByRole('button', { name: 'Bỏ chọn', exact: true }).click()
  await expect(roomPicker.getByRole('checkbox', { name: c.room.room_number, exact: true })).not.toBeChecked()
  await roomPicker.getByRole('checkbox', { name: c.room.room_number, exact: true }).check()
  await expect(roomPicker.locator('.picker-selected')).toHaveText(`Đã chọn: ${c.room.room_number} Bỏ chọn`)
  await preview(page)
  await expect(page.getByText('2 người nhận', { exact: true })).toBeVisible(); await expect(page.getByText(c.member.full_name, { exact: true })).toBeVisible()
  await write(page.request, `/owner/tenants/${c.member.id}/account`, { is_active: false }, 'PUT')
  await send(page); await expect(page.getByRole('alert')).toContainText('danh sách người nhận đã thay đổi')
  await preview(page); await expect(page.getByText('1 người nhận', { exact: true })).toBeVisible()
  let firstPayload: unknown
  await page.route('**/api/owner/announcements', async route => { if (route.request().method() !== 'POST') return route.continue(); firstPayload = route.request().postDataJSON(); const response = await route.fetch(); expect(response.status()).toBe(201); await route.abort('failed') }, { times: 1 })
  await send(page); await expect(page.getByRole('button', { name: 'Gửi lại lượt đang chờ', exact: true })).toBeEnabled()
  await page.reload(); let replayPayload: unknown
  page.on('request', r => { if (r.method() === 'POST' && r.url().endsWith('/api/owner/announcements')) replayPayload = r.postDataJSON() })
  await page.getByRole('button', { name: 'Gửi lại lượt đang chờ', exact: true }).click(); await expect(page).toHaveURL(/\/owner\/announcements\/\d+\?sent=1$/); expect(replayPayload).toEqual(firstPayload)
  const id = new URL(page.url()).pathname.split('/').at(-1)!
  let state = (await (await page.request.get(`/api/owner/announcements/${id}`)).json()).data; expect(state.recipient_count).toBe(1); expect(state.read_count).toBe(0)
  expect((await (await page.request.get(`/api/owner/announcements?q=${encodeURIComponent(title)}`)).json()).meta.total).toBe(1)
  const tenantContext = await browser.newContext({ baseURL }), tenantPage = await tenantContext.newPage()
  tenantPage.setDefaultTimeout(15_000)
  await login(tenantPage, c.tenant.username); await tenantPage.goto('/notifications?type=owner_announcement')
  await expect(tenantPage.getByRole('heading', { name: title, exact: true })).toBeVisible(); await tenantPage.getByRole('button', { name: `Xem nội dung: ${title}`, exact: true }).click()
  const dialog = tenantPage.getByRole('dialog'); await expect(dialog.getByText(body, { exact: true })).toBeVisible(); expect(await tenantPage.evaluate(() => (window as unknown as Record<string, unknown>).announcementInjected)).toBeUndefined()
  await dialog.getByRole('link', { name: 'Mở trang chi tiết →', exact: true }).click(); await expect(tenantPage).toHaveURL(`/tenant/announcements/${id}`)
  await expect(tenantPage.getByText('Đã đọc', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Cập nhật trạng thái đọc', exact: true }).click(); await expect(page.getByText('Đọc lúc', { exact: false })).toBeVisible()
  state = (await (await page.request.get(`/api/owner/announcements/${id}`)).json()).data; expect(state.read_count).toBe(1)
  await page.screenshot({ path: resolve(shots, 'announcements-owner-detail.png'), animations: 'disabled', fullPage: true })
  await tenantPage.setViewportSize({ width: 390, height: 844 }); await tenantPage.reload(); await expect(tenantPage.getByRole('heading', { name: title, exact: true })).toBeVisible(); await tenantPage.evaluate(() => document.fonts.ready)
  expect(await tenantPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true)
  await tenantPage.screenshot({ path: resolve(shots, 'announcements-tenant-mobile.png'), animations: 'disabled', fullPage: false }); await tenantContext.close()
})

test('all audience delivers to active residents and future residents cannot read the old announcement', async ({ page, browser, baseURL }) => {
  await login(page); const c = await context(page.request, 'ANN-ALL')
  await fill(page, 'Thông báo toàn khu dùng thử'); await preview(page); await expect(page.getByText(c.tenant.full_name, { exact: true })).toBeVisible(); await send(page); await expect(page).toHaveURL(/\/owner\/announcements\/\d+\?sent=1$/)
  const id = new URL(page.url()).pathname.split('/').at(-1)!
  const newcomer = await context(page.request, 'ANN-FUTURE')
  const tenantContext = await browser.newContext({ baseURL }), tenantPage = await tenantContext.newPage(); await login(tenantPage, newcomer.tenant.username)
  expect((await tenantPage.request.get(`/api/tenant/announcements/${id}`)).status()).toBe(404)
  expect((await tenantPage.request.get('/api/owner/announcements')).status()).toBe(403)
  await tenantPage.goto('/owner/announcements/new'); await expect(tenantPage).toHaveURL('/forbidden'); await tenantContext.close()
  const recipientContext = await browser.newContext({ baseURL }), recipientPage = await recipientContext.newPage(); await login(recipientPage, c.tenant.username); await recipientPage.goto(`/tenant/announcements/${id}`)
  await recipientPage.getByRole('button', { name: 'Đánh dấu đã đọc', exact: true }).click(); await expect(recipientPage.getByText('Đã đọc', { exact: true })).toBeVisible()
  await recipientContext.close()
  await page.getByRole('combobox', { name: 'Trạng thái đọc', exact: true }).selectOption('read'); await page.getByRole('button', { name: 'Lọc người nhận', exact: true }).click(); await expect(page.getByText(c.tenant.full_name, { exact: true })).toBeVisible()
})

test('empty room has clear zero-recipient state and owner list fits a narrow screen', async ({ page }) => {
  await login(page); const room = await write(page.request, '/owner/rooms', { room_number: 'ANN-EMPTY', capacity: 2 })
  await fill(page, 'Không gửi khi chưa có người nhận'); await selectRoom(page, room.room_number); await preview(page)
  await expect(page.getByText('0 người nhận', { exact: true })).toBeVisible(); await expect(page.getByRole('button', { name: 'Xác nhận gửi thông báo', exact: true })).toBeDisabled()
  await page.goto('/owner/announcements'); await page.getByLabel('Tìm thông báo', { exact: true }).fill('không tồn tại ANN-EMPTY'); await page.getByRole('button', { name: 'Tìm thông báo', exact: true }).click(); await expect(page.getByText('Chưa có thông báo phù hợp', { exact: true })).toBeVisible()
  await page.setViewportSize({ width: 390, height: 844 }); await page.reload(); await expect(page.getByRole('heading', { name: 'Thông báo chung', exact: true })).toBeVisible(); expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true)
})

test('a CSRF rejection does not automatically resend and explicit review can send once', async ({ page }) => {
  await login(page); const c = await context(page.request, 'ANN-CSRF'); await fill(page, 'Lịch kiểm tra có xác nhận'); await selectRoom(page, c.room.room_number); await preview(page)
  let rejected = 0
  await page.route('**/api/owner/announcements', async route => { rejected++; await route.fulfill({ status: 419, contentType: 'application/json', body: JSON.stringify({ message: 'Phiên bảo mật đã thay đổi.' }) }) }, { times: 1 })
  await send(page); await expect(page.getByRole('alert')).toBeVisible(); expect(rejected).toBe(1); await expect(page.getByRole('button', { name: 'Xem trước và người nhận', exact: true })).toBeEnabled()
  await preview(page); await send(page); await expect(page).toHaveURL(/\/owner\/announcements\/\d+\?sent=1$/)
})

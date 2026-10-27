import { expect, test, type Page, type APIRequestContext } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'
const password = 'E2e-Password-2077!', shots = resolve(process.cwd(), '.cache/e2e/screenshots')
mkdirSync(shots, { recursive: true })
test.describe.configure({ timeout: 150_000 })
test.beforeEach(({ page }) => page.setDefaultTimeout(15_000))
async function login(page: Page, username = 'e2e_owner') {
  await page.goto('/login'); await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username); await page.getByLabel('Mật khẩu', { exact: true }).fill(password)
  const signedIn = page.waitForResponse(r => r.url().endsWith('/api/auth/login') && r.request().method() === 'POST')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click(); expect((await signedIn).status()).toBe(200)
  await expect(page).toHaveURL(username === 'e2e_owner' ? '/owner' : '/tenant')
}
async function write(request: APIRequestContext, path: string, data: unknown, method = 'POST') {
  await request.get('/api/auth/csrf-cookie'); const cookie = (await request.storageState()).cookies.find(c => c.name === 'XSRF-TOKEN')!
  const response = await request.fetch(`/api${path}`, { method, data, headers: { 'X-XSRF-TOKEN': decodeURIComponent(cookie.value) } })
  expect(response.ok(), await response.text()).toBe(true); return (await response.json()).data
}
async function create(request: APIRequestContext, tag: string) {
  const person = await write(request, '/owner/tenants', { full_name: `Người gửi ${tag}`, phone_number: '0900000000' })
  const username = tag.toLowerCase().replaceAll('-', '_')
  await write(request, `/owner/tenants/${person.id}/account`, { username, password, password_confirmation: password, is_active: true }, 'PUT')
  const room = await write(request, '/owner/rooms', { room_number: tag, capacity: 1 })
  const group = await write(request, '/owner/rental-groups', { group_code: tag })
  const stay = await write(request, '/owner/occupancies', { room_id: room.id, rental_group_id: group.id, tenant_ids: [person.id], representative_tenant_id: person.id, started_at: '2026-01-01 00:00:00' })
  return { username, stay }
}
async function edit(page: Page, note: string, resolved = false) {
  await page.getByRole('button', { name: /^(Tiếp nhận \/ xử lý|Cập nhật xử lý)$/, exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Cập nhật sửa chữa', exact: true })
  if (resolved) await dialog.getByLabel('Trạng thái xử lý (bắt buộc)', { exact: true }).selectOption('resolved')
  await dialog.getByLabel(resolved ? 'Kết quả sửa chữa (bắt buộc)' : 'Nội dung xử lý', { exact: true }).fill(note)
  await dialog.getByRole('button', { name: 'Xem lại cập nhật', exact: true }).click()
  await dialog.getByRole('button', { name: 'Xác nhận cập nhật', exact: true }).click()
}
async function noOverflow(page: Page) { expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true) }

test('owner and author see real update history including clear notes, lost-response reconciliation and checkout', async ({ page, browser, baseURL }) => {
  await login(page); const c = await create(page.request, 'HIST-WORK')
  const tenantContext = await browser.newContext({ baseURL }), tenantPage = await tenantContext.newPage(); tenantPage.setDefaultTimeout(15_000)
  await login(tenantPage, c.username)
  const report = await write(tenantPage.request, `/tenant/occupancies/${c.stay.id}/maintenance-requests`, { title: 'Vòi nước cần sửa HIST-WORK', description: 'Nước rò tại van.' })
  await page.goto(`/owner/maintenance/${report.id}`)
  const history = page.getByRole('region', { name: 'Lịch sử xử lý sửa chữa', exact: true })
  await expect(history.getByRole('listitem')).toHaveCount(1)
  let attempts = 0
  await page.route(`**/api/owner/maintenance-requests/${report.id}`, async route => {
    if (route.request().method() !== 'PATCH') return route.continue()
    attempts++; const result = await route.fetch(); expect(result.status()).toBe(200); await route.abort('failed')
  }, { times: 1 })
  await edit(page, 'Đã hẹn thợ kiểm tra.')
  await expect(page.getByRole('dialog').getByRole('alert').first()).toBeVisible(); expect(attempts).toBe(1)
  await page.getByRole('dialog').getByRole('button', { name: 'Xác nhận cập nhật', exact: true }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Tải bản mới nhất', exact: true }).click()
  await expect(history.getByRole('listitem')).toHaveCount(2)
  await edit(page, 'Chờ van thay thế.'); await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(history.getByRole('listitem')).toHaveCount(3)
  await edit(page, ''); await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(history.getByText('Đã xóa nội dung xử lý trước đó.', { exact: true })).toBeVisible()
  await edit(page, 'Đã thay van và kiểm tra hết rò.', true); await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(history.getByRole('listitem')).toHaveCount(5)
  await history.getByRole('listitem').filter({ hasText: 'Ghi chú: Chờ van thay thế.' }).getByText('Ghi chú trước khi cập nhật', { exact: true }).click()
  await expect(history.getByText('Ghi chú trước: Đã hẹn thợ kiểm tra.', { exact: true })).toBeVisible()
  await page.evaluate(() => (document.activeElement as HTMLElement)?.blur())
  await history.screenshot({ path: resolve(shots, 'maintenance-history-owner.png'), animations: 'disabled' })
  const now = (await (await page.request.get('/api/owner/dashboard')).json()).data.as_of
  await write(page.request, `/owner/occupancies/${c.stay.id}/checkout`, { ended_at: now })
  await tenantPage.goto(`/tenant/maintenance/${report.id}`)
  const tenantHistory = tenantPage.getByRole('region', { name: 'Lịch sử xử lý sửa chữa', exact: true })
  await expect(tenantHistory.getByRole('listitem')).toHaveCount(5)
  await expect(tenantHistory.getByText('Người thực hiện: Chủ trọ', { exact: true })).toHaveCount(4)
  await expect(tenantHistory).not.toContainText('e2e_owner')
  await tenantPage.setViewportSize({ width: 390, height: 844 }); await tenantPage.reload()
  await expect(tenantHistory.getByRole('listitem')).toHaveCount(5); await noOverflow(tenantPage)
  await tenantPage.evaluate(() => document.fonts.ready)
  await tenantPage.evaluate(() => { (document.activeElement as HTMLElement)?.blur(); document.querySelector('.maintenance-history')?.scrollIntoView({ block: 'start' }) })
  await tenantPage.screenshot({ path: resolve(shots, 'maintenance-history-tenant-mobile.png'), animations: 'disabled' })
  await tenantContext.close()
})

test('timeline paginates newest first and rejects another tenant', async ({ page, browser, baseURL }) => {
  await login(page); const c = await create(page.request, 'HIST-PAGES')
  const tenantContext = await browser.newContext({ baseURL }), tenantPage = await tenantContext.newPage(); await login(tenantPage, c.username)
  const report = await write(tenantPage.request, `/tenant/occupancies/${c.stay.id}/maintenance-requests`, { title: 'Sự cố HIST-PAGES', description: 'Thử dòng thời gian.' })
  let current = (await (await page.request.get(`/api/owner/maintenance-requests/${report.id}`)).json()).data
  for (let i = 1; i <= 12; i++) current = await write(page.request, `/owner/maintenance-requests/${report.id}`, { status: 'in_progress', resolution: `Tiến độ HIST-PAGES ${i}`, version_token: current.version_token }, 'PATCH')
  await page.goto(`/owner/maintenance/${report.id}`)
  const history = page.getByRole('region', { name: 'Lịch sử xử lý sửa chữa', exact: true })
  await expect(history.getByRole('listitem')).toHaveCount(10)
  await expect(history.getByRole('listitem').first()).toContainText('Ghi chú: Tiến độ HIST-PAGES 12')
  await history.getByRole('button', { name: 'Trang sau', exact: true }).click()
  await expect(history.getByRole('listitem')).toHaveCount(3); await expect(history.getByRole('listitem').last()).toContainText('Đã gửi yêu cầu')
  const foreignContext = await browser.newContext({ baseURL }), foreignPage = await foreignContext.newPage(); await login(foreignPage, 'e2e_notify_empty')
  expect((await foreignPage.request.get(`/api/tenant/maintenance-requests/${report.id}/history`)).status()).toBe(404)
  expect((await foreignPage.request.get(`/api/owner/maintenance-requests/${report.id}/history`)).status()).toBe(403)
  await foreignContext.close(); await tenantContext.close()
})

test('legacy history is labelled honestly and refresh failure hides old entries until explicit retry', async ({ page }) => {
  await login(page); await page.goto('/owner/maintenance/1')
  const history = page.getByRole('region', { name: 'Lịch sử xử lý sửa chữa', exact: true })
  await expect(history.getByText('Mốc bắt đầu lưu lịch sử', { exact: true })).toBeVisible()
  await expect(history.getByText(/Những cập nhật trước mốc này chỉ còn trạng thái/)).toBeVisible()
  await page.route('**/api/owner/maintenance-requests/1/history?*', route => route.abort('failed'), { times: 1 })
  await history.getByRole('button', { name: 'Làm mới lịch sử', exact: true }).click()
  await expect(history.getByRole('listitem')).toHaveCount(0)
  await history.getByRole('button', { name: 'Thử lại lịch sử', exact: true }).click()
  await expect(history.getByText('Mốc bắt đầu lưu lịch sử', { exact: true })).toBeVisible()
  await page.setViewportSize({ width: 320, height: 740 }); await page.reload()
  await expect(history.getByText('Mốc bắt đầu lưu lịch sử', { exact: true })).toBeVisible(); await noOverflow(page)
})

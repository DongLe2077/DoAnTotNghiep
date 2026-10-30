import { expect, test, type Page, type APIRequestContext, type Browser, type BrowserContext } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'

const password = 'E2e-Password-2077!', screenshots = resolve(process.cwd(), '.cache/e2e/screenshots')
mkdirSync(screenshots, { recursive: true })
test.describe.configure({ timeout: 150_000 })
test.beforeEach(({ page }) => page.setDefaultTimeout(15_000))

async function login(page: Page, username = 'e2e_owner') {
  await page.goto('/login')
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username)
  await page.getByLabel('Mật khẩu', { exact: true }).fill(password)
  const response = page.waitForResponse(r => r.url().endsWith('/api/auth/login') && r.request().method() === 'POST')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  expect((await response).status()).toBe(200)
  await expect(page).toHaveURL(username === 'e2e_owner' ? '/owner' : '/tenant')
}
async function write(request: APIRequestContext, path: string, data: unknown, method = 'POST') {
  await request.get('/api/auth/csrf-cookie')
  const cookie = (await request.storageState()).cookies.find(item => item.name === 'XSRF-TOKEN')!
  const response = await request.fetch(`/api${path}`, { method, data, headers: { 'X-XSRF-TOKEN': decodeURIComponent(cookie.value) } })
  expect(response.ok(), await response.text()).toBe(true)
  return response.status() === 204 ? null : (await response.json()).data
}
async function fixture(page: Page, browser: Browser, baseURL: string | undefined, tag: string) {
  const person = await write(page.request, '/owner/tenants', { full_name: `Người thuê ${tag}`, phone_number: '0900000000' })
  const username = tag.toLowerCase().replaceAll('-', '_')
  await write(page.request, `/owner/tenants/${person.id}/account`, { username, password, password_confirmation: password, is_active: true }, 'PUT')
  const room = await write(page.request, '/owner/rooms', { room_number: tag, capacity: 1 })
  const group = await write(page.request, '/owner/rental-groups', { group_code: tag })
  const stay = await write(page.request, '/owner/occupancies', { room_id: room.id, rental_group_id: group.id, tenant_ids: [person.id], representative_tenant_id: person.id, started_at: '2026-01-01 00:00:00' })
  const context: BrowserContext = await browser.newContext({ baseURL }), tenant = await context.newPage()
  tenant.setDefaultTimeout(15_000); await login(tenant, username)
  const report = await write(tenant.request, `/tenant/occupancies/${stay.id}/maintenance-requests`, { title: `Sự cố ${tag}`, description: `Kiểm tra chi phí ${tag}` })
  return { report, tenant, context }
}
async function cash(page: Page, tag: string, amount = '125000.50') {
  return write(page.request, '/owner/cash-transactions', { transaction_type: 'expense', amount, occurred_at: '2026-01-15 09:00:00', description: `Chi sửa chữa ${tag}`, reason: 'Dữ liệu riêng của kiểm thử', idempotency_key: crypto.randomUUID() })
}
function expenses(page: Page) { return page.getByRole('region', { name: 'Chi phí sửa chữa', exact: true }) }
async function choose(page: Page, description: string, reason: string) {
  await expenses(page).getByRole('button', { name: 'Liên kết khoản chi', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Chọn khoản chi sửa chữa', exact: true })
  await dialog.getByLabel('Tìm khoản chi đã ghi', { exact: true }).fill(description)
  await dialog.getByRole('button', { name: 'Tìm khoản chi', exact: true }).click()
  await dialog.getByRole('radio', { name: new RegExp(description) }).check()
  await expect(dialog.getByText('Đang kiểm tra liên kết hiện tại…', { exact: true })).toHaveCount(0)
  await dialog.getByLabel('Lý do liên kết / bỏ liên kết (bắt buộc)', { exact: true }).fill(reason)
  await dialog.getByRole('button', { name: 'Xem lại liên kết', exact: true }).click()
  await expect(page.getByRole('dialog', { name: 'Xem lại liên kết chi phí', exact: true })).toBeVisible()
}
async function confirmLink(page: Page) {
  await page.getByRole('dialog', { name: 'Xem lại liên kết chi phí', exact: true }).getByRole('button', { name: 'Xác nhận liên kết', exact: true }).click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
}
async function total(page: Page, amount: string) { await expect(expenses(page).getByRole('group', { name: 'Tổng chi phí sửa chữa', exact: true }).getByText(amount, { exact: true })).toBeVisible() }
async function overflow(page: Page) { expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true) }

test('repair expense links existing cash once and follows edit/delete/restore/unlink/relink with audit', async ({ page, browser, baseURL }) => {
  await login(page)
  const f = await fixture(page, browser, baseURL, 'COST-LINK'), entry = await cash(page, 'COST-LINK')
  try {
    await page.goto(`/owner/maintenance/${f.report.id}`); await total(page, '0 ₫')
    await choose(page, entry.description, 'Đối chiếu khoản thay van nước'); await confirmLink(page)
    await total(page, '125.000,50 ₫')
    await expenses(page).getByRole('button', { name: `Xem khoản chi ${entry.id}`, exact: true }).click()
    const detail = page.getByRole('dialog', { name: 'Chi tiết và nhật ký thu chi', exact: true })
    await expect(detail.getByRole('link', { name: 'Mở yêu cầu sửa chữa →', exact: true })).toHaveAttribute('href', `/owner/maintenance/${f.report.id}`)
    await detail.getByRole('button', { name: 'Sửa khoản này', exact: true }).click()
    let changing = page.getByRole('dialog')
    await expect(changing.getByRole('combobox', { name: 'Loại khoản thu chi', exact: true })).toBeDisabled()
    await changing.getByLabel('Số tiền (VNĐ) (bắt buộc)', { exact: true }).fill('250000.75')
    await changing.getByLabel('Lý do thay đổi', { exact: true }).fill('Bổ sung vật tư thực tế')
    await changing.getByRole('button', { name: 'Xem lại thay đổi', exact: true }).click()
    await changing.getByRole('button', { name: 'Xác nhận thay đổi', exact: true }).click()
    await expect(page.getByRole('button', { name: 'Sửa khoản này', exact: true })).toBeVisible()
    await page.getByRole('dialog').getByRole('button', { name: 'Đóng', exact: true }).click()
    await total(page, '250.000,75 ₫')
    await expenses(page).getByRole('button', { name: `Xem khoản chi ${entry.id}`, exact: true }).click()
    await page.getByRole('dialog').getByRole('button', { name: 'Xóa khoản này', exact: true }).click()
    changing = page.getByRole('dialog')
    await changing.getByLabel('Lý do thay đổi', { exact: true }).fill('Kiểm tra khoản xóa nhầm')
    await changing.getByRole('button', { name: 'Xem lại thay đổi', exact: true }).click()
    await changing.getByRole('button', { name: 'Xác nhận thay đổi', exact: true }).click()
    await expect(page.getByRole('button', { name: 'Hoàn tác để khôi phục', exact: true })).toBeVisible()
    await page.getByRole('dialog').getByRole('button', { name: 'Đóng', exact: true }).click()
    await total(page, '0 ₫')
    await expect(expenses(page).getByText('Đã xóa khỏi sổ · Không tính vào tổng', { exact: true })).toBeVisible()
    await expenses(page).getByRole('button', { name: `Xem khoản chi ${entry.id}`, exact: true }).click()
    await page.getByRole('button', { name: 'Hoàn tác để khôi phục', exact: true }).click()
    changing = page.getByRole('dialog')
    await changing.getByLabel('Lý do thay đổi (bắt buộc)', { exact: true }).fill('Đã đối chiếu, khôi phục khoản chi')
    await changing.getByRole('button', { name: 'Xem lại thay đổi', exact: true }).click()
    await changing.getByRole('button', { name: 'Xác nhận thay đổi', exact: true }).click()
    await expect(page.getByRole('button', { name: 'Sửa khoản này', exact: true })).toBeVisible()
    await page.getByRole('dialog').getByRole('button', { name: 'Đóng', exact: true }).click(); await total(page, '250.000,75 ₫')
    await expenses(page).getByRole('button', { name: `Bỏ liên kết khoản chi ${entry.id}`, exact: true }).click()
    await page.getByRole('dialog').getByLabel('Lý do liên kết / bỏ liên kết (bắt buộc)', { exact: true }).fill('Đối chiếu trước khi liên kết lại')
    await page.getByRole('dialog').getByRole('button', { name: 'Xem lại liên kết', exact: true }).click()
    await page.getByRole('dialog').getByRole('button', { name: 'Xác nhận bỏ liên kết', exact: true }).click()
    await expect(page.getByRole('dialog')).toHaveCount(0); await total(page, '0 ₫')
    await choose(page, entry.description, 'Liên kết lại khoản chi đã đối chiếu'); await confirmLink(page); await total(page, '250.000,75 ₫')
    await expenses(page).getByText('Lịch sử liên kết chi phí', { exact: true }).click()
    await expect(expenses(page).getByRole('list', { name: 'Nhật ký liên kết khoản chi', exact: true }).getByRole('listitem')).toHaveCount(3)
    const summary = (await (await page.request.get(`/api/owner/cash-flow/summary?month=2026-01&q=${encodeURIComponent(entry.description)}`)).json()).data
    expect(summary.expense_total).toBe('250000.75'); expect(summary.expense_count).toBe(1)
    await page.evaluate(() => (document.activeElement as HTMLElement)?.blur())
    await expenses(page).screenshot({ path: resolve(screenshots, 'repair-costs-owner.png'), animations: 'disabled' })
  } finally { await f.context.close() }
})

test('repair link retries the identical durable request after committed response loss and reload', async ({ page, browser, baseURL }) => {
  await login(page)
  const f = await fixture(page, browser, baseURL, 'COST-REPLAY'), entry = await cash(page, 'COST-REPLAY', '330000.25'), payloads: unknown[] = []
  try {
    await page.goto(`/owner/maintenance/${f.report.id}`)
    await page.route(`**/api/owner/maintenance-requests/${f.report.id}/expenses`, async route => {
      if (route.request().method() !== 'POST') return route.continue()
      payloads.push(route.request().postDataJSON())
      const response = await route.fetch(); expect(response.status()).toBe(201); await route.abort('failed')
    }, { times: 1 })
    await choose(page, entry.description, 'Liên kết an toàn khi mất kết nối')
    await page.getByRole('dialog').getByRole('button', { name: 'Xác nhận liên kết', exact: true }).click()
    await expect(page.getByRole('dialog').getByRole('alert').first()).toBeVisible(); expect(payloads).toHaveLength(1)
    await page.reload()
    await total(page, '330.000,25 ₫')
    await expenses(page).getByRole('button', { name: 'Mở yêu cầu đang chờ', exact: true }).click()
    page.on('request', request => { if (request.method() === 'POST' && request.url().endsWith(`/maintenance-requests/${f.report.id}/expenses`)) payloads.push(request.postDataJSON()) })
    const replay = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith(`/maintenance-requests/${f.report.id}/expenses`))
    await page.getByRole('dialog').getByRole('button', { name: 'Gửi lại yêu cầu đang chờ', exact: true }).click()
    const response = await replay; expect(response.status()).toBe(200); expect((await response.json()).replayed).toBe(true)
    await expect(page.getByRole('dialog')).toHaveCount(0); expect(payloads).toHaveLength(2); expect(payloads[1]).toEqual(payloads[0])
    await total(page, '330.000,25 ₫')
    await expenses(page).getByText('Lịch sử liên kết chi phí', { exact: true }).click()
    await expect(expenses(page).getByRole('list', { name: 'Nhật ký liên kết khoản chi', exact: true }).getByRole('listitem')).toHaveCount(1)
    expect(await page.evaluate(reportId => Object.keys(localStorage).some(key => key.startsWith('rental.maintenance-expense.pending.v1:') && key.includes(`:${reportId}:`)), f.report.id)).toBe(false)
  } finally { await f.context.close() }
})

test('new expense requires explicit linking and internal costs stay private from tenant on mobile', async ({ page, browser, baseURL }) => {
  await login(page)
  const f = await fixture(page, browser, baseURL, 'COST-CREATE'), description = 'Vật tư riêng COST-CREATE'
  try {
    await page.goto(`/owner/maintenance/${f.report.id}`)
    await expenses(page).getByRole('button', { name: 'Liên kết khoản chi', exact: true }).click()
    await page.getByRole('dialog').getByRole('button', { name: 'Tạo khoản chi mới', exact: true }).click()
    let dialog = page.getByRole('dialog', { name: 'Thêm khoản thu chi', exact: true })
    await dialog.getByLabel('Số tiền (VNĐ) (bắt buộc)', { exact: true }).fill('120500.30')
    await dialog.getByLabel('Thời điểm phát sinh (giờ Việt Nam) (bắt buộc)', { exact: true }).fill('2026-01-15T09:00')
    await dialog.getByLabel('Nội dung thu chi (bắt buộc)', { exact: true }).fill(description)
    await dialog.getByRole('button', { name: 'Xem lại khoản thu chi', exact: true }).click()
    await page.getByRole('dialog').getByRole('button', { name: 'Xác nhận ghi nhận', exact: true }).click()
    dialog = page.getByRole('dialog', { name: 'Chọn khoản chi sửa chữa', exact: true })
    await expect(dialog.getByText(description, { exact: false }).first()).toBeVisible()
    const before = (await (await page.request.get(`/api/owner/maintenance-requests/${f.report.id}/expenses`)).json())
    expect(before.summary.active_count).toBe(0); expect(before.summary.total_amount).toBe('0.00')
    await dialog.getByLabel('Lý do liên kết / bỏ liên kết (bắt buộc)', { exact: true }).fill('Chi vật tư cho sự cố đang xử lý')
    await dialog.getByRole('button', { name: 'Xem lại liên kết', exact: true }).click(); await confirmLink(page)
    await total(page, '120.500,30 ₫')
    const list = (await (await page.request.get(`/api/owner/cash-transactions?q=${encodeURIComponent(description)}`)).json()).data
    expect(list).toHaveLength(1)
    await f.tenant.goto(`/tenant/maintenance/${f.report.id}`)
    await expect(f.tenant.getByRole('heading', { name: f.report.title, exact: true })).toBeVisible()
    await expect(f.tenant.getByRole('region', { name: 'Chi phí sửa chữa', exact: true })).toHaveCount(0)
    await expect(f.tenant.getByText(description, { exact: false })).toHaveCount(0)
    for (const path of [`/api/owner/maintenance-requests/${f.report.id}/expenses`, `/api/owner/maintenance-requests/${f.report.id}/expenses/history`, `/api/owner/cash-transactions/${list[0].id}/maintenance-link`]) expect((await f.tenant.request.get(path)).status()).toBe(403)
    expect((await f.tenant.request.get(`/api/tenant/maintenance-requests/${f.report.id}/expenses`)).status()).toBe(404)
    await page.setViewportSize({ width: 320, height: 740 }); await page.reload(); await total(page, '120.500,30 ₫'); await overflow(page)
    await expenses(page).scrollIntoViewIfNeeded(); await page.evaluate(() => (document.activeElement as HTMLElement)?.blur())
    await page.screenshot({ path: resolve(screenshots, 'repair-costs-owner-mobile.png'), animations: 'disabled' })
    await f.tenant.setViewportSize({ width: 320, height: 740 }); await f.tenant.reload(); await expect(f.tenant.getByRole('heading', { name: f.report.title, exact: true })).toBeVisible(); await overflow(f.tenant)
  } finally { await f.context.close() }
})

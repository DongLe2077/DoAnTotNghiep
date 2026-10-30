import { expect, test, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'

test.describe.configure({ timeout: 150_000 })
test.beforeEach(({ page }) => page.setDefaultTimeout(15_000))
const password = 'E2e-Password-2077!', shots = resolve(process.cwd(), '.cache/e2e/screenshots')
mkdirSync(shots, { recursive: true })
async function login(page: Page, username = 'e2e_owner') {
  await page.goto('/login'); await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username); await page.getByLabel('Mật khẩu', { exact: true }).fill(password)
  const response = page.waitForResponse(result => result.request().method() === 'POST' && result.url().endsWith('/api/auth/login'))
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click(); expect((await response).status()).toBe(200)
  await expect(page).toHaveURL(username === 'e2e_owner' ? '/owner' : '/tenant')
}
async function write(page: Page, path: string, data: unknown, method = 'POST') {
  await page.request.get('/api/auth/csrf-cookie'); const token = (await page.context().cookies()).find(item => item.name === 'XSRF-TOKEN')!
  const response = await page.request.fetch(`/api/owner${path}`, { method, data, headers: { 'X-XSRF-TOKEN': decodeURIComponent(token.value) } })
  expect(response.ok(), await response.text()).toBe(true); return response.status() === 204 ? null : (await response.json()).data
}
async function fixture(page: Page, name: string) {
  const tenant = await write(page, '/tenants', { full_name: `Người thuê ${name}`, phone_number: '0900000000' })
  await write(page, `/tenants/${tenant.id}/account`, { username: name.toLowerCase().replaceAll('-', '_'), password, password_confirmation: password, is_active: true }, 'PUT')
  const room = await write(page, '/rooms', { room_number: name, capacity: 1 }), group = await write(page, '/rental-groups', { group_code: name })
  let stay = await write(page, '/occupancies', { room_id: room.id, rental_group_id: group.id, tenant_ids: [tenant.id], representative_tenant_id: tenant.id, started_at: '2026-01-01 00:00:00' })
  const service = await write(page, '/services', { service_code: `${name}-DIEN`, name: `Điện ${name}`, unit: 'kWh', billing_method: 'metered', default_unit_price: '3500.1234', is_active: true })
  stay = await write(page, `/occupancies/${stay.id}/services`, { service_id: service.id, effective_from: '2026-01-01 00:00:00' })
  return { stay, service, period: stay.services.find((item: { service_id: number }) => item.service_id === service.id), room }
}
async function readings(page: Page, stayId: number) { return (await (await page.request.get(`/api/owner/occupancies/${stayId}/meter-readings`)).json()).data }
async function openMonth(page: Page, prefix: string) {
  await page.goto('/owner/meters/bulk'); await page.getByLabel('Tháng chỉ số', { exact: true }).fill('2026-01'); await page.getByLabel('Tìm phòng', { exact: true }).fill(prefix)
  await page.getByRole('button', { name: 'Tải danh sách phòng', exact: true }).click()
  await expect(page.getByRole('list', { name: 'Các dòng chỉ số nhiều phòng', exact: true }).getByRole('listitem')).toHaveCount(2)
}
function row(page: Page, name: string) { return page.getByRole('listitem', { name: new RegExp(`^Phòng ${name} ·`) }) }
async function fill(page: Page, name: string, previous: string, current: string) {
  const line = row(page, name)
  await line.getByRole('checkbox').check(); await line.getByLabel('Chỉ số đầu', { exact: true }).fill(previous); await line.getByLabel('Chỉ số cuối', { exact: true }).fill(current)
  return line
}
async function review(page: Page) {
  await page.getByRole('button', { name: 'Xem lại các chỉ số đã chọn', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Xem lại chỉ số nhiều phòng', exact: true })
  await dialog.getByRole('checkbox', { name: 'Tôi đã kiểm tra từng dòng và đồng ý lưu các chỉ số này.' }).check()
  return dialog
}

test('bulk meters require real first baselines, save separate rooms and preserve exact consumption before invoice locking', async ({ page }) => {
  await login(page)
  const first = await fixture(page, 'BULK-MULTI-A'), second = await fixture(page, 'BULK-MULTI-B')
  await openMonth(page, 'BULK-MULTI-')
  await expect(row(page, 'BULK-MULTI-A').getByLabel('Chỉ số đầu', { exact: true })).toHaveValue('')
  const a = await fill(page, 'BULK-MULTI-A', '100.125', '110.375'), b = await fill(page, 'BULK-MULTI-B', '20.100', '22.375')
  await expect(a.getByText('10,250 kWh', { exact: true })).toBeVisible(); await expect(b.getByText('2,275 kWh', { exact: true })).toBeVisible()
  const dialog = await review(page); await expect(dialog.getByRole('listitem')).toHaveCount(2)
  await dialog.getByRole('button', { name: 'Xác nhận lưu các chỉ số', exact: true }).click(); await expect(dialog).toHaveCount(0)
  await expect(a.getByText('Đã lưu', { exact: true })).toBeVisible(); await expect(b.getByText('Đã lưu', { exact: true })).toBeVisible()
  const firstReadings = await readings(page, first.stay.id), secondReadings = await readings(page, second.stay.id)
  expect(firstReadings).toHaveLength(1); expect(secondReadings).toHaveLength(1)
  expect(firstReadings[0].consumption).toBe('10.250'); expect(secondReadings[0].consumption).toBe('2.275')
  const preview = await write(page, `/occupancies/${first.stay.id}/invoices/preview`, { billing_month: '2026-01' })
  await write(page, `/occupancies/${first.stay.id}/invoices`, { billing_month: '2026-01', preview_token: preview.preview_token })
  await a.getByRole('button', { name: 'Tải lại riêng dòng này', exact: true }).click(); await page.getByRole('button', { name: 'Xác nhận tải lại dòng', exact: true }).click()
  await expect(a.getByRole('checkbox')).toBeDisabled(); await expect(a.getByRole('link', { name: 'Xem hóa đơn đã khóa', exact: true })).toBeVisible()
  await page.evaluate(() => (document.activeElement as HTMLElement)?.blur())
  await page.locator('.bulk-meters').screenshot({ path: resolve(shots, 'bulk-meters-owner.png'), animations: 'disabled' })
  await page.setViewportSize({ width: 320, height: 740 }); await page.reload()
  await expect(page.getByRole('heading', { name: 'Nhập chỉ số nhiều phòng', exact: true })).toBeVisible()
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true)
})

test('lost committed row response stops remaining queue and exact durable requests resume after reload and reauthentication', async ({ page }) => {
  await login(page)
  const first = await fixture(page, 'BULK-RETRY-A'), second = await fixture(page, 'BULK-RETRY-B')
  await openMonth(page, 'BULK-RETRY-'); await fill(page, 'BULK-RETRY-A', '100', '110'); await fill(page, 'BULK-RETRY-B', '200', '220')
  const dialog = await review(page), sent: unknown[] = []
  await page.route('**/api/owner/meter-readings/bulk/rows', async route => {
    sent.push(route.request().postDataJSON()); const response = await route.fetch(); expect(response.status()).toBe(201); await route.abort('failed')
  }, { times: 1 })
  await dialog.getByRole('button', { name: 'Xác nhận lưu các chỉ số', exact: true }).click(); await expect(dialog).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Xem lại các dòng đang chờ', exact: true })).toBeVisible()
  expect(await readings(page, first.stay.id)).toHaveLength(1); expect(await readings(page, second.stay.id)).toHaveLength(0)
  expect(await page.evaluate(() => Object.keys(localStorage).filter(key => key.startsWith('rental.bulk-meter.pending.v1:')).length)).toBe(2)
  await page.context().clearCookies(); await page.reload(); await expect(page).toHaveURL(/\/login(?:\?|$)/)
  await login(page); await page.goto('/owner/meters/bulk')
  await page.getByRole('button', { name: 'Xem lại các dòng đang chờ', exact: true }).click()
  const resumed = page.getByRole('dialog', { name: 'Xem lại chỉ số nhiều phòng', exact: true })
  await resumed.getByRole('checkbox', { name: 'Tôi đã kiểm tra từng dòng và đồng ý lưu các chỉ số này.' }).check()
  page.on('request', request => { if (request.method() === 'POST' && request.url().endsWith('/meter-readings/bulk/rows')) sent.push(request.postDataJSON()) })
  await resumed.getByRole('button', { name: 'Gửi lại các dòng đang chờ', exact: true }).click(); await expect(resumed).toHaveCount(0)
  expect(sent).toHaveLength(3)
  const original = sent[0] as { idempotency_key: string }
  const retried = sent.slice(1).filter(value => (value as { idempotency_key: string }).idempotency_key === original.idempotency_key)
  expect(retried).toHaveLength(1); expect(retried[0]).toEqual(original)
  expect(await readings(page, first.stay.id)).toHaveLength(1); expect(await readings(page, second.stay.id)).toHaveLength(1)
  expect(await page.evaluate(() => Object.keys(localStorage).some(key => key.startsWith('rental.bulk-meter.pending.v1:')))).toBe(false)
  await expect(page.getByRole('region', { name: 'Kết quả nhập chỉ số', exact: true }).getByText('Đã đối chiếu', { exact: true })).toBeVisible()
})

test('stale context rejects one row while the other saves and refresh does not erase neighboring drafts', async ({ page, browser, baseURL }) => {
  await login(page)
  const first = await fixture(page, 'BULK-CONFLICT-A'), second = await fixture(page, 'BULK-CONFLICT-B')
  await openMonth(page, 'BULK-CONFLICT-'); await fill(page, 'BULK-CONFLICT-A', '50', '60'); await fill(page, 'BULK-CONFLICT-B', '20', '30')
  const dialog = await review(page)
  await write(page, `/occupancies/${first.stay.id}/meter-readings`, { occupancy_service_id: first.period.id, period_start: '2026-01-01 00:00:00', period_end: '2026-01-10 00:00:00', previous_reading: '50', current_reading: '55' })
  await dialog.getByRole('button', { name: 'Xác nhận lưu các chỉ số', exact: true }).click(); await expect(dialog).toHaveCount(0)
  const a = row(page, 'BULK-CONFLICT-A'), b = row(page, 'BULK-CONFLICT-B')
  await expect(a.getByText('Dữ liệu đã thay đổi', { exact: true })).toBeVisible(); await expect(b.getByText('Đã lưu', { exact: true })).toBeVisible()
  expect(await readings(page, first.stay.id)).toHaveLength(1); expect(await readings(page, second.stay.id)).toHaveLength(1)
  await a.getByRole('button', { name: 'Tải lại riêng dòng này', exact: true }).click(); await page.getByRole('button', { name: 'Xác nhận tải lại dòng', exact: true }).click()
  await expect(a.getByLabel('Chỉ số đầu', { exact: true })).toHaveValue('55.000'); await expect(a.getByLabel('Chỉ số đầu', { exact: true })).toHaveAttribute('readonly', '')
  await expect(a.getByLabel('Chỉ số cuối', { exact: true })).toHaveValue(''); await expect(b.getByLabel('Chỉ số cuối', { exact: true })).toHaveValue('30')
  await page.setViewportSize({ width: 320, height: 740 })
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true)
  await a.scrollIntoViewIfNeeded(); await page.evaluate(() => (document.activeElement as HTMLElement)?.blur())
  await page.screenshot({ path: resolve(shots, 'bulk-meters-mobile.png'), animations: 'disabled' })
  const tenant = await browser.newContext({ baseURL }), tenantPage = await tenant.newPage()
  try { await login(tenantPage, 'e2e_tenant'); expect((await tenantPage.request.get('/api/owner/meter-readings/bulk?billing_month=2026-01')).status()).toBe(403); await tenantPage.goto('/owner/meters/bulk'); await expect(tenantPage).toHaveURL('/forbidden') } finally { await tenant.close() }
})

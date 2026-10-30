import { expect, test, type Page, type Download } from '@playwright/test'
import { mkdirSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const password = 'E2e-Password-2077!', root = resolve(process.cwd(), '.cache/e2e'), shots = resolve(root, 'screenshots'), files = resolve(root, 'pdf-downloads')
mkdirSync(shots, { recursive: true }); mkdirSync(files, { recursive: true })
test.describe.configure({ timeout: 120_000 })
test.beforeEach(({ page }) => page.setDefaultTimeout(15_000))
async function login(page: Page, username = 'e2e_owner') {
  await page.goto('/login'); await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username); await page.getByLabel('Mật khẩu', { exact: true }).fill(password)
  const result = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith('/api/auth/login'))
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click(); expect((await result).status()).toBe(200)
  await expect(page).toHaveURL(username === 'e2e_owner' ? '/owner' : '/tenant')
}
async function write(page: Page, path: string, data: unknown, method = 'POST') {
  await page.request.get('/api/auth/csrf-cookie'); const token = (await page.context().cookies()).find(c => c.name === 'XSRF-TOKEN')!
  const response = await page.request.fetch(`/api/owner${path}`, { method, data, headers: { 'X-XSRF-TOKEN': decodeURIComponent(token.value) } })
  expect(response.ok(), await response.text()).toBe(true); return response.status() === 204 ? null : (await response.json()).data
}
async function fixture(page: Page, tag: string) {
  const tenant = await write(page, '/tenants', { full_name: `Người thuê ${tag}`, phone_number: '0900000000' }), username = tag.toLowerCase().replaceAll('-', '_')
  await write(page, `/tenants/${tenant.id}/account`, { username, password, password_confirmation: password, is_active: true }, 'PUT')
  const room = await write(page, '/rooms', { room_number: tag, capacity: 1 }), group = await write(page, '/rental-groups', { group_code: tag })
  const stay = await write(page, '/occupancies', { room_id: room.id, rental_group_id: group.id, tenant_ids: [tenant.id], representative_tenant_id: tenant.id, started_at: '2026-01-01 00:00:00' })
  const preview = await write(page, `/occupancies/${stay.id}/invoices/preview`, { billing_month: '2026-01' })
  const invoice = await write(page, `/occupancies/${stay.id}/invoices`, { billing_month: '2026-01', preview_token: preview.preview_token })
  const payment = await write(page, `/invoices/${invoice.id}/payments`, { amount: '1000000', paid_at: invoice.issued_at, payment_method: 'cash', idempotency_key: crypto.randomUUID() })
  return { invoice, payment, username }
}
async function checkDownload(download: Download, name: string) {
  expect(download.suggestedFilename()).toBe(name)
  const path = resolve(files, name); await download.saveAs(path)
  const bytes = readFileSync(path); expect(bytes.subarray(0, 5).toString()).toBe('%PDF-'); expect(bytes.length).toBeGreaterThan(5000)
}

test('owner configures property, detects concurrent changes and settings fit a narrow screen', async ({ page }) => {
  await login(page); await page.getByRole('link', { name: 'Thông tin nhà trọ', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Thông tin nhà trọ', exact: true })).toBeVisible()
  await page.getByLabel('Tên nhà trọ (bắt buộc)', { exact: true }).fill('Nhà trọ Bình An - kiểm thử')
  await page.getByLabel('Địa chỉ nhà trọ (bắt buộc)', { exact: true }).fill('28 Đường Bình An, Thành phố Hồ Chí Minh')
  await page.getByLabel('Người liên hệ', { exact: true }).fill('Nguyễn Văn An')
  await page.getByLabel('Điện thoại liên hệ', { exact: true }).fill('0900000000')
  await page.getByLabel('Email liên hệ', { exact: true }).fill('nhatro@example.test')
  await page.getByLabel('Ngân hàng', { exact: true }).fill('Ngân hàng dùng thử')
  await page.getByLabel('Số tài khoản', { exact: true }).fill('000123456789')
  await page.getByLabel('Tên chủ tài khoản', { exact: true }).fill('NGUYEN VAN AN')
  await page.getByLabel('Ghi chú hiển thị trên chứng từ', { exact: true }).fill('Vui lòng ghi mã hóa đơn khi chuyển khoản.')
  await page.getByRole('button', { name: 'Xem lại thông tin', exact: true }).click()
  let dialog = page.getByRole('dialog', { name: 'Xem lại thông tin nhà trọ', exact: true })
  await dialog.getByRole('checkbox', { name: 'Tôi đã kiểm tra thông tin liên hệ và tài khoản nhận tiền.' }).check()
  await dialog.getByRole('button', { name: 'Xác nhận lưu thông tin', exact: true }).click(); await expect(dialog).toHaveCount(0)
  const profile = (await (await page.request.get('/api/owner/property')).json()).data
  expect(profile.bank_account_number).toBe('000123456789'); expect(profile.configured).toBe(true)
  await write(page, '/property', { ...Object.fromEntries(['name', 'address', 'contact_name', 'phone', 'email', 'bank_name', 'bank_account_number', 'bank_account_name', 'notes'].map(key => [key, profile[key]])), name: 'Nhà trọ đã cập nhật từ nơi khác', version_token: profile.version_token }, 'PUT')
  await page.getByLabel('Tên nhà trọ (bắt buộc)', { exact: true }).fill('Tên cũ chưa đối chiếu')
  await page.getByRole('button', { name: 'Xem lại thông tin', exact: true }).click(); dialog = page.getByRole('dialog')
  await dialog.getByRole('checkbox').check(); await dialog.getByRole('button', { name: 'Xác nhận lưu thông tin', exact: true }).click()
  await expect(dialog.getByRole('button', { name: 'Tải bản mới để đối chiếu', exact: true })).toBeVisible()
  await dialog.getByRole('button', { name: 'Tải bản mới để đối chiếu', exact: true }).click()
  await expect(page.getByLabel('Tên nhà trọ (bắt buộc)', { exact: true })).toHaveValue('Nhà trọ đã cập nhật từ nơi khác')
  await page.setViewportSize({ width: 320, height: 740 }); await page.reload(); await expect(page.getByLabel('Số tài khoản', { exact: true })).toHaveValue('000123456789')
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true)
  await page.evaluate(() => (document.activeElement as HTMLElement)?.blur()); await page.screenshot({ path: resolve(shots, 'property-owner-mobile.png'), animations: 'disabled' })
})

test('owner and authorized tenant download actual invoice and voided receipt PDFs, revoked grants stop downloads', async ({ page, browser, baseURL }) => {
  await login(page); const f = await fixture(page, 'PDF-DOWNLOAD')
  await write(page, `/payments/${f.payment.id}/corrections`, { action: 'void', reason: 'Đối chiếu phiếu ghi nhận nhầm để kiểm tra PDF', version_token: f.payment.version_token, idempotency_key: crypto.randomUUID() })
  await page.goto(`/owner/invoices/${f.invoice.id}`)
  let download = page.waitForEvent('download'); await page.getByRole('button', { name: 'Tải PDF hóa đơn', exact: true }).click(); await checkDownload(await download, `hoa-don-${f.invoice.id}.pdf`)
  await page.getByRole('button', { name: `Xem lần thu PT-${f.payment.id}`, exact: true }).click()
  download = page.waitForEvent('download'); await page.getByRole('button', { name: 'Tải PDF phiếu thu', exact: true }).click(); await checkDownload(await download, `phieu-thu-${f.payment.id}.pdf`)
  const context = await browser.newContext({ baseURL }), tenantPage = await context.newPage()
  try {
    await login(tenantPage, f.username); await tenantPage.goto(`/tenant/invoices/${f.invoice.id}`)
    download = tenantPage.waitForEvent('download'); await tenantPage.getByRole('button', { name: 'Tải PDF hóa đơn', exact: true }).click(); await checkDownload(await download, `hoa-don-${f.invoice.id}.pdf`)
    download = tenantPage.waitForEvent('download'); await tenantPage.getByRole('button', { name: `Tải PDF phiếu PT-${f.payment.id}`, exact: true }).click(); await checkDownload(await download, `phieu-thu-${f.payment.id}.pdf`)
    const response = await tenantPage.request.get(`/api/tenant/invoices/${f.invoice.id}/pdf`)
    expect(response.status()).toBe(200); expect(response.headers()['cache-control']).toContain('no-store'); expect(response.headers()['content-type']).toContain('application/pdf')
    expect((await tenantPage.request.get(`/api/tenant/invoices/${f.invoice.id}/payments/999999/pdf`)).status()).toBe(404)
    await expect(tenantPage.getByRole('link', { name: 'Thông tin nhà trọ', exact: true })).toHaveCount(0)
    expect((await tenantPage.request.get('/api/owner/property')).status()).toBe(403)
    await write(page, `/invoices/${f.invoice.id}/access`, undefined, 'DELETE')
    const forbidden = tenantPage.waitForResponse(r => r.url().endsWith(`/api/tenant/invoices/${f.invoice.id}/pdf`))
    await tenantPage.getByRole('button', { name: 'Tải PDF hóa đơn', exact: true }).click(); expect((await forbidden).status()).toBe(404)
    await expect(tenantPage.getByRole('alert')).toContainText('Không tìm thấy')
    await tenantPage.setViewportSize({ width: 320, height: 740 })
    expect(await tenantPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true)
  } finally { await context.close() }
})

test('a failed download is retryable and an old account response cannot start a download after logout', async ({ page }) => {
  await login(page); const f = await fixture(page, 'PDF-SESSION'); await page.goto(`/owner/invoices/${f.invoice.id}`)
  await page.route(`**/api/owner/invoices/${f.invoice.id}/pdf`, route => route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'fixture' }) }), { times: 1 })
  await page.getByRole('button', { name: 'Tải PDF hóa đơn', exact: true }).click(); await expect(page.getByRole('alert')).toContainText('Máy chủ')
  const retry = page.waitForEvent('download'); await page.getByRole('button', { name: 'Tải PDF hóa đơn', exact: true }).click(); await checkDownload(await retry, `hoa-don-${f.invoice.id}.pdf`)
  let release: (() => void) | undefined, arrived: (() => void) | undefined, completed: (() => void) | undefined
  const started = new Promise<void>(resolve => { arrived = resolve }), unblock = new Promise<void>(resolve => { release = resolve }), finished = new Promise<void>(resolve => { completed = resolve })
  await page.route(`**/api/owner/invoices/${f.invoice.id}/pdf`, async route => { const response = await route.fetch(); arrived?.(); await unblock; try { await route.fulfill({ response }) } catch { /* The old page intentionally cancels its private request. */ } finally { completed?.() } }, { times: 1 })
  const unexpected: Download[] = []; page.on('download', value => unexpected.push(value))
  const cancelled = page.waitForEvent('requestfailed', request => request.url().endsWith(`/api/owner/invoices/${f.invoice.id}/pdf`))
  await page.getByRole('button', { name: 'Tải PDF hóa đơn', exact: true }).click(); await started
  try { await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click(); await expect(page).toHaveURL(/\/login(?:\?|$)/); await cancelled }
  finally { release?.(); await finished }
  await expect(page.getByLabel('Tên đăng nhập', { exact: true })).toBeVisible()
  expect(unexpected).toHaveLength(0)
})

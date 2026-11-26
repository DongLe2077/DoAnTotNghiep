import { expect, test, type Page, type APIRequestContext } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'
import { photoFixture } from './support/photo-fixture'
const password = 'E2e-Password-2077!'
const shots = resolve(process.cwd(), '.cache/e2e/screenshots')
mkdirSync(shots, { recursive: true })
const png = photoFixture()
const image = { name: 'anh-su-co.png', mimeType: 'image/png', buffer: png }
test.describe.configure({ timeout: 90_000 })
test.beforeEach(({ page }) => { page.setDefaultTimeout(15_000) })
async function apiWrite(request: APIRequestContext, method: string, path: string, data?: unknown) {
  await request.get('/api/auth/csrf-cookie')
  const cookie = (await request.storageState()).cookies.find(item => item.name === 'XSRF-TOKEN')!
  const response = await request.fetch(`/api${path}`, { method, data, headers: { 'X-XSRF-TOKEN': decodeURIComponent(cookie.value) } })
  expect(response.ok(), await response.text()).toBe(true)
  return response.status() === 204 ? null : await response.json()
}
async function owner(request: APIRequestContext) { await apiWrite(request, 'POST', '/auth/login', { username: 'e2e_owner', password }) }
async function login(page: Page, username: string) {
  await page.goto('/login'); await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username)
  await page.getByLabel('Mật khẩu', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await expect(page).toHaveURL(username === 'e2e_owner' ? '/owner' : '/tenant')
}
async function context(request: APIRequestContext, prefix: string, member = false) {
  await owner(request)
  async function person(suffix: string) {
    const data = (await apiWrite(request, 'POST', '/owner/tenants', { full_name: `${prefix} ${suffix}`, phone_number: '0900123456', identity_number: `${prefix}-${suffix}` })).data
    const username = `${prefix.toLowerCase()}_${suffix.toLowerCase()}`
    await apiWrite(request, 'PUT', `/owner/tenants/${data.id}/account`, { username, password, password_confirmation: password, is_active: true })
    return { ...data, username }
  }
  const tenant = await person('Rep'), other = member ? await person('Member') : null
  const room = (await apiWrite(request, 'POST', '/owner/rooms', { room_number: prefix, capacity: 3 })).data
  const group = (await apiWrite(request, 'POST', '/owner/rental-groups', { group_code: `NHOM-${prefix}` })).data
  const stay = (await apiWrite(request, 'POST', '/owner/occupancies', { room_id: room.id, rental_group_id: group.id, tenant_ids: [tenant.id, ...(other ? [other.id] : [])], representative_tenant_id: tenant.id, started_at: '2026-01-01 00:00:00' })).data
  return { tenant, other, room, group, stay }
}
async function fillReport(page: Page, title: string) {
  await page.goto('/tenant/maintenance/new')
  await page.getByLabel('Tiêu đề sự cố (bắt buộc)', { exact: true }).fill(title)
  await page.getByLabel('Mô tả sự cố (bắt buộc)', { exact: true }).fill('Nước rỉ ở van dưới bồn rửa. Nhờ chủ trọ kiểm tra.')
}
async function sendReport(page: Page) {
  await page.getByRole('button', { name: 'Xem lại trước khi gửi', exact: true }).click()
  await page.getByRole('button', { name: 'Xác nhận gửi báo hỏng', exact: true }).click()
}
async function noOverflow(page: Page) { expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true) }

test('NT1 shows own agreed room/services/profile/history and keeps other residents private', async ({ page, request }) => {
  const c = await context(request, 'NT1-ROOM', true)
  const service = (await apiWrite(request, 'POST', '/owner/services', { service_code: 'NT1-WIFI', name: 'Internet NT1', unit: 'phòng/tháng', billing_method: 'per_room', default_unit_price: '100000.1234', is_active: true })).data
  await apiWrite(request, 'POST', `/owner/occupancies/${c.stay.id}/services`, { service_id: service.id, effective_from: '2026-01-01 00:00:00' })
  await apiWrite(request, 'PUT', `/owner/services/${service.id}`, { ...service, default_unit_price: '200000.0000', is_active: false })
  await login(page, c.tenant.username)
  await expect(page.getByRole('heading', { name: `Phòng ${c.room.room_number}`, exact: true })).toBeVisible()
  await expect(page.getByText('Giá thuê toàn phòng / tháng', { exact: true })).toBeVisible()
  await expect(page.getByText('100.000,1234 ₫ / phòng/tháng', { exact: true })).toBeVisible()
  const serviceTerms = page.getByText(/Phân bổ theo thời gian sử dụng trong tháng/)
  await expect(serviceTerms).toBeHidden()
  await page.getByText('Cách tính và thời gian áp dụng', { exact: true }).click()
  await expect(serviceTerms).toBeVisible()
  await expect(page.getByText(c.other.full_name, { exact: true })).toHaveCount(0)
  await page.screenshot({ path: resolve(shots, 'nt1-room-desktop.png'), fullPage: true, animations: 'disabled' })
  await page.goto('/tenant/profile'); await expect(page.getByRole('heading', { name: c.tenant.full_name, exact: true })).toBeVisible()
  await expect(page.getByText(c.tenant.identity_number, { exact: true })).toBeVisible()
  await expect(page.getByText(c.other.identity_number, { exact: true })).toHaveCount(0)
  await page.goto('/tenant/stays'); await expect(page.getByRole('heading', { name: `Phòng ${c.room.room_number}`, exact: true })).toBeVisible()
  await page.setViewportSize({ width: 390, height: 844 }); await page.goto('/tenant')
  await expect(page.getByRole('heading', { name: `Phòng ${c.room.room_number}`, exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Mở menu', exact: true })).toBeVisible(); await noOverflow(page)
  await page.getByRole('heading', { name: 'Phòng của tôi', exact: true }).click()
  await page.screenshot({ path: resolve(shots, 'nt1-room-mobile.png'), animations: 'disabled' })
  await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click(); await expect(page).toHaveURL(/\/login/); await login(page, c.other.username)
  await page.getByText('Thông tin cư trú', { exact: true }).click()
  await expect(page.getByText('Bạn là thành viên đang cư trú.', { exact: false })).toBeVisible()
  await page.goto('/tenant/maintenance/new'); await expect(page.getByRole('heading', { name: 'Bạn chưa có quyền gửi báo hỏng mới', exact: true })).toBeVisible()
})

test('NT1 empty and unlinked profiles are clear and a failed refresh removes private details', async ({ page }) => {
  await login(page, 'e2e_notify_empty')
  await expect(page.getByRole('heading', { name: 'Bạn chưa có phòng đang cư trú', exact: true })).toBeVisible()
  await page.goto('/tenant/stays'); await expect(page.getByRole('heading', { name: 'Bạn chưa có lịch sử cư trú', exact: true })).toBeVisible()
  await page.goto('/tenant/profile'); await expect(page.getByRole('heading', { name: 'Người thuê chưa có thông báo', exact: true })).toBeVisible()
  await page.route('**/api/tenant/profile', route => route.abort('failed'))
  await page.getByRole('button', { name: 'Làm mới hồ sơ', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Chưa tải được hồ sơ', exact: true })).toBeVisible()
  await expect(page.getByText('Người thuê chưa có thông báo', { exact: true })).toHaveCount(0)
  await page.unroute('**/api/tenant/profile'); await page.getByRole('button', { name: 'Tải lại', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Người thuê chưa có thông báo', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click(); await expect(page).toHaveURL(/\/login/); await login(page, 'e2e_unlinked_tenant')
  await expect(page.getByRole('heading', { name: 'Chưa có hồ sơ liên kết', exact: true })).toBeVisible()
})

test('NT2 follows grants, partial/full receipts and revocation without offering owner payment actions', async ({ page, request }) => {
  const c = await context(request, 'NT2-BILL')
  const preview = (await apiWrite(request, 'POST', `/owner/occupancies/${c.stay.id}/invoices/preview`, { billing_month: '2026-01' })).data
  const invoice = (await apiWrite(request, 'POST', `/owner/occupancies/${c.stay.id}/invoices`, { billing_month: '2026-01', preview_token: preview.preview_token })).data
  await apiWrite(request, 'POST', `/owner/invoices/${invoice.id}/payments`, { idempotency_key: crypto.randomUUID(), amount: '300000.25', paid_at: invoice.issued_at, payment_method: 'cash' })
  await login(page, c.tenant.username)
  await page.getByRole('link', { name: 'Hóa đơn', exact: true }).click()
  const periodFilter = page.getByLabel('Kỳ hóa đơn', { exact: true })
  await expect(periodFilter).toBeHidden()
  await page.getByText('Bộ lọc hóa đơn', { exact: true }).press('Enter')
  await expect(periodFilter).toBeVisible()
  await periodFilter.fill('2026-01')
  await page.getByRole('combobox', { name: 'Trạng thái', exact: true }).selectOption('partially_paid')
  await page.getByRole('button', { name: 'Lọc hóa đơn', exact: true }).click()
  await expect(page).toHaveURL('/tenant/invoices?billing_month=2026-01&payment_status=partially_paid')
  await page.reload()
  await expect(page.getByLabel('Kỳ hóa đơn', { exact: true })).toHaveValue('2026-01')
  await page.getByRole('link', { name: 'Xóa bộ lọc', exact: true }).click()
  await expect(page).toHaveURL('/tenant/invoices')
  await expect(periodFilter).toBeHidden()
  await page.getByRole('link', { name: 'Xem hóa đơn và lịch sử thanh toán →', exact: true }).click()
  await expect(page).toHaveURL(`/tenant/invoices/${invoice.id}`)
  await expect(page.locator('.ui-badge').filter({ hasText: 'Thanh toán một phần' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Còn cần thanh toán', exact: true })).toBeVisible()
  await expect(page.getByText('Trả trực tiếp cho chủ trọ', { exact: true })).toBeVisible()
  await expect(page.getByText('Khi chuyển khoản,', { exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: /^PT-\d+ · 300\.000,25 ₫$/ })).toBeVisible()
  await page.screenshot({ path: resolve(shots, `nt2-payment-guide-${test.info().project.name}.png`), animations: 'disabled' })
  await expect(page.getByRole('button', { name: /Ghi nhận thu tiền|Xác nhận đã nhận tiền/ })).toHaveCount(0)
  const balance = (await (await request.get(`/api/owner/invoices/${invoice.id}`)).json()).data.outstanding_amount
  await apiWrite(request, 'POST', `/owner/invoices/${invoice.id}/payments`, { idempotency_key: crypto.randomUUID(), amount: balance, paid_at: invoice.issued_at, payment_method: 'bank_transfer' })
  await page.getByRole('button', { name: 'Cập nhật thanh toán', exact: true }).click()
  await expect(page.getByText('Đã thanh toán đủ', { exact: true })).toBeVisible()
  await expect(page.getByText('Trả trực tiếp cho chủ trọ', { exact: true })).toHaveCount(0)
  await page.screenshot({ path: resolve(shots, 'nt2-invoice.png'), fullPage: true, animations: 'disabled' })
  await apiWrite(request, 'DELETE', `/owner/invoices/${invoice.id}/access`)
  await page.getByRole('button', { name: 'Cập nhật thanh toán', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Hóa đơn không còn khả dụng', exact: true })).toBeVisible()
  await expect(page.getByText(invoice.invoice_number, { exact: true })).toHaveCount(0)
  await page.goto('/tenant/invoices'); await expect(page.getByRole('heading', { name: 'Chưa có hóa đơn phù hợp', exact: true })).toBeVisible()
})

test('NT2 restores the filtered invoice page and scroll after returning from a detail', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await login(page, 'e2e_notify_tenant')
  const response = await page.request.get('/api/tenant/invoices/1')
  expect(response.ok()).toBe(true)
  const { data: invoice } = await response.json()
  const rows = [...Array.from({ length: 14 }, (_, index) => ({ ...invoice, id: 100 + index })), invoice]
  let failNext = false
  await page.route(url => url.pathname === '/api/tenant/invoices', async route => {
    await new Promise(resolve => setTimeout(resolve, 650))
    const query = new URL(route.request().url()).searchParams
    expect(query.get('due_status')).toBe('overdue')
    if (failNext) {
      failNext = false
      await route.fulfill({ status: 503, json: { message: 'Kết nối thử nghiệm gián đoạn.' } })
      return
    }
    await route.fulfill({ json: { data: rows, meta: { current_page: Number(query.get('page')), last_page: 2, total: 30 } } })
  })
  await page.goto('/tenant/invoices?due_status=overdue')
  await expect(page.getByText('Đang tải hóa đơn…', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Trang sau', exact: true }).click()
  await expect(page).toHaveURL('/tenant/invoices?due_status=overdue&page=2')
  const invoiceLink = page.locator('a[href="/tenant/invoices/1"]')
  const list = page.locator('.tenant-invoice-list')
  for (const useBrowserBack of [false, true]) {
    await invoiceLink.scrollIntoViewIfNeeded()
    const scroll = await invoiceLink.evaluate(link => {
      if (!(link instanceof HTMLAnchorElement)) throw new Error('Expected an invoice link')
      const top = -link.closest('.tenant-invoice-list')!.getBoundingClientRect().top
      link.click()
      return top
    })
    expect(scroll).toBeGreaterThan(500)
    await expect(page).toHaveURL('/tenant/invoices/1')
    if (useBrowserBack) {
      failNext = true
      await page.goBack()
      await page.getByRole('button', { name: 'Tải lại', exact: true }).click()
    }
    else await page.getByRole('link', { name: '← Hóa đơn của tôi', exact: true }).click()
    await expect(page).toHaveURL('/tenant/invoices?due_status=overdue&page=2')
    await expect(page.getByRole('combobox', { name: 'Hạn thanh toán', exact: true })).toHaveValue('overdue')
    await expect(page.getByText('30 kết quả · Trang 2 / 2', { exact: true })).toBeVisible()
    await expect.poll(async () => Math.abs(await list.evaluate(el => -el.getBoundingClientRect().top) - scroll)).toBeLessThanOrEqual(8)
  }
})

test('NT2 payOS return waits for server confirmation and updates the invoice without a manual refresh', async ({ page, request }) => {
  const c = await context(request, 'NT2-PAYOS')
  const preview = (await apiWrite(request, 'POST', `/owner/occupancies/${c.stay.id}/invoices/preview`, { billing_month: '2026-01' })).data
  const invoice = (await apiWrite(request, 'POST', `/owner/occupancies/${c.stay.id}/invoices`, { billing_month: '2026-01', preview_token: preview.preview_token })).data
  const path = `/tenant/invoices/${invoice.id}`
  const checkoutUrl = 'https://pay.payos.vn/web/isolated-e2e-checkout'
  let started = false, paid = false
  await page.route(`**/api${path}`, async route => {
    const response = await route.fetch()
    const { data } = await response.json()
    const current = paid ? { ...data, payment_status: 'paid', outstanding_amount: '0.00', paid_amount: data.total_amount,
      payments: [{ id: 43210, amount: data.total_amount, paid_at: data.issued_at, payment_method: 'bank_transfer', status: 'active', source: 'payos' }] } : data
    await route.fulfill({ response, json: { data: { ...current, payos_configured: true } } })
  })
  await page.route(`**/api${path}/payos`, async route => {
    if (route.request().method() === 'POST') started = true
    await route.fulfill({ json: { data: { status: paid ? 'paid' : started ? 'pending' : 'idle',
      payment_id: paid ? 43210 : null, can_start: !paid, checkout_url: started && !paid ? checkoutUrl : null } } })
  })
  await page.route(checkoutUrl, route => route.fulfill({ contentType: 'text/html', body: '<h1>payOS checkout thử</h1>' }))
  await login(page, c.tenant.username)
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto(path)
  const checkoutButton = page.getByRole('button', { name: 'Thanh toán qua ngân hàng', exact: true })
  await expect(checkoutButton).toBeInViewport()
  await expect(page.getByRole('heading', { name: 'Còn cần thanh toán', exact: true })).toBeInViewport()
  await noOverflow(page)
  await checkoutButton.click()
  await expect(page).toHaveURL(checkoutUrl)
  await page.goto(`${path}?payos=return`)
  await expect(page.getByText('Đang kiểm tra thanh toán', { exact: true })).toBeVisible()
  await expect(page.getByText('Thanh toán thành công', { exact: true })).toHaveCount(0)
  paid = true
  await expect(page.getByText('Thanh toán thành công', { exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Tải phiếu thu', exact: true })).toBeInViewport()
  await expect(page.getByRole('button', { name: /Tải PDF phiếu PT-43210/ })).toHaveCount(0)
  await expect(checkoutButton).toHaveCount(0)
  await expect(page.getByText('Đã thanh toán đủ', { exact: true })).toBeVisible()
})

test('NT3 lost photo response retries only images and completes the owner-to-tenant notification flow after checkout', async ({ page, request }) => {
  const c = await context(request, 'NT3-PHOTO')
  await login(page, c.tenant.username)
  await page.route('**/api/tenant/maintenance-requests/eligible-occupancies', async route => {
    const response = await route.fetch()
    const { data } = await response.json()
    expect(data).toHaveLength(1)
    await route.fulfill({ response, json: { data: [...data, { ...data[0], occupancy_id: 99999, room: { ...data[0].room, room_number: 'PHONG-KHAC' } }] } })
  }, { times: 1 })
  await page.goto('/tenant/maintenance/new')
  const roomSelector = page.getByRole('combobox', { name: 'Phòng báo hỏng', exact: true })
  await expect(roomSelector).toHaveValue('')
  await expect(roomSelector.getByRole('option')).toHaveCount(3)
  await roomSelector.selectOption(String(c.stay.id))
  await fillReport(page, 'NT3 vòi nước cần sửa')
  await expect(roomSelector).toHaveCount(0)
  await expect(page.getByText(`Phòng ${c.room.room_number}`, { exact: true })).toBeVisible()
  const chooser = page.waitForEvent('filechooser')
  await page.getByRole('button', { name: 'Chọn ảnh từ thiết bị', exact: true }).click()
  await (await chooser).setFiles([image, { ...image, name: 'anh-trung.png' }])
  await page.screenshot({ path: resolve(shots, 'nt3-new-report.png'), fullPage: true, animations: 'disabled' })
  let creates = 0, uploads = 0
  page.on('request', request => { if (request.method() === 'POST' && /\/occupancies\/\d+\/maintenance-requests$/.test(request.url())) creates++ })
  await page.route('**/api/tenant/maintenance-requests/*/photos', async route => { uploads++; if (uploads === 1) { const result = await route.fetch(); expect(result.ok()).toBe(true); await route.abort('failed') } else await route.continue() })
  await expect(page.getByRole('list', { name: 'Các bước gửi báo hỏng' }).getByText('1. Mô tả sự cố')).toHaveAttribute('aria-current', 'step')
  await page.getByRole('button', { name: 'Xem lại trước khi gửi', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Xem lại yêu cầu', exact: true })).toBeFocused()
  await expect(page.getByRole('list', { name: 'Các bước gửi báo hỏng' }).getByText('2. Xem lại và gửi')).toHaveAttribute('aria-current', 'step')
  await page.getByRole('button', { name: 'Xác nhận gửi báo hỏng', exact: true }).dblclick()
  await expect(page.getByText('Báo hỏng đã được gửi', { exact: true })).toBeVisible()
  await expect(page.getByRole('alert')).toBeVisible(); expect(creates).toBe(1)
  await page.getByRole('button', { name: 'Gửi lại ảnh đã chọn', exact: true }).click()
  await expect(page).toHaveURL(/\/tenant\/maintenance\/\d+\?created=1$/)
  const id = Number(new URL(page.url()).pathname.split('/').at(-1))
  expect(uploads).toBe(2); expect(creates).toBe(1)
  const report = (await (await page.request.get(`/api/tenant/maintenance-requests/${id}`)).json()).data
  expect(report.photos).toHaveLength(1)
  await expect(page.getByRole('status').filter({ hasText: 'Chờ tiếp nhận' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Xem ảnh báo hỏng 1', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Xem ảnh báo hỏng 1', exact: true }).click()
  await expect(page.getByRole('dialog', { name: 'Ảnh báo hỏng', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Đóng ảnh', exact: true }).click()
  await page.getByText('Bổ sung ảnh sự cố', { exact: true }).click()
  const anotherPhoto = page.waitForEvent('filechooser')
  await page.getByRole('button', { name: 'Chọn ảnh từ thiết bị', exact: true }).click()
  await (await anotherPhoto).setFiles({ ...image, name: 'anh-bo-sung.png', buffer: photoFixture(260, 160) })
  await page.getByRole('button', { name: 'Gửi ảnh đã chọn', exact: true }).click()
  await expect(page.getByText('Ảnh đã được gửi thành công.', { exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Xem ảnh báo hỏng 2', exact: true })).toBeVisible()
  expect(uploads).toBe(3); expect(creates).toBe(1)
  const ownerReport = (await (await request.get(`/api/owner/maintenance-requests/${id}`)).json()).data
  const processing = (await apiWrite(request, 'PATCH', `/owner/maintenance-requests/${id}`, { version_token: ownerReport.version_token, status: 'in_progress', resolution: 'Đã hẹn thợ kiểm tra.' })).data
  await apiWrite(request, 'PATCH', `/owner/maintenance-requests/${id}`, { version_token: processing.version_token, status: 'resolved', resolution: 'Đã thay van và kiểm tra hết rò.' })
  const now = (await (await request.get('/api/owner/dashboard')).json()).data.as_of
  await apiWrite(request, 'POST', `/owner/occupancies/${c.stay.id}/checkout`, { ended_at: now })
  await page.getByRole('button', { name: 'Cập nhật tiến độ', exact: true }).click()
  await expect(page.getByText('Đã thay van và kiểm tra hết rò.', { exact: true })).toBeVisible()
  await expect(page.getByRole('status').filter({ hasText: 'Đã xử lý' })).toBeVisible()
  await expect(page.getByText('Bổ sung ảnh sự cố', { exact: true })).toHaveCount(0)
  await page.setViewportSize({ width: 390, height: 844 }); await noOverflow(page)
  await page.screenshot({ path: resolve(shots, 'nt3-report-mobile.png'), animations: 'disabled' })
  await page.goto('/tenant'); await expect(page.getByRole('heading', { name: 'Bạn chưa có phòng đang cư trú', exact: true })).toBeVisible()
  await page.goto('/notifications'); await page.getByRole('button', { name: /^Xem báo hỏng:/ }).first().click()
  await page.getByRole('dialog').getByRole('link', { name: 'Mở trang chi tiết →', exact: true }).click()
  await expect(page).toHaveURL(`/tenant/maintenance/${id}`)
  await expect(page.getByRole('heading', { name: 'NT3 vòi nước cần sửa', exact: true })).toBeVisible()
})

test('NT3 validates local files and recovers a real invalid-image rejection without recreating the report', async ({ page, request }) => {
  const c = await context(request, 'NT3-VALID')
  await login(page, c.tenant.username); await fillReport(page, 'NT3 kiểm tra ảnh hợp lệ')
  const picker = page.getByLabel('Ảnh sự cố', { exact: false })
  await picker.setInputFiles(Array.from({ length: 6 }, (_, index) => ({ ...image, name: `${index}.png` })))
  await expect(page.getByRole('alert')).toContainText('tối đa 5 ảnh')
  await picker.setInputFiles({ name: 'anh.svg', mimeType: 'image/svg+xml', buffer: Buffer.from('<svg/>') })
  await expect(page.getByRole('alert')).toContainText('Chỉ chọn ảnh')
  await picker.setInputFiles({ name: 'qua-lon.png', mimeType: 'image/png', buffer: Buffer.alloc(5 * 1024 * 1024 + 1) })
  await expect(page.getByRole('alert')).toContainText('tối đa 5 MB')
  await picker.setInputFiles({ name: 'gia-anh.png', mimeType: 'image/png', buffer: Buffer.from('not an image') })
  let creates = 0
  page.on('request', request => { if (request.method() === 'POST' && /\/occupancies\/\d+\/maintenance-requests$/.test(request.url())) creates++ })
  await sendReport(page); await expect(page.getByText('Báo hỏng đã được gửi', { exact: true })).toBeVisible()
  await expect(page.locator('.field-error').first()).toBeVisible()
  await page.getByRole('button', { name: 'Bỏ ảnh 1', exact: true }).click(); await picker.setInputFiles({ name: 'anh-qua-rong.png', mimeType: 'image/png', buffer: photoFixture(5000, 10) })
  await page.getByRole('button', { name: 'Gửi lại ảnh đã chọn', exact: true }).click()
  await expect(page.locator('.field-error').first()).toBeVisible()
  await page.getByRole('button', { name: 'Bỏ ảnh 1', exact: true }).click(); await picker.setInputFiles(image)
  await page.getByRole('button', { name: 'Gửi lại ảnh đã chọn', exact: true }).click()
  await expect(page).toHaveURL(/\/tenant\/maintenance\/\d+\?created=1$/); expect(creates).toBe(1)
  await expect(page.getByRole('button', { name: 'Xem ảnh báo hỏng 1', exact: true })).toBeVisible()
})

test('NT3 an explicit CSRF retry does not send automatically or alter report payload', async ({ page, request }) => {
  const c = await context(request, 'NT3-CSRF')
  await login(page, c.tenant.username); await fillReport(page, 'NT3 sự cố kiểm tra CSRF')
  const payloads: unknown[] = []
  await page.route('**/api/tenant/occupancies/*/maintenance-requests', async route => { payloads.push(route.request().postDataJSON()); if (payloads.length === 1) await route.fulfill({ status: 419, contentType: 'application/json', body: JSON.stringify({ message: 'Phiên bảo mật đã đổi.' }) }); else await route.continue() })
  await sendReport(page); await expect(page.getByRole('alert')).toBeVisible(); expect(payloads).toHaveLength(1)
  await page.getByRole('button', { name: 'Gửi lại yêu cầu đang chờ', exact: true }).click()
  await expect(page).toHaveURL(/\/tenant\/maintenance\/\d+\?created=1$/)
  expect(payloads).toHaveLength(2); expect(payloads[1]).toEqual(payloads[0])
})

test('NT tenant privacy, account expiry and owner role guards apply to all new screens', async ({ page, request }) => {
  await login(page, 'e2e_notify_tenant')
  await page.goto('/tenant/maintenance/2')
  await expect(page.getByRole('heading', { name: 'Yêu cầu không còn khả dụng', exact: true })).toBeVisible()
  expect((await page.request.get('/api/tenant/maintenance-requests/2/photos/1')).status()).toBe(404)
  await page.goto('/tenant/profile'); await expect(page.getByRole('heading', { name: 'Nguyễn Minh An', exact: true })).toBeVisible()
  await apiWrite(page.request, 'POST', '/auth/logout')
  await page.getByRole('button', { name: 'Làm mới hồ sơ', exact: true }).click()
  await expect(page).toHaveURL(/\/login/)
  await expect(page.getByText('012345678902', { exact: true })).toHaveCount(0)
  await login(page, 'e2e_owner')
  for (const path of ['/tenant', '/tenant/profile', '/tenant/stays', '/tenant/invoices', '/tenant/invoices/1', '/tenant/maintenance', '/tenant/maintenance/new', '/tenant/maintenance/1']) { await page.goto(path); await expect(page).toHaveURL('/forbidden') }
  await owner(request)
  expect((await request.get('/api/tenant/profile')).status()).toBe(403)
})

test('representative and owner complete checkout from their workspaces', async ({ page, request, browser }) => {
  const c = await context(request, 'CHECKOUT-ROOM')
  const invoicePath = '/owner/occupancies/' + c.stay.id + '/invoices'
  const bill = { billing_month: '2026-01' }
  const preview = (await apiWrite(request, 'POST', invoicePath + '/preview', bill)).data
  const invoice = (await apiWrite(request, 'POST', invoicePath, { ...bill, preview_token: preview.preview_token })).data
  await apiWrite(request, 'POST', '/owner/invoices/' + invoice.id + '/payments', {
    idempotency_key: crypto.randomUUID(), amount: '1000', paid_at: invoice.issued_at, payment_method: 'cash',
  })
  const ownerInvoicePath = '/api/owner/invoices/' + invoice.id
  const before = (await (await request.get(ownerInvoicePath)).json()).data
  expect(before.payment_status).toBe('partially_paid')
  await page.setViewportSize({ width: 390, height: 844 })
  await login(page, c.tenant.username)
  const tenantRequests = page.getByRole('region', { name: 'Yêu cầu trả phòng' })
  const send = async () => {
    await tenantRequests.getByRole('button', { name: 'Gửi yêu cầu trả phòng', exact: true }).click()
    await page.getByRole('dialog').getByRole('button', { name: 'Gửi yêu cầu', exact: true }).click()
    await expect(tenantRequests.getByRole('button', { name: 'Rút yêu cầu trả phòng' })).toBeVisible()
  }
  await send()
  await expect(page.getByRole('heading', { name: 'Phòng ' + c.room.room_number, exact: true })).toBeVisible()
  const currentStay = await page.request.get('/api/tenant/current-stay')
  expect(currentStay.ok()).toBe(true)
  expect((await currentStay.json()).data.room.room_number).toBe(c.room.room_number)
  await tenantRequests.getByRole('button', { name: 'Rút yêu cầu trả phòng' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Xác nhận rút' }).click()
  await expect(tenantRequests.getByText('Đã rút yêu cầu', { exact: true })).toBeVisible()
  await send()

  const ownerContext = await browser.newContext({ viewport: { width: 390, height: 844 } })
  try {
    const ownerPage = await ownerContext.newPage()
    await login(ownerPage, 'e2e_owner')
    await ownerPage.goto('/owner/work')
    const ownerRequests = ownerPage.getByRole('region', { name: 'Yêu cầu trả phòng' })
    const pending = ownerRequests.getByRole('listitem').filter({ hasText: c.room.room_number })
    await pending.getByRole('button', { name: 'Từ chối', exact: true }).click()
    const reject = ownerPage.getByRole('dialog', { name: 'Từ chối yêu cầu trả phòng' })
    await reject.getByLabel('Lý do từ chối').fill('Chưa bàn giao chìa khóa')
    await reject.getByRole('button', { name: 'Gửi lý do từ chối' }).click()
    await expect(pending).toHaveCount(0)

    await page.reload()
    await expect(tenantRequests.getByText(/Chưa bàn giao chìa khóa/)).toBeVisible()
    await send()

    await ownerPage.reload()
    await pending.getByRole('button', { name: 'Xác nhận trả phòng' }).click()
    const confirm = ownerPage.getByRole('dialog', { name: 'Xác nhận trả phòng' })
    await expect(confirm.getByLabel('Thời điểm trả phòng thực tế (giờ Việt Nam)')).not.toHaveValue('')
    await noOverflow(ownerPage)
    await confirm.getByRole('button', { name: 'Xác nhận', exact: true }).click()
    await expect(ownerRequests.getByText('Đã xác nhận trả phòng.', { exact: true })).toBeVisible()
    await ownerRequests.getByRole('button', { name: 'Lịch sử', exact: true }).click()
    await expect(ownerRequests.getByText('Đã xác nhận trả phòng', { exact: true })).toBeVisible()

    await page.reload()
    await expect(page.getByRole('heading', { name: 'Bạn chưa có phòng đang cư trú' })).toBeVisible()
    await expect(tenantRequests.getByText('Đã xác nhận trả phòng', { exact: true })).toBeVisible()
    await page.getByRole('link', { name: 'Xem lịch sử cư trú' }).click()
    await expect(page.getByRole('heading', { name: `Phòng ${c.room.room_number}`, exact: true })).toBeVisible()

    const ownerResponse = await request.get(ownerInvoicePath)
    expect(ownerResponse.ok()).toBe(true)
    expect((await ownerResponse.json()).data).toMatchObject({
      id: invoice.id, paid_amount: before.paid_amount, outstanding_amount: before.outstanding_amount,
      payment_status: 'partially_paid', payments: [{ id: before.payments[0].id }],
    })
    const tenantResponse = await page.request.get('/api/tenant/invoices/' + invoice.id)
    expect(tenantResponse.ok()).toBe(true)
    expect((await tenantResponse.json()).data).toMatchObject({
      id: invoice.id, outstanding_amount: before.outstanding_amount, payment_status: 'partially_paid',
    })
  } finally {
    await ownerContext.close()
  }
})

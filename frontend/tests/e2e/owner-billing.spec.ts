import { expect, test, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'

test.describe.configure({ timeout: 120_000 })
test.beforeEach(({ page }) => { page.setDefaultTimeout(15_000) })
const password = 'E2e-Password-2077!', shots = resolve(process.cwd(), '.cache/e2e/screenshots')
mkdirSync(shots, { recursive: true })
async function login(page: Page, username = 'e2e_owner') { await page.goto('/login'); await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username); await page.getByLabel('Mật khẩu', { exact: true }).fill(password); await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click(); await expect(page).toHaveURL(username === 'e2e_owner' ? '/owner' : '/tenant') }
async function mutation(page: Page, method: string, path: string, data?: unknown) { await page.request.get('/api/auth/csrf-cookie'); const token = (await page.context().cookies()).find(item => item.name === 'XSRF-TOKEN')!; const result = await page.request.fetch(`/api/owner${path}`, { method, data, headers: { 'X-XSRF-TOKEN': decodeURIComponent(token.value) } }); expect(result.ok(), await result.text()).toBe(true); return result.status() === 204 ? null : (await result.json()).data }
async function setup(page: Page, prefix: string, metered = false, account = true, representative = true) { const tenant = await mutation(page, 'POST', '/tenants', { full_name: `${prefix} Người thuê`, phone_number: '0900123456' }); if (account) await mutation(page, 'PUT', `/tenants/${tenant.id}/account`, { username: prefix.toLowerCase().replace(/-/g, '_'), password, password_confirmation: password, is_active: true }); const room = await mutation(page, 'POST', '/rooms', { room_number: prefix, capacity: 2 }); const group = await mutation(page, 'POST', '/rental-groups', { group_code: `NHOM-${prefix}` }); let occupancy = await mutation(page, 'POST', '/occupancies', { room_id: room.id, rental_group_id: group.id, tenant_ids: [tenant.id], started_at: '2026-01-01 00:00:00', ...(representative ? { representative_tenant_id: tenant.id } : {}) }); if (metered) { const service = await mutation(page, 'POST', '/services', { service_code: `${prefix}-DIEN`, name: `Điện ${prefix}`, unit: 'kWh', billing_method: 'metered', default_unit_price: '3500.0000', is_active: true }); occupancy = await mutation(page, 'POST', `/occupancies/${occupancy.id}/services`, { service_id: service.id, effective_from: '2026-01-01 00:00:00' }) } return { tenant, room, group, occupancy } }
async function createInvoice(page: Page, prefix: string) { const context = await setup(page, prefix); const preview = await mutation(page, 'POST', `/occupancies/${context.occupancy.id}/invoices/preview`, { billing_month: '2026-01' }); expect(preview.can_issue).toBe(true); const invoice = await mutation(page, 'POST', `/occupancies/${context.occupancy.id}/invoices`, { billing_month: '2026-01', preview_token: preview.preview_token }); return { ...context, invoice } }
async function saveMeter(page: Page, endReading = '140') { await page.getByRole('button', { name: 'Ghi chỉ số mới', exact: true }).click(); const dialog = page.getByRole('dialog'); await dialog.getByLabel('Đầu kỳ (giờ Việt Nam) (bắt buộc)').fill('2026-01-01T00:00'); await dialog.getByLabel('Cuối kỳ (giờ Việt Nam) (bắt buộc)').fill('2026-02-01T00:00'); await dialog.getByLabel('Chỉ số đầu kỳ (bắt buộc)').fill('100'); await dialog.getByLabel('Chỉ số cuối kỳ (bắt buộc)').fill(endReading); await dialog.getByRole('button', { name: 'Xem lại chỉ số', exact: true }).click(); await dialog.getByRole('button', { name: 'Xác nhận lưu chỉ số', exact: true }).click(); await expect(dialog).toHaveCount(0) }
async function issueThroughUI(page: Page) { await page.getByRole('button', { name: 'Kiểm tra và phát hành', exact: true }).click(); await page.getByRole('checkbox', { name: 'Tôi đã kiểm tra thời gian, chỉ số và các khoản tính tiền.' }).check(); await page.getByRole('button', { name: 'Xác nhận phát hành hóa đơn', exact: true }).click() }
async function noOverflow(page: Page) { expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true) }

test('CT4 records edits deletes meters, blocks missing readings and stale preview, then issues and locks month', async ({ page }) => {
  await login(page); const context = await setup(page, 'CT4-E201', true)
  await page.goto(`/owner/invoices/new?occupancy_id=${context.occupancy.id}`)
  await page.getByLabel('Tháng tính tiền (bắt buộc)').fill('2026-01'); await page.getByRole('button', { name: 'Xem trước hóa đơn', exact: true }).click()
  await expect(page.getByText('Cần xử lý trước khi phát hành', { exact: true })).toBeVisible(); await expect(page.getByRole('button', { name: 'Kiểm tra và phát hành', exact: true })).toHaveCount(0)
  await page.goto(`/owner/meters?occupancy_id=${context.occupancy.id}`); await expect(page.getByRole('button', { name: 'Ghi chỉ số mới', exact: true })).toBeEnabled(); await saveMeter(page)
  await expect(page.getByText('40,000 kWh', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Sửa chỉ số Điện CT4-E201 tháng 2026-01', exact: true }).click(); const edit = page.getByRole('dialog'); await edit.getByLabel('Chỉ số cuối kỳ (bắt buộc)').fill('150'); await edit.getByRole('button', { name: 'Xem lại chỉ số', exact: true }).click(); await edit.getByRole('button', { name: 'Xác nhận lưu chỉ số', exact: true }).click(); await expect(edit).toHaveCount(0); await expect(page.getByText('50,000 kWh', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Xóa chỉ số Điện CT4-E201 tháng 2026-01', exact: true }).click(); await page.getByRole('button', { name: 'Xác nhận xóa', exact: true }).click(); await expect(page.getByRole('dialog')).toHaveCount(0); await saveMeter(page)
  await page.screenshot({ path: resolve(shots, 'ct4-meters.png'), fullPage: true })
  await page.goto(`/owner/invoices/new?occupancy_id=${context.occupancy.id}`); await page.getByLabel('Tháng tính tiền (bắt buộc)').fill('2026-01'); await page.getByRole('button', { name: 'Xem trước hóa đơn', exact: true }).click(); await expect(page.getByRole('button', { name: 'Kiểm tra và phát hành', exact: true })).toBeVisible()
  const readings = (await (await page.request.get(`/api/owner/occupancies/${context.occupancy.id}/meter-readings`)).json()).data
  await mutation(page, 'PUT', `/occupancies/${context.occupancy.id}/meter-readings/${readings[0].id}`, { occupancy_service_id: readings[0].occupancy_service_id, period_start: readings[0].period_start, period_end: readings[0].period_end, previous_reading: readings[0].previous_reading, current_reading: '155.000' })
  await issueThroughUI(page); await expect(page.getByText(/Dữ liệu tính tiền đã thay đổi hoặc tháng/)).toBeVisible(); await expect(page.getByRole('button', { name: 'Xác nhận phát hành hóa đơn', exact: true })).toHaveCount(0)
  await page.getByRole('button', { name: 'Xem trước hóa đơn', exact: true }).click(); await issueThroughUI(page); await expect(page).toHaveURL(/\/owner\/invoices\/\d+\?issued=1$/); await expect(page.getByRole('heading', { name: 'Hóa đơn phòng CT4-E201', exact: true })).toBeVisible(); await page.screenshot({ path: resolve(shots, 'ct4-invoice.png'), fullPage: true })
  await page.goto(`/owner/meters?occupancy_id=${context.occupancy.id}`); await expect(page.getByText('Tháng đã phát hành hóa đơn', { exact: true })).toBeVisible(); await expect(page.getByRole('button', { name: 'Sửa kỳ chỉ số', exact: true })).toHaveCount(0)
  await page.setViewportSize({ width: 390, height: 844 }); await noOverflow(page); await page.screenshot({ path: resolve(shots, 'ct4-meters-mobile.png'), fullPage: true })
})

test('CT4 closed occupancy can explicitly supplement historical billing representative without creating account', async ({ page }) => {
  await login(page); const { occupancy, tenant } = await setup(page, 'CT4-HISTORY', false, false, false)
  await mutation(page, 'POST', `/occupancies/${occupancy.id}/checkout`, { ended_at: '2026-01-20 12:00:00' })
  await page.goto(`/owner/invoices/new?occupancy_id=${occupancy.id}`); await page.getByLabel('Tháng tính tiền (bắt buộc)').fill('2026-01'); await expect(page.getByRole('heading', { name: 'Bổ sung đại diện lịch sử', exact: true })).toBeVisible(); await page.getByLabel('Người đại diện để lập hóa đơn').selectOption({ label: tenant.full_name }); await page.getByRole('button', { name: 'Xác nhận bổ sung đại diện', exact: true }).click(); await expect(page.getByRole('heading', { name: 'Bổ sung đại diện lịch sử', exact: true })).toHaveCount(0); await page.getByRole('button', { name: 'Xem trước hóa đơn', exact: true }).click(); await expect(page.getByText('Đại diện lịch sử chưa có tài khoản đang hoạt động; hóa đơn được lưu cho chủ trọ và chưa cấp quyền người thuê.', { exact: true })).toBeVisible(); await issueThroughUI(page); await expect(page.getByRole('heading', { name: 'Hóa đơn phòng CT4-HISTORY', exact: true })).toBeVisible(); await expect(page.getByText('Chưa cấp quyền', { exact: true })).toBeVisible()
})

test('CT5 payment lost response survives reload and explicit retry without double collection; overpay rejects; full receipt history works', async ({ page }) => {
  await login(page); const { invoice } = await createInvoice(page, 'CT5-PAYMENT'); await page.goto(`/owner/invoices/${invoice.id}`)
  await page.getByRole('button', { name: 'Ghi nhận thu tiền', exact: true }).click(); let dialog = page.getByRole('dialog'); await dialog.getByLabel('Số tiền thực nhận (bắt buộc)').fill('1000000.25'); await dialog.getByRole('button', { name: 'Xem lại lần thu', exact: true }).click()
  let firstPayload: unknown
  await page.route(`**/api/owner/invoices/${invoice.id}/payments`, async route => { if (route.request().method() !== 'POST') return route.continue(); firstPayload = route.request().postDataJSON(); const response = await route.fetch(); expect(response.status()).toBe(201); await route.abort('failed') }, { times: 1 })
  await dialog.getByRole('button', { name: 'Xác nhận đã nhận tiền', exact: true }).click()
  await expect(dialog.getByRole('button', { name: 'Gửi lại lần thu đang chờ', exact: true })).toBeVisible()
  const pendingKey = `rental:pending-payment:1:${invoice.id}`
  expect(await page.evaluate(key => !!sessionStorage.getItem(key), pendingKey)).toBe(true)
  await page.request.get('/api/auth/csrf-cookie')
  const csrf = (await page.context().cookies()).find(cookie => cookie.name === 'XSRF-TOKEN')!
  const expiredSession = await page.request.post('/api/auth/logout', { headers: { 'X-XSRF-TOKEN': decodeURIComponent(csrf.value) } })
  expect(expiredSession.ok()).toBe(true)
  await dialog.getByRole('button', { name: 'Gửi lại lần thu đang chờ', exact: true }).click()
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
  expect(await page.evaluate(key => !!sessionStorage.getItem(key), pendingKey)).toBe(true)
  await login(page); await page.goto(`/owner/invoices/${invoice.id}`); await page.reload()
  await page.getByRole('button', { name: 'Kiểm tra lần thu đang chờ', exact: true }).click()
  dialog = page.getByRole('dialog'); let replayPayload: unknown
  page.on('request', request => { if (request.method() === 'POST' && request.url().endsWith(`/invoices/${invoice.id}/payments`)) replayPayload = request.postDataJSON() })
  await dialog.getByRole('button', { name: 'Gửi lại lần thu đang chờ', exact: true }).click()
  await expect(dialog).toHaveCount(0); expect(replayPayload).toEqual(firstPayload)
  await expect(page.getByText('Lần thu này đã được ghi nhận trước đó; số dư không cộng thêm.', { exact: true })).toBeVisible()
  let state = (await (await page.request.get(`/api/owner/invoices/${invoice.id}`)).json()).data
  expect(state.payments).toHaveLength(1); expect(state.paid_amount).toBe('1000000.25')
  await page.getByRole('button', { name: 'Ghi nhận thu tiền', exact: true }).click(); dialog = page.getByRole('dialog'); await dialog.getByLabel('Số tiền thực nhận (bắt buộc)').fill('999999999'); await dialog.getByRole('button', { name: 'Xem lại lần thu', exact: true }).click(); await dialog.getByRole('button', { name: 'Xác nhận đã nhận tiền', exact: true }).click(); await expect(dialog.getByRole('alert')).toBeVisible(); await dialog.getByRole('button', { name: 'Điền toàn bộ còn nợ', exact: true }).click(); await dialog.getByLabel('Hình thức nhận tiền').selectOption('bank_transfer'); await dialog.getByRole('button', { name: 'Xem lại lần thu', exact: true }).click(); await dialog.getByRole('button', { name: 'Xác nhận đã nhận tiền', exact: true }).click(); await expect(dialog).toHaveCount(0); await expect(page.getByText('Đã thu đủ', { exact: true })).toBeVisible(); state = (await (await page.request.get(`/api/owner/invoices/${invoice.id}`)).json()).data; expect(state.payments).toHaveLength(2); expect(state.outstanding_amount).toBe('0.00'); await page.getByRole('button', { name: `Xem lần thu PT-${state.payments[0].id}`, exact: true }).click(); await expect(page.getByRole('dialog').getByText('Chi tiết lần thu', { exact: true })).toBeVisible(); await page.getByRole('dialog').getByRole('button', { name: 'Đóng hộp thoại', exact: true }).click(); await page.screenshot({ path: resolve(shots, 'ct5-paid-invoice.png'), fullPage: true }); await page.goto('/owner/payments'); await expect(page.getByRole('heading', { name: 'Lịch sử thu tiền hóa đơn', exact: true })).toBeVisible(); await expect(page.getByText(invoice.invoice_number, { exact: true }).first()).toBeVisible(); await page.setViewportSize({ width: 390, height: 844 }); await noOverflow(page)
})

test('CT5 owner reviews grant history, revokes and regrants invoice access and sends deduplicated reminders', async ({ page }) => {
  await login(page); const { invoice, tenant } = await createInvoice(page, 'CT5-ACCESS'); await page.goto(`/owner/invoices/${invoice.id}`)
  await page.getByRole('button', { name: 'Nhắc thanh toán', exact: true }).click(); await page.getByRole('button', { name: 'Gửi nhắc thanh toán', exact: true }).click(); await expect(page.getByRole('dialog')).toHaveCount(0); await expect(page.getByText('Đã tạo thông báo nhắc thanh toán.', { exact: true })).toBeVisible(); await page.getByRole('button', { name: 'Nhắc thanh toán', exact: true }).click(); await page.getByRole('button', { name: 'Gửi nhắc thanh toán', exact: true }).click(); await expect(page.getByText('Hóa đơn đã được nhắc cho người này hôm nay.', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Thu hồi quyền hiện tại', exact: true }).click(); await page.getByRole('button', { name: 'Xác nhận thu hồi', exact: true }).click(); await expect(page.getByText('Chưa cấp quyền', { exact: true })).toBeVisible(); await page.getByRole('button', { name: 'Cấp / thay người được xem', exact: true }).click(); const dialog = page.getByRole('dialog'); await dialog.getByRole('combobox', { name: 'Người đại diện', exact: true }).selectOption({ index: 1 }); await dialog.getByLabel('Lý do cấp / thay quyền (bắt buộc)').fill('Bàn giao lại hóa đơn sau khi đối chiếu.'); await dialog.getByRole('button', { name: 'Xác nhận cấp quyền', exact: true }).click(); await expect(dialog).toHaveCount(0); await expect(page.getByText('Đang cấp quyền', { exact: true })).toBeVisible(); await expect(page.getByText('Bàn giao lại hóa đơn sau khi đối chiếu.', { exact: true })).toBeVisible(); const fresh = (await (await page.request.get(`/api/owner/invoices/${invoice.id}`)).json()).data; expect(fresh.access_grants).toHaveLength(2); expect(fresh.access_grants.filter((grant: { is_active: boolean }) => grant.is_active)).toHaveLength(1); await expect(page.getByText(tenant.full_name, { exact: true }).first()).toBeVisible()
  await page.setViewportSize({ width: 390, height: 844 }); await noOverflow(page); await page.screenshot({ path: resolve(shots, 'ct5-invoice-mobile.png'), fullPage: true })
})

test('CT4 invoice links preserve dashboard and debt room group month filters and stay scope on reload', async ({ page }) => {
  await login(page); const { invoice, room, group, occupancy } = await createInvoice(page, 'CT4-FILTER')
  await page.goto(`/owner/invoices?billing_month=2026-01&room_id=${room.id}&payment_status=unpaid`)
  await expect(page.getByLabel('Tháng hóa đơn')).toHaveValue('2026-01')
  await expect(page.getByRole('article')).toHaveCount(1)
  await expect(page.getByRole('article')).toContainText(invoice.invoice_number)
  await page.goto(`/owner/invoices?billing_month=2026-01&rental_group_id=${group.id}`)
  await expect(page.getByRole('article')).toHaveCount(1)
  await expect(page.getByRole('article')).toContainText(invoice.invoice_number)
  await page.goto(`/owner/invoices?occupancy_id=${occupancy.id}`); await page.reload()
  await expect(page.getByRole('button', { name: 'Bỏ lọc đợt thuê', exact: true })).toBeVisible()
  await expect(page.getByRole('article')).toHaveCount(1)
  await page.getByRole('button', { name: 'Bỏ lọc đợt thuê', exact: true }).click()
  await expect(page).not.toHaveURL(/occupancy_id/)
  await expect.poll(() => page.getByRole('article').count()).toBeGreaterThan(1)
})

import { expect, test, type Page } from '@playwright/test'
import { resolve } from 'node:path'
import { mkdirSync } from 'node:fs'
const password = 'E2e-Password-2077!'
const shots = resolve(process.cwd(), '.cache/e2e/screenshots')
mkdirSync(shots, { recursive: true })
test.describe.configure({ timeout: 120_000 })
test.beforeEach(({ page }) => page.setDefaultTimeout(15_000))
async function login(page: Page, username = 'e2e_owner') {
  await page.goto('/login'); await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username); await page.getByLabel('Mật khẩu', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click(); await expect(page).toHaveURL(username === 'e2e_owner' ? '/owner' : '/tenant')
}
async function write(page: Page, path: string, data: unknown, method = 'POST') {
  await page.request.get('/api/auth/csrf-cookie'); const token = (await page.context().cookies()).find(c => c.name === 'XSRF-TOKEN')!
  const response = await page.request.fetch(`/api/owner${path}`, { method, data, headers: { 'X-XSRF-TOKEN': decodeURIComponent(token.value) } })
  expect(response.ok(), await response.text()).toBe(true); return (await response.json()).data
}
async function fixture(page: Page, name: string) {
  const tenant = await write(page, '/tenants', { full_name: name, phone_number: '0900123456' })
  const username = name.toLowerCase().replaceAll('-', '_')
  await write(page, `/tenants/${tenant.id}/account`, { username, password, password_confirmation: password, is_active: true }, 'PUT')
  const room = await write(page, '/rooms', { room_number: name, capacity: 2 })
  const group = await write(page, '/rental-groups', { group_code: name })
  const stay = await write(page, '/occupancies', { room_id: room.id, rental_group_id: group.id, tenant_ids: [tenant.id], representative_tenant_id: tenant.id, started_at: '2026-01-01 00:00:00' })
  const preview = await write(page, `/occupancies/${stay.id}/invoices/preview`, { billing_month: '2026-01' })
  const invoice = await write(page, `/occupancies/${stay.id}/invoices`, { billing_month: '2026-01', preview_token: preview.preview_token })
  return { invoice, stay, username }
}
async function review(page: Page) {
  const dialog = page.getByRole('dialog'); await dialog.getByRole('button', { name: 'Xem lại thay đổi', exact: true }).click()
  await dialog.getByRole('checkbox', { name: 'Tôi đã kiểm tra và đồng ý lưu thay đổi này.' }).check()
  return dialog
}
async function confirm(page: Page) { const dialog = await review(page); await dialog.getByRole('button', { name: 'Xác nhận thay đổi', exact: true }).click(); await expect(dialog).toHaveCount(0) }

test('invoice adjustment lost response survives reload, replays once, and tenant sees readonly audit', async ({ page }) => {
  await login(page); const { invoice, username } = await fixture(page, 'CONTROL-RETRY')
  await page.goto(`/owner/invoices/${invoice.id}`); await page.getByRole('button', { name: 'Điều chỉnh số tiền', exact: true }).click()
  await page.getByLabel('Khoản điều chỉnh tăng / giảm (bắt buộc)').fill('-50000.25'); await page.getByLabel('Lý do thay đổi (bắt buộc)').fill('Giảm phí sau đối chiếu điện nước')
  const dialog = await review(page); let sent: unknown
  await page.route(`**/api/owner/invoices/${invoice.id}/changes`, async route => { sent = route.request().postDataJSON(); const response = await route.fetch(); expect(response.ok()).toBe(true); await route.abort('failed') }, { times: 1 })
  await dialog.getByRole('button', { name: 'Xác nhận thay đổi', exact: true }).click(); await expect(dialog.getByRole('button', { name: 'Gửi lại thay đổi đang chờ', exact: true })).toBeVisible()
  await page.context().clearCookies(); await page.reload(); await expect(page).toHaveURL(/\/login(?:\?|$)/)
  await login(page); await page.goto(`/owner/invoices/${invoice.id}`); await page.getByRole('button', { name: 'Kiểm tra thay đổi đang chờ', exact: true }).click()
  let retried: unknown
  page.on('request', r => { if (r.method() === 'POST' && r.url().endsWith(`/invoices/${invoice.id}/changes`)) retried = r.postDataJSON() })
  await page.getByRole('button', { name: 'Gửi lại thay đổi đang chờ', exact: true }).click(); await expect(page.getByRole('dialog')).toHaveCount(0); expect(retried).toEqual(sent)
  const data = (await (await page.request.get(`/api/owner/invoices/${invoice.id}`)).json()).data
  expect(data.changes).toHaveLength(1); expect(Number(data.original_amount) - Number(data.total_amount)).toBe(50000.25)
  await page.getByRole('button', { name: 'Đặt / đổi hạn thanh toán', exact: true }).click(); await page.getByLabel('Hạn thanh toán mới (bắt buộc)').fill('2030-12-31'); await page.getByLabel('Lý do thay đổi (bắt buộc)').fill('Thống nhất hạn đóng với người thuê'); await confirm(page)
  await page.screenshot({ path: resolve(shots, 'invoice-control-owner.png'), fullPage: true })
  await page.context().clearCookies(); await login(page, username); await page.goto(`/tenant/invoices/${invoice.id}`)
  await expect(page.getByText('Giảm phí sau đối chiếu điện nước', { exact: true })).toBeVisible(); await expect(page.getByText('31/12/2030', { exact: false }).first()).toBeVisible()
  await expect(page.getByRole('button', { name: 'Điều chỉnh số tiền', exact: true })).toHaveCount(0)
  await page.setViewportSize({ width: 390, height: 844 }); await page.reload()
  await expect(page.getByRole('heading', { name: 'Hóa đơn phòng CONTROL-RETRY', exact: true })).toBeVisible()
  await page.evaluate(() => document.fonts.ready)
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true)
  await page.screenshot({ path: resolve(shots, 'invoice-control-tenant-mobile.png'), fullPage: false, animations: 'disabled' })
  const geometry = await page.locator('.main-shell, .main-content, .management-page, .management-header, .record-card, .management-page h1, .management-page dd, .management-page .ui-button').evaluateAll(elements => elements.map(element => ({ className: element.className, left: element.getBoundingClientRect().left, right: element.getBoundingClientRect().right, width: element.getBoundingClientRect().width })))
  expect(geometry.every(element => element.left >= 0 && element.right <= 391), JSON.stringify(geometry)).toBe(true)
})

test('owner can cancel and restore unpaid invoice, while a receipt blocks cancellation and reduction below paid amount', async ({ page }) => {
  await login(page); const { invoice } = await fixture(page, 'CONTROL-CANCEL')
  await page.goto(`/owner/invoices/${invoice.id}`); await page.getByRole('button', { name: 'Hủy hóa đơn', exact: true }).click(); await page.getByLabel('Lý do thay đổi (bắt buộc)').fill('Ngừng thu kỳ này sau đối chiếu'); await confirm(page)
  await expect(page.getByRole('button', { name: 'Ghi nhận thu tiền', exact: true })).toHaveCount(0)
  await page.getByRole('button', { name: 'Khôi phục hóa đơn', exact: true }).click(); await page.getByLabel('Lý do thay đổi (bắt buộc)').fill('Khôi phục theo thỏa thuận mới'); await confirm(page)
  await write(page, `/invoices/${invoice.id}/payments`, { amount: '1000000', paid_at: invoice.issued_at, payment_method: 'cash', idempotency_key: crypto.randomUUID() })
  await page.reload(); await expect(page.getByRole('button', { name: 'Hủy hóa đơn', exact: true })).toHaveCount(0)
  await page.getByRole('button', { name: 'Điều chỉnh số tiền', exact: true }).click(); await page.getByLabel('Khoản điều chỉnh tăng / giảm (bắt buộc)').fill('-99999999'); await page.getByLabel('Lý do thay đổi (bắt buộc)').fill('Thử giảm vượt số đã thu')
  const dialog = await review(page); await dialog.getByRole('button', { name: 'Xác nhận thay đổi', exact: true }).click(); await expect(dialog.getByRole('alert')).toContainText('thấp hơn số tiền đã thu')
  const data = (await (await page.request.get(`/api/owner/invoices/${invoice.id}`)).json()).data; expect(data.changes).toHaveLength(2); expect(data.paid_amount).toBe('1000000.00')
})

test('overdue filter and grouped summary agree, tenant sees days late, and owner can extend deadline', async ({ page }) => {
  await login(page); await page.goto('/owner/receivables?due_status=overdue')
  await expect(page.getByLabel('Hạn thanh toán')).toHaveValue('overdue'); await expect(page.getByText('Quá hạn 2 ngày', { exact: false }).first()).toBeVisible()
  await page.getByLabel('Cách xem').selectOption('room'); await page.getByRole('button', { name: 'Áp dụng bộ lọc', exact: true }).click(); await expect(page.getByText('Phòng A101', { exact: true })).toBeVisible()
  await page.screenshot({ path: resolve(shots, 'invoice-overdue-receivables.png'), fullPage: true })
  await page.context().clearCookies(); await login(page, 'e2e_notify_tenant'); await page.goto('/tenant/invoices/1'); await expect(page.getByText('Hóa đơn đã quá hạn 2 ngày.', { exact: false })).toBeVisible()
  await page.goto('/tenant/invoices?due_status=overdue'); await expect(page.getByLabel('Hạn thanh toán')).toHaveValue('overdue'); await expect(page.getByRole('article')).toHaveCount(1)
})

test('issuing a new invoice includes the chosen due date in review and persists it', async ({ page }) => {
  await login(page); const { stay } = await fixture(page, 'CONTROL-ISSUE')
  await page.goto(`/owner/invoices/new?occupancy_id=${stay.id}`); await page.getByLabel('Tháng tính tiền (bắt buộc)').fill('2026-02'); await page.getByLabel('Hạn thanh toán (không bắt buộc)').fill('2030-12-20')
  await page.getByRole('button', { name: 'Xem trước hóa đơn', exact: true }).click(); await page.getByRole('button', { name: 'Kiểm tra và phát hành', exact: true }).click()
  await expect(page.getByText('20/12/2030', { exact: true })).toBeVisible(); await page.getByRole('checkbox', { name: 'Tôi đã kiểm tra thời gian, chỉ số và các khoản tính tiền.' }).check()
  await page.getByRole('button', { name: 'Xác nhận phát hành hóa đơn', exact: true }).click(); await expect(page).toHaveURL(/\/owner\/invoices\/\d+\?issued=1$/)
  await expect(page.getByText('20/12/2030', { exact: false }).first()).toBeVisible()
})

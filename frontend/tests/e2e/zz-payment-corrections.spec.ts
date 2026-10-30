import { expect, test, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'

const password = 'E2e-Password-2077!', screenshots = resolve(process.cwd(), '.cache/e2e/screenshots')
mkdirSync(screenshots, { recursive: true })
test.describe.configure({ timeout: 120_000 })
test.beforeEach(({ page }) => page.setDefaultTimeout(15_000))

async function login(page: Page, username = 'e2e_owner') {
  await page.goto('/login'); await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username); await page.getByLabel('Mật khẩu', { exact: true }).fill(password)
  const response = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith('/api/auth/login'))
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click(); expect((await response).status()).toBe(200)
  await expect(page).toHaveURL(username === 'e2e_owner' ? '/owner' : '/tenant')
}
async function write(page: Page, path: string, data: unknown, method = 'POST') {
  await page.request.get('/api/auth/csrf-cookie'); const token = (await page.context().cookies()).find(c => c.name === 'XSRF-TOKEN')!
  const response = await page.request.fetch(`/api/owner${path}`, { method, data, headers: { 'X-XSRF-TOKEN': decodeURIComponent(token.value) } })
  expect(response.ok(), await response.text()).toBe(true); return (await response.json()).data
}
async function fixture(page: Page, tag: string) {
  const tenant = await write(page, '/tenants', { full_name: `Người thuê ${tag}`, phone_number: '0900123456' }), username = tag.toLowerCase().replaceAll('-', '_')
  await write(page, `/tenants/${tenant.id}/account`, { username, password, password_confirmation: password, is_active: true }, 'PUT')
  const room = await write(page, '/rooms', { room_number: tag, capacity: 1 }), group = await write(page, '/rental-groups', { group_code: tag })
  const stay = await write(page, '/occupancies', { room_id: room.id, rental_group_id: group.id, tenant_ids: [tenant.id], representative_tenant_id: tenant.id, started_at: '2026-01-01 00:00:00' })
  const preview = await write(page, `/occupancies/${stay.id}/invoices/preview`, { billing_month: '2026-01' })
  const invoice = await write(page, `/occupancies/${stay.id}/invoices`, { billing_month: '2026-01', preview_token: preview.preview_token })
  const payment = await write(page, `/invoices/${invoice.id}/payments`, { amount: '1000000.00', paid_at: invoice.issued_at, payment_method: 'cash', idempotency_key: crypto.randomUUID() })
  return { invoice, payment, username }
}
async function openCorrection(page: Page, invoiceId: number, paymentId: number, pending = false) {
  await page.goto(`/owner/invoices/${invoiceId}`)
  await page.getByRole('button', { name: `Xem lần thu PT-${paymentId}`, exact: true }).click()
  await page.getByRole('dialog', { name: 'Chi tiết lần thu', exact: true }).getByRole('button', { name: pending ? 'Kiểm tra sửa sai đang chờ' : 'Sửa sai phiếu thu', exact: true }).click()
  return page.getByRole('dialog', { name: 'Sửa sai phiếu thu', exact: true })
}
async function review(page: Page) {
  const dialog = page.getByRole('dialog', { name: 'Sửa sai phiếu thu', exact: true })
  await dialog.getByRole('button', { name: 'Xem lại sửa sai', exact: true }).click()
  await dialog.getByRole('checkbox', { name: 'Tôi đã đối chiếu thực nhận và kiểm tra nội dung sửa sai.' }).check()
  return dialog
}
async function state(page: Page, id: number) { return (await (await page.request.get(`/api/owner/invoices/${id}`)).json()).data }

test('atomic receipt replacement survives committed response loss, reload and reauthentication without a second correction', async ({ page, browser, baseURL }) => {
  await login(page); const { invoice, payment, username } = await fixture(page, 'RECEIPT-RETRY')
  let dialog = await openCorrection(page, invoice.id, payment.id)
  await dialog.getByLabel('Số tiền thực nhận đúng (bắt buộc)', { exact: true }).fill('800000.25')
  await dialog.getByRole('combobox', { name: 'Hình thức thực nhận đúng', exact: true }).selectOption('bank_transfer')
  await dialog.getByLabel('Lý do sửa sai (bắt buộc)', { exact: true }).fill('Đối chiếu tiền thực nhận, nhập dư phiếu cũ')
  dialog = await review(page)
  const sent: unknown[] = []
  await page.route(`**/api/owner/payments/${payment.id}/corrections`, async route => {
    sent.push(route.request().postDataJSON()); const response = await route.fetch(); expect(response.status()).toBe(201); await route.abort('failed')
  }, { times: 1 })
  await dialog.getByRole('button', { name: 'Xác nhận sửa sai phiếu thu', exact: true }).click()
  await expect(dialog.getByRole('button', { name: 'Gửi lại yêu cầu sửa sai đang chờ', exact: true })).toBeVisible()
  await page.context().clearCookies(); await page.reload(); await expect(page).toHaveURL(/\/login(?:\?|$)/)
  await login(page); dialog = await openCorrection(page, invoice.id, payment.id, true)
  page.on('request', r => { if (r.method() === 'POST' && r.url().endsWith(`/payments/${payment.id}/corrections`)) sent.push(r.postDataJSON()) })
  const replay = page.waitForResponse(r => r.request().method() === 'POST' && r.url().endsWith(`/payments/${payment.id}/corrections`))
  await dialog.getByRole('button', { name: 'Gửi lại yêu cầu sửa sai đang chờ', exact: true }).click()
  expect((await replay).status()).toBe(200)
  await expect(page.getByRole('dialog', { name: 'Chi tiết lần thu', exact: true })).toBeVisible()
  expect(sent).toHaveLength(2); expect(sent[1]).toEqual(sent[0])
  const data = await state(page, invoice.id)
  expect(data.paid_amount).toBe('800000.25'); expect(data.payments).toHaveLength(2)
  expect(data.payments.filter((p: { status: string }) => p.status === 'voided')).toHaveLength(1)
  const active = data.payments.find((p: { status: string }) => p.status === 'active')
  expect(active.replaces_payment_id).toBe(payment.id)
  const ledger = (await (await page.request.get(`/api/owner/cash-flow/summary?q=${encodeURIComponent(invoice.invoice_number)}`)).json()).data
  expect(ledger.invoice_payment_total).toBe('800000.25'); expect(ledger.invoice_payment_count).toBe(1)
  await page.getByRole('dialog').getByRole('button', { name: 'Đóng hộp thoại', exact: true }).click()
  await page.evaluate(() => (document.activeElement as HTMLElement)?.blur())
  await page.locator('.detail-sections').screenshot({ path: resolve(screenshots, 'payment-correction-owner.png'), animations: 'disabled' })
  const context = await browser.newContext({ baseURL }), tenantPage = await context.newPage()
  try {
    await login(tenantPage, username); await tenantPage.goto(`/tenant/invoices/${invoice.id}`)
    await expect(tenantPage.getByText('Đã hủy ghi nhận', { exact: true })).toBeVisible()
    await expect(tenantPage.getByText(`Phiếu thay thế cho PT-${payment.id}.`, { exact: true })).toBeVisible()
    await expect(tenantPage.getByRole('button', { name: 'Sửa sai phiếu thu', exact: true })).toHaveCount(0)
    const tenantData = (await (await tenantPage.request.get(`/api/tenant/invoices/${invoice.id}`)).json()).data
    expect(tenantData.payments[0].correction.performed_by).toBeUndefined(); expect(tenantData.payments[0].version_token).toBeUndefined()
    await tenantPage.setViewportSize({ width: 320, height: 740 }); await tenantPage.reload()
    await expect(tenantPage.getByText('Đã hủy ghi nhận', { exact: true })).toBeVisible()
    expect(await tenantPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true)
    await tenantPage.evaluate(() => (document.activeElement as HTMLElement)?.blur())
    await tenantPage.screenshot({ path: resolve(screenshots, 'payment-correction-tenant-mobile.png'), animations: 'disabled' })
  } finally { await context.close() }
})

test('voiding a mistaken receipt reopens debt, preserves history and enables invoice cancellation without recording a refund', async ({ page }) => {
  await login(page); const { invoice, payment } = await fixture(page, 'RECEIPT-VOID')
  let dialog = await openCorrection(page, invoice.id, payment.id)
  await dialog.getByRole('combobox', { name: 'Cách sửa sai', exact: true }).selectOption('void')
  await dialog.getByLabel('Lý do sửa sai (bắt buộc)', { exact: true }).fill('Chưa nhận tiền, chỉ ghi nhận nhầm trong sổ')
  dialog = await review(page); await dialog.getByRole('button', { name: 'Xác nhận sửa sai phiếu thu', exact: true }).click()
  await expect(page.getByRole('dialog', { name: 'Chi tiết lần thu', exact: true }).getByText('Đã hủy ghi nhận', { exact: true })).toBeVisible()
  await page.getByRole('dialog').getByRole('button', { name: 'Đóng hộp thoại', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Hủy hóa đơn', exact: true })).toBeVisible()
  const data = await state(page, invoice.id)
  expect(data.paid_amount).toBe('0.00'); expect(data.outstanding_amount).toBe(invoice.total_amount); expect(data.payments).toHaveLength(1)
  expect(data.payments[0].correction.replacement_payment_id).toBeNull()
  const ledger = (await (await page.request.get(`/api/owner/cash-flow/summary?q=${encodeURIComponent(invoice.invoice_number)}`)).json()).data
  expect(ledger.invoice_payment_total).toBe('0.00'); expect(ledger.expense_total).toBe('0.00')
  await page.goto('/owner/payments'); await page.getByRole('combobox', { name: 'Trạng thái phiếu', exact: true }).selectOption('voided')
  await page.getByRole('button', { name: 'Lọc lần thu', exact: true }).click()
  await expect(page.getByRole('button', { name: `Chi tiết PT-${payment.id}`, exact: true })).toBeVisible()
})

test('receipt correction conflicts keep the original unchanged and require fresh reconciliation', async ({ page }) => {
  await login(page); const { invoice, payment } = await fixture(page, 'RECEIPT-CONFLICT')
  let dialog = await openCorrection(page, invoice.id, payment.id)
  await dialog.getByLabel('Số tiền thực nhận đúng (bắt buộc)', { exact: true }).fill('999999999999')
  await dialog.getByLabel('Lý do sửa sai (bắt buộc)', { exact: true }).fill('Thử nhập quá tổng hóa đơn')
  dialog = await review(page); await dialog.getByRole('button', { name: 'Xác nhận sửa sai phiếu thu', exact: true }).click()
  await expect(dialog.getByRole('alert')).toContainText('vượt số còn nợ')
  await expect(dialog.getByRole('button', { name: 'Tải lại phiếu để đối chiếu', exact: true })).toBeVisible()
  const data = await state(page, invoice.id)
  expect(data.paid_amount).toBe('1000000.00'); expect(data.payments).toHaveLength(1); expect(data.payments[0].status).toBe('active')
})

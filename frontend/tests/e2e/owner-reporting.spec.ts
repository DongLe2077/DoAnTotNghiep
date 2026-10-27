import { expect, test, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'
test.describe.configure({ timeout: 70_000 })
test.beforeEach(({ page }) => { page.setDefaultTimeout(15_000) })
const screenshots = resolve(process.cwd(), '.cache/e2e/screenshots')
mkdirSync(screenshots, { recursive: true })
async function login(page: Page, username = 'e2e_owner') {
  await page.goto('/login')
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username)
  await page.getByLabel('Mật khẩu', { exact: true }).fill('E2e-Password-2077!')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await expect(page).toHaveURL(username === 'e2e_tenant' ? '/tenant' : '/owner')
}
function money(value: string) {
  const sign = value.startsWith('-') ? '-' : ''
  const [whole = '0', fraction = ''] = value.replace('-', '').split('.')
  const tail = fraction.replace(/0+$/, '')
  return `${sign}${BigInt(whole).toLocaleString('vi-VN')}${tail ? ',' + tail : ''}`
}
async function noOverflow(page: Page) { expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true) }

test('CT8 dashboard distinguishes current occupancy, selected invoice period and actual cash period', async ({ page }) => {
  await login(page)
  await expect(page.getByRole('heading', { name: 'Tình hình hiện tại', exact: true })).toBeVisible()
  const month = await page.getByLabel('Tháng theo dõi', { exact: true }).inputValue()
  const response = await page.request.get(`/api/owner/dashboard?month=${month}`)
  expect(response.status()).toBe(200)
  const initial = (await response.json()).data
  await expect(page.getByRole('link').filter({ hasText: 'Người đang cư trú' }).locator('strong')).toHaveText(String(initial.tenants.residing_count))
  await expect(page.getByRole('link').filter({ hasText: 'Tổng thực thu' }).locator('strong')).toContainText(money(initial.cash_flow.total_income))
  await page.screenshot({ path: resolve(screenshots, 'ct8-dashboard.png'), fullPage: true, animations: 'disabled' })
  await page.screenshot({ path: resolve(screenshots, 'ct8-dashboard-viewport.png'), animations: 'disabled' })
  await page.getByLabel('Tháng theo dõi', { exact: true }).fill('1900-01')
  await page.getByRole('button', { name: 'Cập nhật', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Thực thu / chi tháng 1900-01', exact: true })).toBeVisible()
  await expect(page.getByRole('link').filter({ hasText: 'Tổng thực thu' }).locator('strong')).toContainText('0')
  await expect(page.getByRole('link').filter({ hasText: 'Người đang cư trú' }).locator('strong')).toHaveText(String(initial.tenants.residing_count))
  const allDebt = page.getByRole('article').filter({ has: page.getByRole('heading', { name: 'Công nợ mọi tháng', exact: true }) })
  await expect(allDebt).toContainText(money(initial.receivables.outstanding_amount))
  await page.setViewportSize({ width: 390, height: 844 }); await noOverflow(page)
  await page.screenshot({ path: resolve(screenshots, 'ct8-dashboard-mobile.png'), animations: 'disabled' })
  await page.getByRole('link', { name: 'Xem chi tiết công nợ →', exact: true }).click()
  await expect(page).toHaveURL('/owner/receivables')
  await expect(page.getByRole('heading', { name: 'Công nợ', exact: true })).toBeVisible()
})

test('CT5 receivables offer invoice/room/group views and preserve the month when linking to invoices', async ({ page }) => {
  await login(page); await page.goto('/owner/receivables')
  await expect(page.getByText('Tổng còn cần thu', { exact: true })).toBeVisible()
  const expected = (await (await page.request.get('/api/owner/receivables/summary')).json()).data
  await expect(page.locator('.stat-card').filter({ hasText: 'Tổng còn cần thu' })).toContainText(money(expected.outstanding_amount))
  await page.getByRole('combobox', { name: 'Cách xem', exact: true }).selectOption('room')
  await page.getByRole('button', { name: 'Áp dụng bộ lọc', exact: true }).click()
  await expect(page.getByText('Tổng tiền các hóa đơn còn nợ', { exact: true }).first()).toBeVisible()
  const roomGroup = (await (await page.request.get('/api/owner/receivables/groups?group_by=room')).json()).data[0]
  await expect(page.getByRole('heading', { name: `Phòng ${roomGroup.group_label}`, exact: true })).toBeVisible()
  await page.getByRole('combobox', { name: 'Cách xem', exact: true }).selectOption('rental_group')
  await page.getByRole('button', { name: 'Áp dụng bộ lọc', exact: true }).click()
  const group = (await (await page.request.get('/api/owner/receivables/groups?group_by=rental_group')).json()).data[0]
  await expect(page.getByRole('heading', { name: `Nhóm thuê ${group.group_label}`, exact: true })).toBeVisible()
  await page.screenshot({ path: resolve(screenshots, 'ct5-receivables.png'), animations: 'disabled' })
  await page.getByRole('link', { name: 'Xem các hóa đơn trong phạm vi →', exact: true }).first().click()
  await expect(page).toHaveURL(/\/owner\/invoices\?rental_group_id=/)
  await page.goto('/owner/receivables')
  await page.getByLabel('Kỳ hóa đơn', { exact: false }).fill('1900-01')
  await page.getByRole('button', { name: 'Áp dụng bộ lọc', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Không có công nợ phù hợp', exact: true })).toBeVisible()
  await page.setViewportSize({ width: 390, height: 844 }); await noOverflow(page)
})

test('CT8 failed refresh clears prior private statistics and explicit retry reloads real data', async ({ page }) => {
  await login(page)
  await expect(page.getByRole('heading', { name: 'Tình hình hiện tại', exact: true })).toBeVisible()
  await page.route('**/api/owner/dashboard?**', route => route.fulfill({ status: 503, contentType: 'application/json', body: '{}' }))
  await page.getByRole('button', { name: 'Cập nhật', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Chưa thể tải thông tin', exact: true })).toBeVisible()
  await expect(page.locator('.stat-card')).toHaveCount(0)
  await expect(page.locator('.dashboard-charts')).toHaveCount(0)
  await page.unroute('**/api/owner/dashboard?**')
  await page.getByRole('button', { name: 'Thử lại', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Tình hình hiện tại', exact: true })).toBeVisible()
})

test('tenant cannot enter any completed owner finance or maintenance screen', async ({ page }) => {
  await login(page, 'e2e_tenant')
  for (const path of ['/owner', '/owner/meters', '/owner/invoices', '/owner/receivables', '/owner/cash', '/owner/maintenance']) {
    await page.goto(path)
    await expect(page).toHaveURL('/forbidden')
    await expect(page.getByRole('heading', { name: 'Bạn không có quyền truy cập', exact: true })).toBeVisible()
  }
})

test('CT8 interactive invoice/cash charts match the API, preserve drill-down months and fit mobile', async ({ page }) => {
  await login(page)
  const month = await page.getByLabel('Tháng theo dõi', { exact: true }).inputValue()
  const serverNow = (await (await page.request.get(`/api/owner/dashboard?month=${month}`)).json()).data.as_of
  async function write(path: string, payload: unknown) {
    await page.request.get('/api/auth/csrf-cookie')
    const token = (await page.context().cookies()).find(item => item.name === 'XSRF-TOKEN')!
    const response = await page.request.post(`/api/owner${path}`, { data: payload, headers: { 'X-XSRF-TOKEN': decodeURIComponent(token.value) } })
    expect(response.ok(), await response.text()).toBe(true)
  }
  await write('/cash-transactions', { idempotency_key: crypto.randomUUID(), transaction_type: 'other_income', amount: '1250000.50', occurred_at: `${month}-01 09:00:00`, description: 'CT8 thu khác mẫu biểu đồ' })
  await write('/cash-transactions', { idempotency_key: crypto.randomUUID(), transaction_type: 'expense', amount: '350000.25', occurred_at: `${month}-01 10:00:00`, description: 'CT8 chi phí mẫu biểu đồ' })
  await write('/invoices/1/payments', { idempotency_key: crypto.randomUUID(), amount: '600000.25', paid_at: serverNow, payment_method: 'cash' })
  await page.getByRole('button', { name: 'Cập nhật', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Thống kê trực quan', exact: true })).toBeVisible()
  const data = (await (await page.request.get(`/api/owner/dashboard?month=${month}`)).json()).data
  const donut = page.getByRole('article', { name: 'Trạng thái hóa đơn', exact: true })
  for (const [label, key] of [['Đã thu đủ', 'paid_count'], ['Thu một phần', 'partially_paid_count'], ['Chưa thu', 'unpaid_count'], ['Cần đối chiếu', 'inconsistent_count']]) {
    await expect(donut.getByRole('link', { name: `${label} ${data.invoices[key]}`, exact: true })).toBeVisible()
  }
  const chart = page.locator('.trend-chart')
  await expect(chart.getByRole('button', { name: /^Tháng \d{2}\/\d{4}:/ })).toHaveCount(6)
  const previous = data.monthly_series[4]
  await chart.getByRole('button', { name: new RegExp(`^Tháng ${previous.month.slice(5)}/${previous.month.slice(0, 4)}:`) }).focus()
  await page.keyboard.press('Enter')
  await expect(chart.locator('.trend-detail')).toContainText(money(previous.invoice_total))
  await expect(chart.getByRole('link', { name: 'Xem hóa đơn tháng này →', exact: true })).toHaveAttribute('href', `/owner/invoices?billing_month=${previous.month}`)
  await chart.getByRole('button', { name: new RegExp(`^Tháng ${month.slice(5)}/${month.slice(0, 4)}:`) }).click()
  await page.getByRole('heading', { name: 'Thống kê trực quan', exact: true }).click()
  await page.setViewportSize({ width: 1440, height: 1000 })
  await page.locator('.dashboard-charts').screenshot({ path: resolve(screenshots, 'ct8-charts-desktop.png'), animations: 'disabled' })
  await chart.getByRole('button', { name: 'Thu / chi', exact: true }).click()
  await expect(chart.getByRole('heading', { name: 'Thu chi theo tháng', exact: true })).toBeVisible()
  await chart.getByRole('button', { name: new RegExp(`^Tháng ${month.slice(5)}/${month.slice(0, 4)}:`) }).click()
  const current = data.monthly_series[5]
  for (const field of ['cash_income', 'cash_expense', 'cash_net']) await expect(chart.locator('.trend-detail')).toContainText(money(current[field]))
  await chart.getByRole('link', { name: 'Xem sổ thu chi tháng này →', exact: true }).click()
  await expect(page).toHaveURL(`/owner/cash?month=${month}`)
  await expect(page.getByLabel('Tháng phát sinh', { exact: true })).toHaveValue(month)
  await page.goto('/owner')
  await page.getByRole('article', { name: 'Trạng thái hóa đơn', exact: true }).getByRole('link', { name: /^Thu một phần/ }).click()
  await expect(page).toHaveURL(`/owner/invoices?billing_month=${month}&payment_status=partially_paid`)
  await page.goto('/owner')
  await page.getByRole('heading', { name: 'Thống kê trực quan', exact: true }).waitFor()
  for (const width of [360, 768, 1024]) { await page.setViewportSize({ width, height: 900 }); await noOverflow(page) }
  await page.setViewportSize({ width: 390, height: 844 })
  await page.getByRole('heading', { name: 'Thống kê trực quan', exact: true }).click()
  await page.locator('.dashboard-charts').screenshot({ path: resolve(screenshots, 'ct8-charts-mobile.png'), animations: 'disabled' })
  await page.getByLabel('Tháng theo dõi', { exact: true }).fill('1900-01')
  await page.getByRole('button', { name: 'Cập nhật', exact: true }).click()
  await expect(page.getByText('Chưa có hóa đơn kỳ này.', { exact: true })).toBeVisible()
  await expect(page.getByText('Chưa có phát sinh trong giai đoạn này.', { exact: true })).toBeVisible()
  await expect(page.locator('.month-column[aria-pressed="true"]')).toHaveAttribute('aria-label', /^Tháng 01\/1900:/)
})

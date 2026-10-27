import { expect, test, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'
const password = 'E2e-Password-2077!'
const shots = resolve(process.cwd(), '.cache/e2e/screenshots')
mkdirSync(shots, { recursive: true })
test.describe.configure({ timeout: 90_000 })
test.beforeEach(({ page }) => { page.setDefaultTimeout(15_000) })
async function login(page: Page, username = 'e2e_owner') {
  await page.goto('/login')
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username)
  await page.getByLabel('Mật khẩu', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await expect(page).toHaveURL(username === 'e2e_owner' ? '/owner' : '/tenant')
}
async function mutate(page: Page, method: string, path: string, data?: unknown) {
  await page.request.get('/api/auth/csrf-cookie')
  const token = (await page.context().cookies()).find(item => item.name === 'XSRF-TOKEN')!
  const response = await page.request.fetch(`/api/owner${path}`, { method, data, headers: { 'X-XSRF-TOKEN': decodeURIComponent(token.value) } })
  expect(response.ok(), await response.text()).toBe(true)
  return response.status() === 204 ? null : (await response.json()).data
}
async function newEntry(page: Page, description: string, amount = '125000.50', type = 'expense') {
  return mutate(page, 'POST', '/cash-transactions', { idempotency_key: crypto.randomUUID(), transaction_type: type, amount, occurred_at: '2026-01-15 09:00:00', description, reason: 'Dữ liệu kiểm thử riêng' })
}
async function filter(page: Page, q: string) {
  await page.getByLabel('Tháng phát sinh', { exact: true }).fill('2026-01')
  await page.getByLabel('Tìm nội dung hoặc mã hóa đơn', { exact: true }).fill(q)
  await page.getByRole('button', { name: 'Áp dụng bộ lọc', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Đang tải sổ thu chi', exact: true })).toHaveCount(0)
}
async function form(page: Page, description: string) {
  await page.getByRole('button', { name: 'Thêm khoản thu chi', exact: true }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByRole('combobox', { name: 'Loại khoản thu chi', exact: true }).selectOption('expense')
  await dialog.getByLabel('Số tiền (VNĐ) (bắt buộc)', { exact: true }).fill('125000.50')
  await dialog.getByLabel('Thời điểm phát sinh (giờ Việt Nam) (bắt buộc)', { exact: true }).fill('2026-01-15T09:00')
  await dialog.getByLabel('Nội dung thu chi (bắt buộc)', { exact: true }).fill(description)
  await dialog.getByLabel('Ghi chú ghi nhận', { exact: true }).fill('Kiểm thử qua giao diện')
  await dialog.getByRole('button', { name: 'Xem lại khoản thu chi', exact: true }).click()
  await expect(dialog.getByRole('heading', { name: 'Xem lại khoản thu chi', exact: true })).toBeVisible()
}
async function detail(page: Page, description: string) {
  await page.getByRole('button', { name: `Chi tiết và nhật ký: ${description}`, exact: true }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.getByRole('heading', { name: 'Chi tiết và nhật ký thu chi', exact: true })).toBeVisible()
  await expect(dialog.getByText(description, { exact: true })).toBeVisible()
  return dialog
}

test('CT6 reviews money before creation, edits, soft-deletes and restores with readable audit history', async ({ page }) => {
  await login(page); await page.goto('/owner/cash')
  const description = 'CT6 UI sửa khóa phòng thử'
  let postCount = 0
  page.on('request', request => { if (request.method() === 'POST' && request.url().endsWith('/cash-transactions')) postCount++ })
  await form(page, description); expect(postCount).toBe(0)
  let dialog = page.getByRole('dialog')
  await dialog.getByRole('button', { name: 'Xác nhận ghi nhận', exact: true }).click()
  await expect(dialog.getByRole('heading', { name: 'Chi tiết và nhật ký thu chi', exact: true })).toBeVisible()
  expect(postCount).toBe(1)
  await dialog.getByRole('button', { name: 'Sửa khoản này', exact: true }).click()
  await dialog.getByLabel('Số tiền (VNĐ) (bắt buộc)', { exact: true }).fill('140000.75')
  await dialog.getByLabel('Lý do thay đổi', { exact: true }).fill('Bổ sung tiền làm chìa')
  await dialog.getByRole('button', { name: 'Xem lại thay đổi', exact: true }).click()
  await expect(dialog.getByText('Trước: 125.000,50 ₫', { exact: true })).toBeVisible()
  await expect(dialog.getByText('Sau: 140.000,75 ₫', { exact: true })).toBeVisible()
  await dialog.getByRole('button', { name: 'Xác nhận thay đổi', exact: true }).click()
  await expect(dialog.getByRole('button', { name: 'Sửa khoản này', exact: true })).toBeVisible()
  await dialog.getByRole('button', { name: 'Xóa khoản này', exact: true }).click()
  await dialog.getByLabel('Lý do thay đổi', { exact: true }).fill('Kiểm tra khôi phục khoản xóa nhầm')
  await dialog.getByRole('button', { name: 'Xem lại thay đổi', exact: true }).click()
  await dialog.getByRole('button', { name: 'Xác nhận thay đổi', exact: true }).click()
  await expect(dialog.getByRole('button', { name: 'Hoàn tác để khôi phục', exact: true })).toBeVisible()
  await dialog.getByRole('button', { name: 'Đóng', exact: true }).click()
  await page.getByRole('button', { name: 'Khoản đã xóa', exact: true }).click(); await filter(page, description)
  dialog = await detail(page, description)
  await expect(dialog.getByText('Đã xóa', { exact: true }).first()).toBeVisible()
  await dialog.getByRole('button', { name: 'Hoàn tác để khôi phục', exact: true }).click()
  await dialog.getByLabel('Lý do thay đổi (bắt buộc)', { exact: true }).fill('Đối chiếu xong, khôi phục khoản hợp lệ')
  await dialog.getByRole('button', { name: 'Xem lại thay đổi', exact: true }).click()
  await dialog.getByRole('button', { name: 'Xác nhận thay đổi', exact: true }).click()
  await expect(dialog.getByRole('button', { name: 'Sửa khoản này', exact: true })).toBeVisible()
  await expect(dialog.getByText('Hoàn tác', { exact: true }).first()).toBeVisible()
  await expect(dialog.getByText('Bổ sung tiền làm chìa', { exact: false })).toBeVisible()
  await dialog.getByText('Xem thay đổi trước và sau', { exact: true }).first().click()
  await page.screenshot({ path: resolve(shots, 'ct6-cash-history.png'), animations: 'disabled' })
  await dialog.getByRole('button', { name: 'Đóng', exact: true }).click()
  await page.getByRole('button', { name: 'Sổ thu chi', exact: true }).click()
  await expect(page.getByRole('heading', { name: description, exact: true })).toBeVisible()
  const data = (await (await page.request.get(`/api/owner/cash-transactions?q=${encodeURIComponent(description)}`)).json()).data
  expect(data).toHaveLength(1); expect(data[0].amount).toBe('140000.75'); expect(data[0].is_deleted).toBe(false)
})

test('CT6 retries the identical durable creation after the server commits but the response is lost', async ({ page }) => {
  await login(page); await page.goto('/owner/cash')
  const description = 'CT6 mất phản hồi đã ghi nhận'
  const payloads: unknown[] = []
  let originalId = 0
  await page.route('**/api/owner/cash-transactions', async route => {
    if (route.request().method() !== 'POST') { await route.continue(); return }
    payloads.push(route.request().postDataJSON())
    const response = await route.fetch(); expect(response.status()).toBe(201); originalId = (await response.json()).data.id
    await route.abort('failed')
  })
  await form(page, description)
  await page.getByRole('dialog').getByRole('button', { name: 'Xác nhận ghi nhận', exact: true }).click()
  await expect(page.getByRole('dialog').getByRole('alert')).toBeVisible()
  expect(payloads).toHaveLength(1)
  await page.unroute('**/api/owner/cash-transactions')
  await page.reload()
  await page.getByRole('button', { name: 'Thêm khoản thu chi', exact: true }).click()
  const dialog = page.getByRole('dialog')
  await expect(dialog.getByText('Có yêu cầu đang chờ xác định kết quả', { exact: true })).toBeVisible()
  await expect(dialog.getByText(description, { exact: true })).toBeVisible()
  page.on('request', request => { if (request.method() === 'POST' && request.url().endsWith('/cash-transactions')) payloads.push(request.postDataJSON()) })
  await dialog.getByRole('button', { name: 'Gửi lại yêu cầu đang chờ', exact: true }).click()
  await expect(dialog.getByRole('heading', { name: 'Chi tiết và nhật ký thu chi', exact: true })).toBeVisible()
  expect(payloads).toHaveLength(2); expect(payloads[1]).toEqual(payloads[0])
  const response = (await (await page.request.get(`/api/owner/cash-transactions?q=${encodeURIComponent(description)}`)).json()).data
  expect(response).toHaveLength(1); expect(response[0].id).toBe(originalId)
  expect((await (await page.request.get(`/api/owner/cash-transactions/${originalId}/history`)).json()).meta.total).toBe(1)
})

test('CT6 version conflict requires reload and another review while keeping edited intent', async ({ page }) => {
  await login(page)
  const description = 'CT6 xung đột phiên bản'
  const entry = await newEntry(page, description, '80000.00')
  await page.goto('/owner/cash'); await filter(page, description)
  const dialog = await detail(page, description)
  await dialog.getByRole('button', { name: 'Sửa khoản này', exact: true }).click()
  await dialog.getByLabel('Số tiền (VNĐ) (bắt buộc)', { exact: true }).fill('90000.25')
  await dialog.getByLabel('Lý do thay đổi', { exact: true }).fill('Giữ nội dung đang nhập')
  await mutate(page, 'PATCH', `/cash-transactions/${entry.id}`, { version_token: entry.version_token, amount: '85000.00', reason: 'Sửa từ phiên khác' })
  await dialog.getByRole('button', { name: 'Xem lại thay đổi', exact: true }).click()
  await dialog.getByRole('button', { name: 'Xác nhận thay đổi', exact: true }).click()
  await expect(dialog.getByRole('alert')).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Xác nhận thay đổi', exact: true })).toBeDisabled()
  await dialog.getByRole('button', { name: 'Tải phiên bản mới để đối chiếu', exact: true }).click()
  await expect(dialog.getByLabel('Số tiền (VNĐ) (bắt buộc)', { exact: true })).toHaveValue('90000.25')
  await expect(dialog.getByLabel('Lý do thay đổi', { exact: true })).toHaveValue('Giữ nội dung đang nhập')
  await dialog.getByRole('button', { name: 'Xem lại thay đổi', exact: true }).click()
  await expect(dialog.getByText('Trước: 85.000 ₫', { exact: true })).toBeVisible()
  await dialog.getByRole('button', { name: 'Xác nhận thay đổi', exact: true }).click()
  await expect(dialog.getByRole('button', { name: 'Sửa khoản này', exact: true })).toBeVisible()
  expect((await (await page.request.get(`/api/owner/cash-transactions/${entry.id}`)).json()).data.amount).toBe('90000.25')
})

test('CT6 a stale form in another tab reconciles the pending request before creating a new one', async ({ page }) => {
  await login(page); await page.goto('/owner/cash')
  const firstDescription = 'CT6 hai tab yêu cầu chưa rõ', secondDescription = 'CT6 hai tab khoản chưa gửi'
  await form(page, firstDescription)
  const other = await page.context().newPage()
  await other.goto('/owner/cash'); await form(other, secondDescription)
  await page.route('**/api/owner/cash-transactions', async route => {
    if (route.request().method() !== 'POST') { await route.continue(); return }
    const response = await route.fetch(); expect(response.status()).toBe(201); await route.abort('failed')
  })
  await page.getByRole('dialog').getByRole('button', { name: 'Xác nhận ghi nhận', exact: true }).click()
  await expect(page.getByRole('dialog').getByRole('alert')).toBeVisible()
  let otherPosts = 0
  other.on('request', request => { if (request.method() === 'POST' && request.url().endsWith('/cash-transactions')) otherPosts++ })
  const dialog = other.getByRole('dialog')
  await dialog.getByRole('button', { name: 'Xác nhận ghi nhận', exact: true }).click()
  await expect(dialog.getByRole('alert')).toContainText('Có yêu cầu khác đang chờ')
  await expect(dialog.getByText(firstDescription, { exact: true })).toBeVisible()
  expect(otherPosts).toBe(0)
  await dialog.getByRole('button', { name: 'Gửi lại yêu cầu đang chờ', exact: true }).click()
  await expect(dialog.getByRole('heading', { name: 'Chi tiết và nhật ký thu chi', exact: true })).toBeVisible()
  expect(otherPosts).toBe(1)
  const records = (await (await other.request.get('/api/owner/cash-transactions?q=CT6%20hai%20tab')).json()).data
  expect(records).toHaveLength(1); expect(records[0].description).toBe(firstDescription)
  await other.close()
})

test('CT6 CSRF rejection is not replayed automatically and explicit retry keeps the key and payload', async ({ page }) => {
  await login(page); await page.goto('/owner/cash')
  const payloads: unknown[] = []
  await page.route('**/api/owner/cash-transactions', async route => {
    if (route.request().method() !== 'POST') { await route.continue(); return }
    payloads.push(route.request().postDataJSON())
    if (payloads.length === 1) await route.fulfill({ status: 419, contentType: 'application/json', body: JSON.stringify({ message: 'Phiên bảo mật đã đổi, vui lòng thử lại.' }) })
    else await route.continue()
  })
  await form(page, 'CT6 kiểm tra CSRF riêng')
  const dialog = page.getByRole('dialog')
  await dialog.getByRole('button', { name: 'Xác nhận ghi nhận', exact: true }).click()
  await expect(dialog.getByRole('alert')).toBeVisible(); expect(payloads).toHaveLength(1)
  await dialog.getByRole('button', { name: 'Gửi lại yêu cầu đang chờ', exact: true }).click()
  await expect(dialog.getByRole('heading', { name: 'Chi tiết và nhật ký thu chi', exact: true })).toBeVisible()
  expect(payloads).toHaveLength(2); expect(payloads[1]).toEqual(payloads[0])
})

test('CT6 a lost restore response reloads history without silently undoing the successful restore', async ({ page }) => {
  await login(page)
  const description = 'CT6 mất phản hồi hoàn tác khôi phục'
  const entry = await newEntry(page, description)
  await mutate(page, 'DELETE', `/cash-transactions/${entry.id}`, { version_token: entry.version_token, reason: 'Xóa để kiểm tra khôi phục' })
  await page.goto('/owner/cash')
  await page.getByRole('button', { name: 'Khoản đã xóa', exact: true }).click(); await filter(page, description)
  const dialog = await detail(page, description)
  await dialog.getByRole('button', { name: 'Hoàn tác để khôi phục', exact: true }).click()
  await dialog.getByLabel('Lý do thay đổi (bắt buộc)', { exact: true }).fill('Khôi phục đã đối chiếu')
  await dialog.getByRole('button', { name: 'Xem lại thay đổi', exact: true }).click()
  let revertCount = 0
  await page.route(`**/api/owner/cash-transactions/${entry.id}/revert`, async route => {
    revertCount++; const response = await route.fetch(); expect(response.status()).toBe(200); await route.abort('failed')
  })
  await dialog.getByRole('button', { name: 'Xác nhận thay đổi', exact: true }).click()
  await expect(dialog.getByRole('alert')).toBeVisible(); expect(revertCount).toBe(1)
  await dialog.getByRole('button', { name: 'Tải phiên bản mới để đối chiếu', exact: true }).click()
  await expect(dialog.getByRole('heading', { name: 'Chi tiết và nhật ký thu chi', exact: true })).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Sửa khoản này', exact: true })).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Xác nhận thay đổi', exact: true })).toHaveCount(0)
  expect(revertCount).toBe(1)
  expect((await (await page.request.get(`/api/owner/cash-transactions/${entry.id}`)).json()).data.is_deleted).toBe(false)
})

test('CT6 real ledger filters, day/month totals, pagination, empty and network retry fit a phone', async ({ page }) => {
  await login(page)
  for (let index = 0; index < 17; index++) await newEntry(page, `CT6 tổng hợp ${String(index).padStart(2, '0')}`, index % 2 ? '100000.25' : '50000.50', index % 2 ? 'other_income' : 'expense')
  await page.goto('/owner/cash?month=2026-01&entry_type=expense')
  await expect(page.getByLabel('Tháng phát sinh', { exact: true })).toHaveValue('2026-01')
  await expect(page.getByRole('combobox', { name: 'Loại khoản', exact: true })).toHaveValue('expense')
  await page.getByRole('combobox', { name: 'Loại khoản', exact: true }).selectOption('')
  await filter(page, 'CT6 tổng hợp')
  await expect(page.getByText('17 kết quả · Trang 1 / 2', { exact: true })).toBeVisible()
  await expect(page.getByText('800.002 ₫', { exact: true }).first()).toBeVisible()
  await page.getByRole('button', { name: 'Trang sau', exact: true }).click()
  await expect(page.getByText('17 kết quả · Trang 2 / 2', { exact: true })).toBeVisible()
  await page.getByRole('combobox', { name: 'Nhóm báo cáo', exact: true }).selectOption('month')
  await page.getByRole('button', { name: 'Áp dụng bộ lọc', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Tổng hợp từng tháng', exact: true })).toBeVisible()
  await page.screenshot({ path: resolve(shots, 'ct6-cash.png'), fullPage: true, animations: 'disabled' })
  await page.setViewportSize({ width: 390, height: 844 })
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true)
  await page.screenshot({ path: resolve(shots, 'ct6-cash-mobile.png'), fullPage: true, animations: 'disabled' })
  await page.route('**/api/owner/cash-flow?**', route => route.abort('failed'))
  await page.getByRole('button', { name: 'Làm mới sổ', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Thử tải lại sổ', exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: /^CT6 tổng hợp/ })).toHaveCount(0)
  await page.unroute('**/api/owner/cash-flow?**')
  await page.getByRole('button', { name: 'Thử tải lại sổ', exact: true }).click()
  await expect(page.getByRole('heading', { name: /^CT6 tổng hợp/ }).first()).toBeVisible()
  await filter(page, 'CT6 không tồn tại một khoản nào')
  await expect(page.getByRole('heading', { name: 'Chưa có khoản thu chi phù hợp', exact: true })).toBeVisible()
})

test('CT6 tenant cannot enter cash workspace or access cash endpoints', async ({ page }) => {
  await login(page, 'e2e_tenant'); await page.goto('/owner/cash')
  await expect(page.getByRole('heading', { name: 'Bạn không có quyền truy cập', exact: true })).toBeVisible()
  expect((await page.request.get('/api/owner/cash-flow')).status()).toBe(403)
  expect((await page.request.get('/api/owner/cash-transactions')).status()).toBe(403)
})

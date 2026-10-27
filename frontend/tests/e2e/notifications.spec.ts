import { expect, test, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'
import type { AppNotification } from '../../src/types/notifications'

const fixturePassword = 'E2e-Password-2077!'
const screenshotDirectory = resolve(process.cwd(), '.cache/e2e/screenshots')
mkdirSync(screenshotDirectory, { recursive: true })

async function login(page: Page, username: string) {
  await page.goto('/login')
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username)
  await page.getByLabel('Mật khẩu', { exact: true }).fill(fixturePassword)
  const response = page.waitForResponse((result) => result.url().endsWith('/api/auth/login') && result.request().method() === 'POST')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  expect((await response).status()).toBe(200)
  await expect(page).toHaveURL(/\/(?:owner|tenant)\/?$/)
}

async function openInbox(page: Page, username: string) {
  await login(page, username)
  await page.getByRole('link', { name: /^Thông báo(?:,|$)/ }).click()
  await expect(page).toHaveURL(/\/notifications(?:\?|$)/)
  await expect(page.getByRole('heading', { name: 'Thông báo', exact: true })).toBeVisible()
  await expect(page.getByText('Đang tải thông báo…', { exact: true })).toHaveCount(0)
}

async function unreadCount(page: Page): Promise<number> {
  const response = await page.request.get('/api/notifications/unread-count')
  expect(response.status()).toBe(200)
  return (await response.json()).data.unread_count as number
}

async function screenshot(page: Page, name: string) {
  await page.screenshot({ path: resolve(screenshotDirectory, `${name}.png`), fullPage: true })
  if (name === 'notifications-owner' || name === 'notifications-tenant-mobile') {
    await page.screenshot({ path: resolve(screenshotDirectory, `${name}-viewport.png`), fullPage: false })
  }
}

async function noHorizontalOverflow(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true)
}

const listUrl = /\/api\/notifications(?:\?.*)?$/

test('owner inbox uses real private notifications, pagination, unread and type filters', async ({ page }) => {
  await openInbox(page, 'e2e_notify_owner')
  await expect(page.getByRole('article')).toHaveCount(15)
  await expect(page.getByRole('heading', { name: 'Báo hỏng cần xem 18', exact: true })).toBeVisible()
  await expect(page.getByText('Cập nhật sửa chữa 16', { exact: true })).toHaveCount(0)
  await expect(page.getByRole('navigation', { name: 'Phân trang thông báo' })).toContainText('18 thông báo')
  expect(await unreadCount(page)).toBe(17)
  await noHorizontalOverflow(page)
  await screenshot(page, 'notifications-owner')

  await page.getByRole('button', { name: 'Trang sau', exact: true }).click()
  await expect(page).toHaveURL(/page=2/)
  await expect(page.getByRole('article')).toHaveCount(3)
  await expect(page.getByRole('heading', { name: 'Báo hỏng cần xem 01', exact: true })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Trang sau', exact: true })).toBeDisabled()

  await page.getByRole('checkbox', { name: 'Chỉ xem chưa đọc', exact: true }).check()
  await expect(page.getByRole('navigation', { name: 'Phân trang thông báo' })).toContainText('17 thông báo')
  await expect(page.getByRole('article')).toHaveCount(15)
  await page.getByRole('button', { name: 'Trang sau', exact: true }).click()
  await expect(page.getByRole('article')).toHaveCount(2)
  await expect(page.getByRole('heading', { name: 'Báo hỏng cần xem 01', exact: true })).toHaveCount(0)

  await page.getByLabel('Loại thông báo', { exact: true }).selectOption('invoice_reminder')
  await expect(page.getByText('Không có thông báo chưa đọc', { exact: true })).toBeVisible()
  await expect(page.getByRole('article')).toHaveCount(0)
  await page.getByRole('button', { name: 'Xem tất cả thông báo', exact: true }).click()
  await expect(page.getByRole('article')).toHaveCount(15)
})

test('tenant opens current permitted invoice and maintenance subjects through real role APIs', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await openInbox(page, 'e2e_notify_tenant')
  await expect(page.getByRole('article')).toHaveCount(15)
  await expect(page.getByText('Thông báo không được phép xem', { exact: true })).toHaveCount(0)
  await expect(page.getByText('Báo hỏng cần xem 18', { exact: true })).toHaveCount(0)
  expect((await page.request.get('/api/tenant/maintenance-requests/2')).status()).toBe(404)
  expect((await page.request.get('/api/owner/invoices/1')).status()).toBe(403)
  await noHorizontalOverflow(page)
  await screenshot(page, 'notifications-tenant-mobile')

  await page.getByLabel('Loại thông báo', { exact: true }).selectOption('invoice_reminder')
  await expect(page.getByRole('article')).toHaveCount(2)
  const invoiceCard = page.getByRole('article').filter({ hasText: 'Nhắc thanh toán hóa đơn 02' })
  const invoiceResponse = page.waitForResponse((response) => response.url().endsWith('/api/tenant/invoices/1'))
  await invoiceCard.getByRole('button', { name: /^Xem nội dung/ }).click()
  expect((await invoiceResponse).status()).toBe(200)
  const invoiceDialog = page.getByRole('dialog')
  await expect(invoiceDialog.getByText('HD-E2E-2026-10-A101', { exact: true })).toBeVisible()
  await expect(invoiceDialog).toContainText('2.800.000')
  await expect(invoiceDialog).toContainText('A101')
  await noHorizontalOverflow(page)
  await screenshot(page, 'notification-invoice-mobile')
  await page.keyboard.press('Escape')
  await expect(invoiceDialog).toHaveCount(0)

  // Opening again must recheck the real API, including when the notice is already read.
  const freshInvoice = page.waitForResponse((response) => response.url().endsWith('/api/tenant/invoices/1'))
  await invoiceCard.getByRole('button', { name: /^Xem nội dung/ }).click()
  expect((await freshInvoice).status()).toBe(200)
  await expect(page.getByRole('dialog').getByText('HD-E2E-2026-10-A101', { exact: true })).toBeVisible()
  await page.keyboard.press('Escape')

  await page.getByLabel('Loại thông báo', { exact: true }).selectOption('maintenance_updated')
  await expect(page.getByRole('navigation', { name: 'Phân trang thông báo' })).toContainText('16 thông báo')
  const reportResponse = page.waitForResponse((response) => response.url().endsWith('/api/tenant/maintenance-requests/1'))
  await page.getByRole('article').filter({ hasText: 'Cập nhật sửa chữa 16' }).getByRole('button', { name: /^Xem nội dung/ }).click()
  expect((await reportResponse).status()).toBe(200)
  await expect(page.getByRole('dialog').getByText('Vòi nước phòng A101 bị rò', { exact: true })).toBeVisible()
  await expect(page.getByRole('dialog')).toContainText('Thợ sẽ đến kiểm tra chiều nay.')
  await expect(page.getByRole('dialog')).toContainText('Đang xử lý')
})

test('a CSRF failure never retries a read mutation automatically and allows an explicit retry', async ({ page }) => {
  await openInbox(page, 'e2e_notify_owner')
  const before = await unreadCount(page)
  const card = page.getByRole('article').filter({ hasText: 'Báo hỏng cần xem 18' })
  let writeAttempts = 0
  await page.route(/\/api\/notifications\/\d+\/read$/, async (route) => {
    writeAttempts++
    if (writeAttempts === 1) {
      await route.fulfill({ status: 419, contentType: 'application/json', body: JSON.stringify({ message: 'Phiên bảo mật đã thay đổi. Vui lòng thử lại thao tác.' }) })
    } else {
      await route.continue()
    }
  })
  await card.getByRole('button', { name: /^Đánh dấu đã đọc:/ }).click()
  await expect(page.getByText('Phiên bảo mật đã thay đổi. Vui lòng thử lại thao tác.', { exact: true })).toBeVisible()
  await expect(card.getByText('Chưa đọc', { exact: true })).toBeVisible()
  expect(await unreadCount(page)).toBe(before)
  expect(writeAttempts).toBe(1)

  await card.getByRole('button', { name: /^Đánh dấu đã đọc:/ }).click()
  await expect(card.getByText('Đã đọc', { exact: true })).toBeVisible()
  expect(writeAttempts).toBe(2)
  expect(await unreadCount(page)).toBe(before - 1)
})

test('mark all read persists and remains scoped to the tenant account', async ({ page, browser }) => {
  const ownerContext = await browser.newContext()
  const ownerPage = await ownerContext.newPage()
  await login(ownerPage, 'e2e_notify_owner')
  const ownerUnread = await unreadCount(ownerPage)
  expect(ownerUnread).toBeGreaterThan(0)
  await openInbox(page, 'e2e_notify_tenant')
  await page.getByRole('button', { name: 'Đánh dấu tất cả đã đọc', exact: true }).click()
  await expect(page.getByText('Bạn đã đọc hết các thông báo.', { exact: true })).toBeVisible()
  expect(await unreadCount(page)).toBe(0)
  expect(await unreadCount(ownerPage)).toBe(ownerUnread)
  await page.reload()
  await expect(page.getByText('Bạn đã đọc hết các thông báo.', { exact: true })).toBeVisible()
  await page.getByRole('checkbox', { name: 'Chỉ xem chưa đọc', exact: true }).check()
  await expect(page.getByText('Không có thông báo chưa đọc', { exact: true })).toBeVisible()
  await expect(page.getByRole('article')).toHaveCount(0)
  await ownerContext.close()
})

test('owner marks one notice, sees the changed badge after reload and opens current report detail', async ({ page }) => {
  await openInbox(page, 'e2e_notify_owner')
  const before = await unreadCount(page)
  const card = page.getByRole('article').filter({ hasText: 'Báo hỏng cần xem 17' })
  await card.getByRole('button', { name: /^Đánh dấu đã đọc:/ }).click()
  await expect(card.getByText('Đã đọc', { exact: true })).toBeVisible()
  expect(await unreadCount(page)).toBe(before - 1)
  await page.reload()
  await expect(page.getByText(`Bạn có ${before - 1} thông báo chưa đọc.`, { exact: true })).toBeVisible()
  await expect(card.getByText('Đã đọc', { exact: true })).toBeVisible()
  await expect(page.getByRole('link', { name: /^Thông báo(?:,|$)/ })).toContainText(String(before - 1))

  const response = page.waitForResponse((result) => result.url().endsWith('/api/owner/maintenance-requests/1'))
  await card.getByRole('button', { name: /^Xem nội dung/ }).click()
  expect((await response).status()).toBe(200)
  await expect(page.getByRole('dialog').getByText('Vòi nước phòng A101 bị rò', { exact: true })).toBeVisible()
  await expect(page.getByRole('dialog')).toContainText('Nước rỉ ở van dưới bồn rửa.')
  await screenshot(page, 'notification-maintenance-owner')
  await page.keyboard.press('Escape')
  await page.getByRole('button', { name: 'Đánh dấu tất cả đã đọc', exact: true }).click()
  await expect(page.getByText('Bạn đã đọc hết các thông báo.', { exact: true })).toBeVisible()
  expect(await unreadCount(page)).toBe(0)
})

test('a genuinely empty account has a clear empty state on mobile', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await openInbox(page, 'e2e_notify_empty')
  await expect(page.getByText('Chưa có thông báo phù hợp', { exact: true })).toBeVisible()
  await expect(page.getByRole('article')).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Đánh dấu tất cả đã đọc', exact: true })).toBeDisabled()
  expect(await unreadCount(page)).toBe(0)
  await noHorizontalOverflow(page)
  await screenshot(page, 'notifications-empty-mobile')
})

test('failed refresh hides old private cards and an explicit retry loads real data again', async ({ page }) => {
  await openInbox(page, 'e2e_notify_owner')
  await expect(page.getByRole('article')).toHaveCount(15)
  await page.route(listUrl, (route) => route.abort('failed'))
  await page.getByRole('button', { name: 'Làm mới', exact: true }).click()
  await expect(page.getByText('Chưa tải được thông báo', { exact: true })).toBeVisible()
  await expect(page.getByRole('article')).toHaveCount(0)
  await expect(page.getByText('Báo hỏng cần xem 18', { exact: true })).toHaveCount(0)
  await screenshot(page, 'notifications-error')
  await page.unroute(listUrl)
  await page.getByRole('button', { name: 'Thử lại', exact: true }).click()
  await expect(page.getByRole('article')).toHaveCount(15)
  await expect(page.getByRole('heading', { name: 'Báo hỏng cần xem 18', exact: true })).toBeVisible()
})

test('a subject that loses permission shows an unavailable state without cached invoice details', async ({ page }) => {
  await openInbox(page, 'e2e_notify_tenant')
  await page.getByLabel('Loại thông báo', { exact: true }).selectOption('invoice_reminder')
  await expect(page.getByRole('article')).toHaveCount(2)
  const card = page.getByRole('article').filter({ hasText: 'Nhắc thanh toán hóa đơn 02' })
  await card.getByRole('button', { name: /^Xem nội dung/ }).click()
  await expect(page.getByRole('dialog').getByText('HD-E2E-2026-10-A101', { exact: true })).toBeVisible()
  await page.keyboard.press('Escape')

  await page.route('**/api/tenant/invoices/1', (route) => route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ message: 'Không tìm thấy nội dung.' }) }))
  await card.getByRole('button', { name: /^Xem nội dung/ }).click()
  await expect(page.getByRole('dialog').getByText('Nội dung không còn khả dụng', { exact: true })).toBeVisible()
  await expect(page.getByRole('dialog').getByText('HD-E2E-2026-10-A101', { exact: true })).toHaveCount(0)
})

test('real session expiry clears the inbox before another account signs in', async ({ page, context }) => {
  await openInbox(page, 'e2e_notify_owner')
  await expect(page.getByRole('article')).toHaveCount(15)
  await page.request.get('/api/auth/csrf-cookie')
  const token = (await context.cookies()).find((cookie) => cookie.name === 'XSRF-TOKEN')
  expect(token).toBeDefined()
  expect((await page.request.post('/api/auth/logout', { headers: { 'X-XSRF-TOKEN': decodeURIComponent(token!.value) } })).status()).toBe(200)
  await page.getByRole('button', { name: 'Làm mới', exact: true }).click()
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
  await expect(page.getByText('Báo hỏng cần xem 18', { exact: true })).toHaveCount(0)
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill('e2e_notify_empty')
  await page.getByLabel('Mật khẩu', { exact: true }).fill(fixturePassword)
  const signedIn = page.waitForResponse(response => response.url().endsWith('/api/auth/login') && response.request().method() === 'POST')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  expect((await signedIn).status()).toBe(200)
  await expect(page).toHaveURL(/\/(?:tenant|notifications)(?:\?|$)/)
  await page.goto('/notifications')
  await expect(page.getByText('Chưa có thông báo phù hợp', { exact: true })).toBeVisible()
  await expect(page.getByRole('article')).toHaveCount(0)
  await expect(page.getByText('Báo hỏng cần xem 18', { exact: true })).toHaveCount(0)
})

test('reading the last item on an unread page normalizes pagination and the URL', async ({ page }) => {
  await login(page, 'e2e_notify_owner')
  const actual = await page.request.get('/api/notifications?per_page=100')
  expect(actual.status()).toBe(200)
  const notices = (await actual.json()).data as AppNotification[]
  // Only list/count responses model this pagination edge. Authentication, CSRF,
  // notification IDs and the idempotent mark-read request still use real Laravel.
  let unread = notices.slice(0, 16).map((notice) => ({ ...notice, is_read: false, read_at: null }))
  expect(unread).toHaveLength(16)
  await page.route('**/api/notifications/unread-count', (route) => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ data: { unread_count: unread.length } }),
  }))
  await page.route(listUrl, (route) => {
    const currentPage = Number(new URL(route.request().url()).searchParams.get('page') ?? 1)
    const lastPage = Math.max(1, Math.ceil(unread.length / 15))
    return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({
      data: unread.slice((currentPage - 1) * 15, currentPage * 15),
      meta: { current_page: currentPage, last_page: lastPage, per_page: 15, total: unread.length },
    }) })
  })
  await page.route(/\/api\/notifications\/\d+\/read$/, async (route) => {
    const response = await route.fetch()
    expect(response.status()).toBe(200)
    const payload = await response.json()
    unread = unread.filter((notice) => notice.id !== payload.data.id)
    await route.fulfill({ response })
  })

  await page.goto('/notifications?unread=1&page=2')
  await expect(page.getByRole('article')).toHaveCount(1)
  await expect(page.getByRole('navigation', { name: 'Phân trang thông báo' })).toContainText('Trang 2 / 2')
  await page.getByRole('article').getByRole('button', { name: /^Đánh dấu đã đọc:/ }).click()
  await expect(page.getByRole('article')).toHaveCount(15)
  await expect(page.getByRole('navigation', { name: 'Phân trang thông báo' })).toContainText('Trang 1 / 1')
  await expect.poll(() => new URL(page.url()).searchParams.get('page')).toBeNull()
  expect(new URL(page.url()).searchParams.get('unread')).toBe('1')
  await page.reload()
  await expect(page.getByRole('article')).toHaveCount(15)
  await expect(page.getByRole('navigation', { name: 'Phân trang thông báo' })).toContainText('Trang 1 / 1')
})

test('login preserves an allowed shared notification deep link and its filters', async ({ page }) => {
  const target = '/notifications?type=invoice_reminder&unread=1'
  await page.goto(target)
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
  expect(new URL(page.url()).searchParams.get('redirect')).toBe(target)
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill('e2e_notify_empty')
  await page.getByLabel('Mật khẩu', { exact: true }).fill(fixturePassword)
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await expect(page).toHaveURL(target)
  await expect(page.getByRole('heading', { name: 'Thông báo', exact: true })).toBeVisible()
  await expect(page.getByLabel('Loại thông báo', { exact: true })).toHaveValue('invoice_reminder')
  await expect(page.getByRole('checkbox', { name: 'Chỉ xem chưa đọc', exact: true })).toBeChecked()
  await expect(page.getByText('Không có thông báo chưa đọc', { exact: true })).toBeVisible()
})

test('shared forbidden and missing-page screens provide a safe return to the tenant area', async ({ page }) => {
  await login(page, 'e2e_notify_empty')
  await page.goto('/owner')
  await expect(page).toHaveURL('/forbidden')
  await expect(page.getByRole('heading', { name: 'Bạn không có quyền truy cập', exact: true })).toBeVisible()
  await page.getByRole('link', { name: 'Về không gian của bạn', exact: true }).click()
  await expect(page).toHaveURL('/tenant')

  await page.goto('/duong-dan-khong-ton-tai-e2e')
  await expect(page.getByRole('heading', { name: 'Không tìm thấy trang', exact: true })).toBeVisible()
  await page.getByRole('link', { name: 'Về không gian của bạn', exact: true }).click()
  await expect(page).toHaveURL('/tenant')
})

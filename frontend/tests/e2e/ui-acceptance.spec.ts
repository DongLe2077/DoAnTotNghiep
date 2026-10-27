import { expect, test, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'

const shots = resolve(process.cwd(), '.cache/e2e/screenshots')
mkdirSync(shots, { recursive: true })
test.describe.configure({ timeout: 180_000 })
test.beforeEach(({ page }) => page.setDefaultTimeout(15_000))

async function login(page: Page, username: string) {
  await page.goto('/login')
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username)
  await page.getByLabel('Mật khẩu', { exact: true }).fill('E2e-Password-2077!')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await expect(page).toHaveURL(username === 'e2e_owner' ? '/owner' : '/tenant')
}

async function checkPage(page: Page, path: string) {
  await page.goto(path)
  await expect(page).toHaveURL(path)
  const main = page.locator('#main-content')
  await expect(main.getByRole('heading', { level: 1 }).first()).toBeVisible()
  await expect(main.locator('.page-state--loading')).toHaveCount(0)
  await expect(main.locator('.page-state--error')).toHaveCount(0)
  await page.evaluate(() => document.fonts.ready)
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), path).toBe(true)
}

test('forbidden page explains the active role and can switch accounts without discarding a failed logout', async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 800 })
  await login(page, 'e2e_notify_empty')
  await page.goto('/owner/announcements')
  await expect(page).toHaveURL('/forbidden')
  await expect(page.getByText(/Bạn đang đăng nhập bằng tài khoản e2e_notify_empty \(Người thuê\)/)).toBeVisible()
  await expect(page.getByText('Các tab cùng trình duyệt dùng chung phiên đăng nhập.', { exact: true })).toBeVisible()
  await page.route('**/api/auth/logout', route => route.abort('failed'), { times: 1 })
  await page.getByRole('button', { name: 'Đăng xuất để đổi tài khoản', exact: true }).click()
  await expect(page.getByRole('alert')).toContainText('Chưa thể đăng xuất')
  expect((await page.request.get('/api/auth/me')).status()).toBe(200)
  await expect(page).toHaveURL('/forbidden')
  await page.screenshot({ path: resolve(shots, 'acceptance-forbidden-mobile.png'), fullPage: true, animations: 'disabled' })
  await page.getByRole('button', { name: 'Đăng xuất để đổi tài khoản', exact: true }).click()
  await expect(page).toHaveURL(/\/login\?reason=logged-out$/)
  expect((await page.request.get('/api/auth/me')).status()).toBe(401)
  await login(page, 'e2e_owner')
  await page.goto('/tenant')
  await expect(page).toHaveURL('/forbidden')
  await expect(page.getByText(/Bạn đang đăng nhập bằng tài khoản e2e_owner \(Chủ trọ\)/)).toBeVisible()
  await page.getByRole('link', { name: 'Về không gian của bạn', exact: true }).click()
  await expect(page).toHaveURL('/owner')
})

test('owner pages and low-height navigation remain usable at 320px without horizontal scrolling', async ({ page }) => {
  await login(page, 'e2e_owner')
  await page.setViewportSize({ width: 320, height: 740 })
  for (const path of ['/owner', '/owner/rooms', '/owner/services', '/owner/tenants', '/owner/stays', '/owner/stays/new', '/owner/meters', '/owner/invoices', '/owner/invoices/new', '/owner/payments', '/owner/receivables', '/owner/cash', '/owner/maintenance', '/owner/announcements', '/owner/announcements/new']) {
    await checkPage(page, path)
    if (path === '/owner/payments') {
      await page.getByLabel('Từ thời điểm (giờ Việt Nam)', { exact: true }).fill('2026-10-01T08:30')
      await page.getByLabel('Đến thời điểm (giờ Việt Nam)', { exact: true }).fill('2026-10-25T20:30')
      const filtered = page.waitForResponse(response => response.url().includes('/api/owner/payments?') && response.request().method() === 'GET')
      await page.getByRole('button', { name: 'Lọc lần thu', exact: true }).click()
      expect((await filtered).status()).toBe(200)
      await expect(page.locator('#main-content .page-state--loading')).toHaveCount(0)
      await page.getByRole('heading', { level: 1 }).click()
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true)
      await page.locator('.management-toolbar').screenshot({ path: resolve(shots, 'acceptance-date-final.png'), animations: 'disabled' })
    }
  }
  await page.screenshot({ path: resolve(shots, 'acceptance-announcement-form-mobile.png'), fullPage: true, animations: 'disabled' })
  await page.setViewportSize({ width: 1024, height: 600 })
  await checkPage(page, '/owner/rooms')
  const navigation = page.locator('.main-navigation')
  await navigation.getByRole('link', { name: 'Thông báo chung', exact: true }).click()
  await expect(page).toHaveURL('/owner/announcements')
  await page.getByRole('link', { name: '+ Soạn thông báo', exact: true }).click()
  await expect(page).toHaveURL('/owner/announcements/new')
  await page.setViewportSize({ width: 1280, height: 800 })
  await checkPage(page, '/owner')
  await page.screenshot({ path: resolve(shots, 'acceptance-dashboard-desktop.png'), fullPage: true, animations: 'disabled' })
})

test('tenant pages retain all navigation and readable content on narrow screens', async ({ page }) => {
  await login(page, 'e2e_notify_tenant')
  await page.setViewportSize({ width: 320, height: 740 })
  for (const path of ['/tenant', '/tenant/invoices', '/tenant/maintenance', '/tenant/maintenance/new', '/tenant/profile', '/tenant/stays', '/account', '/notifications']) {
    await checkPage(page, path)
  }
  await page.getByRole('button', { name: 'Mở menu', exact: true }).click()
  const menu = page.getByRole('dialog', { name: 'Điều hướng chính', exact: true })
  await expect(menu).toBeVisible()
  await menu.getByRole('link', { name: 'Lịch sử cư trú', exact: true }).click()
  await expect(page).toHaveURL('/tenant/stays')
  await expect(page.getByRole('button', { name: 'Mở menu', exact: true })).toHaveAttribute('aria-expanded', 'false')
  await page.setViewportSize({ width: 390, height: 844 })
  await checkPage(page, '/tenant')
  await expect(page.getByRole('navigation', { name: 'Điều hướng người thuê', exact: true }).getByRole('link')).toHaveCount(4)
  await page.screenshot({ path: resolve(shots, 'acceptance-tenant-mobile.png'), fullPage: true, animations: 'disabled' })
})

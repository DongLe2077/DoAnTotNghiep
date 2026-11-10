import { expect, test, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'

const screenshots = resolve(process.cwd(), process.env.E2E_RESPONSIVE_SCREENSHOTS ?? '.cache/e2e/screenshots/responsive')
mkdirSync(screenshots, { recursive: true })
test.describe.configure({ timeout: 120_000 })
test.beforeEach(({ page }) => page.setDefaultTimeout(15_000))

async function login(page: Page, username = 'e2e_owner') {
  await page.goto('/login')
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username)
  await page.getByLabel('Mật khẩu', { exact: true }).fill('E2e-Password-2077!')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await expect(page).toHaveURL(username === 'e2e_owner' ? '/owner' : '/tenant')
  await expect(page.locator('#main-content .page-state--loading')).toHaveCount(0)
}

async function topbarInsideViewport(page: Page) {
  await page.evaluate(() => document.fonts.ready)
  // Read one layout frame: separate calls can straddle a font or viewport update.
  await expect.poll(() => page.evaluate(() => {
    const bar = document.querySelector('.topbar')?.getBoundingClientRect()
    const notice = document.querySelector('.pwa-notices')?.getBoundingClientRect()
    const heading = document.querySelector('.topbar .page-heading')?.getBoundingClientRect()
    const actions = document.querySelector('.topbar-actions')?.getBoundingClientRect()
    return {
      topAligned: !!bar && Math.abs(bar.top - (notice?.height ?? 0)) <= 1,
      inside: !!bar && bar.left >= 0 && bar.right <= innerWidth + 1,
      noOverlap: !!heading && !!actions && heading.right <= actions.left + 1,
      noOverflow: document.documentElement.scrollWidth <= innerWidth + 1,
    }
  }), { message: `Header geometry at ${page.url()}` }).toMatchObject({ topAligned: true, inside: true, noOverlap: true, noOverflow: true })
  await expect(page.getByRole('button', { name: 'Đăng xuất', exact: true })).toBeInViewport()
}

test('header stays reachable after scrolling, including a changing PWA notice and the mobile menu', async ({ page, context }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await login(page)
  for (const width of [320, 390, 768, 1024, 1440]) {
    await page.setViewportSize({ width, height: 844 })
    await page.evaluate(() => window.scrollTo(0, 1100))
    await expect.poll(() => page.evaluate(() => scrollY)).toBe(await page.evaluate(() => Math.min(1100, Math.max(0, document.documentElement.scrollHeight - innerHeight))))
    await topbarInsideViewport(page)
    if (width < 1024) {
      await page.getByRole('button', { name: 'Mở menu', exact: true }).click()
      const menu = page.getByRole('dialog', { name: 'Điều hướng chính', exact: true })
      await expect(menu).toBeVisible()
      await menu.getByRole('button', { name: 'Đóng menu', exact: true }).click()
      await expect(menu).toHaveCount(0)
      await topbarInsideViewport(page)
    }
  }
  await page.setViewportSize({ width: 390, height: 844 })
  await context.setOffline(true)
  await expect(page.getByRole('region', { name: 'Trạng thái kết nối', exact: true })).toBeVisible()
  await expect.poll(async () => {
    const bar = await page.locator('.topbar').boundingBox()
    const notice = await page.locator('.pwa-notices').boundingBox()
    return Math.abs(bar!.y - notice!.height)
  }).toBeLessThanOrEqual(1)
  await topbarInsideViewport(page)
  await page.screenshot({ path: resolve(screenshots, 'owner-sticky-mobile.png'), animations: 'disabled' })
  await context.setOffline(false)
  await topbarInsideViewport(page)
})

test('month control and update button fit phones and align on tablet and desktop', async ({ page }) => {
  await login(page)
  for (const width of [320, 390, 600, 768, 1024, 1440]) {
    await page.setViewportSize({ width, height: 900 })
    await page.evaluate(() => window.scrollTo(0, 0))
    const field = await page.getByLabel('Tháng theo dõi', { exact: true }).boundingBox()
    const button = await page.getByRole('button', { name: 'Cập nhật', exact: true }).boundingBox()
    expect(field).not.toBeNull(); expect(button).not.toBeNull()
    expect(field!.width).toBeGreaterThanOrEqual(200)
    if (width <= 600) {
      expect(button!.y).toBeGreaterThanOrEqual(field!.y + field!.height + 8)
      expect(Math.abs(button!.width - field!.width)).toBeLessThanOrEqual(1)
    } else {
      expect(Math.abs((button!.y + button!.height) - (field!.y + field!.height))).toBeLessThanOrEqual(1)
      expect(Math.abs(button!.height - field!.height)).toBeLessThanOrEqual(1)
    }
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true)
    if (width === 390 || width === 1440) await page.screenshot({ path: resolve(screenshots, `owner-dashboard-${width}.png`), animations: 'disabled' })
  }
  await page.getByLabel('Tháng theo dõi', { exact: true }).fill('2026-10')
  await page.getByRole('button', { name: 'Cập nhật', exact: true }).click()
  await expect(page.getByRole('link', { name: /Thực thu tháng 2026-10/ })).toBeVisible()
  await page.getByText('Xem số liệu chi tiết', { exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Hóa đơn kỳ 2026-10', exact: true })).toBeVisible()
  await page.goto('/owner/receivables')
  await expect(page.getByText('Tổng còn cần thu', { exact: true })).toBeVisible()
  const controls = [page.getByLabel('Kỳ hóa đơn', { exact: true }), page.getByRole('combobox', { name: 'Hạn thanh toán', exact: true }), page.getByRole('button', { name: 'Áp dụng bộ lọc', exact: true })]
  const boxes = await Promise.all(controls.map(control => control.boundingBox()))
  for (const box of boxes) {
    expect(box).not.toBeNull()
    expect(Math.abs(box!.y - boxes[0]!.y)).toBeLessThanOrEqual(1)
    expect(Math.abs(box!.height - boxes[0]!.height)).toBeLessThanOrEqual(1)
  }
  await expect(controls[0]).toHaveAccessibleDescription('Để trống kỳ hóa đơn để xem công nợ mọi tháng.')
  await page.screenshot({ path: resolve(screenshots, 'receivables-aligned-desktop.png'), animations: 'disabled' })
})

test('tenant header and bottom navigation stay usable on a narrow phone', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 740 })
  await login(page, 'e2e_notify_tenant')
  await page.goto('/tenant/invoices/1')
  await page.getByText('Chi tiết các khoản và PDF hóa đơn', { exact: true }).click()
  await page.evaluate(() => window.scrollTo(0, 900))
  await expect.poll(() => page.evaluate(() => scrollY)).toBeGreaterThan(100)
  await topbarInsideViewport(page)
  await expect(page.getByRole('navigation', { name: 'Điều hướng người thuê', exact: true })).toBeInViewport()
  await page.getByRole('button', { name: 'Mở menu', exact: true }).click()
  const menu = page.getByRole('dialog', { name: 'Điều hướng chính', exact: true })
  await menu.getByRole('link', { name: 'Lịch sử cư trú', exact: true }).click()
  await expect(page).toHaveURL('/tenant/stays')
  await expect(page.locator('.page-state--error')).toHaveCount(0)
})

for (const role of ['owner', 'tenant'] as const) {
  test(`${role} pages fit tablet and desktop without overlapping the navigation`, async ({ page }) => {
    test.setTimeout(240_000)
    await login(page, role === 'owner' ? 'e2e_owner' : 'e2e_notify_tenant')
    const paths = role === 'owner'
      ? ['/owner', '/owner/rooms', '/owner/services', '/owner/tenants', '/owner/groups', '/owner/stays', '/owner/stays/new', '/owner/meters', '/owner/meters/bulk', '/owner/invoices', '/owner/invoices/new', '/owner/payments', '/owner/receivables', '/owner/cash', '/owner/maintenance', '/owner/announcements', '/owner/announcements/new', '/owner/property']
      : ['/tenant', '/tenant/invoices', '/tenant/maintenance', '/tenant/maintenance/new', '/tenant/profile', '/tenant/stays', '/account', '/notifications']
    for (const width of [768, 1024, 1440]) {
      await page.setViewportSize({ width, height: 900 })
      for (const path of paths) {
        await page.goto(path)
        await expect(page).toHaveURL(path)
        await expect(page.locator('#main-content h1').first()).toBeVisible()
        await expect(page.locator('#main-content .page-state--loading')).toHaveCount(0)
        await expect(page.locator('#main-content .page-state--error')).toHaveCount(0)
        await topbarInsideViewport(page)
        if (width === 1440 && ['/owner/rooms','/owner/receivables','/owner/meters/bulk','/owner/property','/tenant/maintenance/new'].includes(path)) {
          await page.screenshot({ path: resolve(screenshots, `${path.slice(1).replaceAll('/', '-')}-${width}.png`), fullPage: true, animations: 'disabled' })
        }
      }
    }
  })
}

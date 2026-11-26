import { expect, test } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'

test('owner work and finance use existing data and fit a phone', async ({ page }) => {
  test.setTimeout(90_000)
  const shots = resolve(process.cwd(), '.cache/e2e/screenshots')
  mkdirSync(shots, { recursive: true })
  await page.goto('/login')
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill('e2e_owner')
  await page.getByLabel('Mật khẩu', { exact: true }).fill('E2e-Password-2077!')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await expect(page).toHaveURL('/owner')
  await page.goto('/owner/work')
  await expect(page.getByRole('heading', { name: 'Việc cần làm', exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Tình trạng phòng' })).toBeVisible()
  await page.screenshot({path: resolve(shots, 'phase7-work-desktop.png'), animations: 'disabled'})
  await page.getByRole('link', { name: 'Tài chính', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Thu và chi 6 tháng' })).toBeVisible()
  const response = await page.request.get('/api/owner/dashboard', {params:{month:await page.getByLabel('Tháng báo cáo').inputValue()}})
  expect(response.ok()).toBeTruthy()
  const data = (await response.json()).data
  const last = data.monthly_series.at(-1)
  const whole = (value:string) => Number(value).toLocaleString('vi-VN')
  await expect(page.locator('[aria-live="polite"]').filter({hasText:'Chênh lệch'})).toContainText(whole(last.cash_net))
  await page.screenshot({path: resolve(shots, 'phase7-finance-desktop.png'), animations: 'disabled'})
  await page.setViewportSize({width:390,height:844})
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
  const first = page.getByRole('button', {name:'Tháng ' + Number(data.monthly_series[0].month.slice(-2)) + '/' + data.monthly_series[0].month.slice(0,4),exact:true})
  await first.focus()
  await page.keyboard.press('Enter')
  await expect(first).toHaveAttribute('aria-pressed','true')
  await expect(page.locator('[aria-live="polite"]').filter({hasText:'Chênh lệch'})).toContainText(whole(data.monthly_series[0].cash_net))
  await page.screenshot({path: resolve(shots, 'phase7-finance-mobile.png'), animations: 'disabled'})
  await page.getByRole('link', {name:'Công việc',exact:true}).click()
  await expect(page.getByRole('heading', { name: 'Việc cần làm', exact: true })).toBeVisible()
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
  await page.screenshot({path: resolve(shots, 'phase7-work-mobile.png'), animations: 'disabled'})
})

test('owner navigation starts with Tổng quan on desktop and mobile', async ({ page }) => {
  await page.goto('/login')
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill('e2e_owner')
  await page.getByLabel('Mật khẩu', { exact: true }).fill('E2e-Password-2077!')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await expect(page).toHaveURL('/owner')

  for (const { width, height, navigation } of [
    { width: 1366, height: 900, navigation: 'Các mục thường dùng' },
    { width: 320, height: 760, navigation: 'Các mục thường dùng trên điện thoại' },
  ]) {
    await page.setViewportSize({ width, height })
    const nav = page.getByRole('navigation', { name: navigation, exact: true })
    await expect(nav.getByRole('link')).toHaveText(['Tổng quan', 'Công việc', 'Phòng', 'Tài chính'])
    await expect(nav.getByRole('link', { name: 'Tổng quan', exact: true })).toHaveAttribute('aria-current', 'page')
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)

    const menuButton = nav.getByRole('button', { name: 'Mở menu' })
    await menuButton.click()
    const dialog = page.getByRole('dialog', { name: 'Điều hướng chính' })
    await expect(dialog.getByRole('link', { name: 'Tổng quan', exact: true })).toHaveAttribute('aria-current', 'page')
    await dialog.getByRole('link', { name: 'Tổng quan', exact: true }).click()
    await expect(dialog).toBeHidden()
    await expect(menuButton).toBeFocused()
  }
})

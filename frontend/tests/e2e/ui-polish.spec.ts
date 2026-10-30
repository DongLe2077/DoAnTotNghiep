import { expect, test, type Page } from '@playwright/test'

test.describe.configure({ timeout: 90_000 })
async function login(page: Page, username = 'e2e_owner') {
  await page.goto('/login')
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username)
  await page.getByLabel('Mật khẩu', { exact: true }).fill('E2e-Password-2077!')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await expect(page).toHaveURL(username === 'e2e_owner' ? '/owner' : '/tenant')
}

for (const reducedMotion of ['no-preference', 'reduce'] as const) {
  test(`native dialog keeps focus, closes and reopens with motion=${reducedMotion}`, async ({ page }) => {
    await page.emulateMedia({ reducedMotion })
    await login(page)
    await page.goto('/owner/rooms')
    const opener = page.getByRole('button', { name: 'Thêm phòng', exact: true })
    await opener.click()
    const dialog = page.getByRole('dialog', { name: 'Thêm phòng', exact: true })
    await expect(dialog).toBeVisible()
    expect(await dialog.evaluate(node => (node as HTMLDialogElement).open)).toBe(true)
    expect(await dialog.evaluate(node => node.contains(document.activeElement))).toBe(true)
    if (reducedMotion === 'reduce') expect(await dialog.evaluate(node => getComputedStyle(node).transitionDuration.split(',').every(time => parseFloat(time) <= .001))).toBe(true)
    await page.keyboard.press('Shift+Tab')
    expect(await dialog.evaluate(node => node.contains(document.activeElement))).toBe(true)
    await page.keyboard.press('Escape')
    await expect(dialog).not.toBeVisible()
    await expect(opener).toBeFocused()
    await opener.click()
    await expect(dialog).toBeVisible()
    await dialog.getByRole('button', { name: 'Đóng hộp thoại', exact: true }).click()
    await expect(dialog).not.toBeVisible()
    await expect(opener).toBeFocused()
  })
}

test('dashboard waits without invented amounts, shows failure and retries with real data', async ({ page }) => {
  let release!: () => void
  const gate = new Promise<void>(resolve => { release = resolve })
  await page.route('**/api/owner/dashboard**', async route => {
    await gate
    await route.fulfill({ status: 503, contentType: 'application/json', body: JSON.stringify({ message: 'Chưa thể tổng hợp. Hãy thử lại.' }) })
  }, { times: 1 })
  try {
    await login(page)
    const skeleton = page.locator('.ui-skeleton--dashboard')
    await expect(skeleton).toBeVisible()
    await expect(skeleton).toHaveAttribute('aria-hidden', 'true')
    await expect(page.locator('.stat-money')).toHaveCount(0)
    release()
    await expect(page.locator('.page-state--error')).toContainText('Máy chủ chưa thể xử lý yêu cầu')
    await expect(skeleton).toHaveCount(0)
    await page.getByRole('button', { name: 'Thử lại', exact: true }).click()
    await expect(page.getByRole('heading', { name: 'Tình hình hiện tại', exact: true })).toBeVisible()
    await expect(page.locator('.page-state--error')).toHaveCount(0)
  } finally { release() }
})

test('owner can stay with unsaved property changes or explicitly discard them', async ({ page }) => {
  await login(page)
  await page.goto('/owner/property')
  const name = page.getByLabel('Tên nhà trọ (bắt buộc)', { exact: true })
  await name.fill('Bản nháp cần giữ')
  page.once('dialog', async dialog => { expect(dialog.type()).toBe('confirm'); await dialog.dismiss() })
  await page.getByRole('link', { name: 'Phòng', exact: true }).click()
  await expect(page).toHaveURL('/owner/property')
  await expect(name).toHaveValue('Bản nháp cần giữ')
  page.once('dialog', async dialog => { expect(dialog.message()).toContain('chưa lưu'); await dialog.accept() })
  await page.getByRole('link', { name: 'Phòng', exact: true }).click()
  await expect(page).toHaveURL('/owner/rooms')
})

test('a saved property leaves cleanly and a dirty form cannot block logout', async ({ page }) => {
  await login(page)
  await page.goto('/owner/property')
  await page.getByLabel('Tên nhà trọ (bắt buộc)', { exact: true }).fill('Nhà trọ kiểm thử giao diện')
  await page.getByLabel('Địa chỉ nhà trọ (bắt buộc)', { exact: true }).fill('Địa chỉ dùng riêng trong kiểm thử')
  await page.getByRole('button', { name: 'Xem lại thông tin', exact: true }).click()
  const review = page.getByRole('dialog', { name: 'Xem lại thông tin nhà trọ', exact: true })
  await review.getByRole('checkbox').check()
  await review.getByRole('button', { name: 'Xác nhận lưu thông tin', exact: true }).click()
  await expect(review).not.toBeVisible()
  let prompts = 0
  page.on('dialog', async dialog => { prompts++; await dialog.dismiss() })
  await page.getByRole('link', { name: 'Phòng', exact: true }).click()
  await expect(page).toHaveURL('/owner/rooms')
  await page.getByRole('link', { name: 'Thông tin nhà trọ', exact: true }).click()
  await page.getByLabel('Tên nhà trọ (bắt buộc)', { exact: true }).fill('Bản nháp trước đăng xuất')
  await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click()
  await expect(page).toHaveURL(/\/login\?reason=logged-out$/)
  expect(prompts).toBe(0)
  expect((await page.request.get('/api/auth/me')).status()).toBe(401)
})

test('tenant draft remains after cancelling navigation on a narrow screen', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await login(page, 'e2e_notify_tenant')
  await page.goto('/tenant/maintenance/new')
  const title = page.getByLabel(/Tiêu đề/).first()
  await title.fill('Nội dung đang nhập của người thuê')
  page.once('dialog', async dialog => { await dialog.dismiss() })
  await page.locator('.tenant-bottom-navigation a[href="/tenant/invoices"]').click()
  await expect(page).toHaveURL('/tenant/maintenance/new')
  await expect(title).toHaveValue('Nội dung đang nhập của người thuê')
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true)
  page.once('dialog', async dialog => { await dialog.accept() })
  await page.locator('.tenant-bottom-navigation a[href="/tenant/invoices"]').click()
  await expect(page).toHaveURL('/tenant/invoices')
})

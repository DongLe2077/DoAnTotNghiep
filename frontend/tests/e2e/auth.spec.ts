import { expect, test, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'

const fixturePassword = 'E2e-Password-2077!'
const screenshotDirectory = resolve(process.cwd(), '.cache/e2e/screenshots')
mkdirSync(screenshotDirectory, { recursive: true })

async function screenshot(page: Page, name: string) {
  await page.screenshot({ path: resolve(screenshotDirectory, `${name}.png`), fullPage: true })
}

async function expectNoHorizontalOverflow(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true)
}

async function fillLogin(page: Page, username: string, password = fixturePassword) {
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username)
  await page.getByLabel('Mật khẩu', { exact: true }).fill(password)
}

async function login(page: Page, username: string, password = fixturePassword) {
  await page.goto('/login')
  await fillLogin(page, username, password)
  const response = page.waitForResponse((result) => result.url().endsWith('/api/auth/login') && result.request().method() === 'POST')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  expect((await response).status()).toBe(200)
}

test('owner signs in with real cookies, survives reload, and signs out', async ({ page, context, request }) => {
  await page.goto('/owner')
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
  await screenshot(page, 'login-desktop')
  await login(page, 'e2e_owner')
  await expect(page).toHaveURL(/\/owner\/?$/)
  await expect(page.getByText('e2e_owner', { exact: true }).first()).toBeVisible()
  await screenshot(page, 'owner')

  const cookies = await context.cookies()
  expect(cookies.find((cookie) => cookie.name === 'rental_e2e_session')?.httpOnly).toBe(true)
  expect(cookies.some((cookie) => cookie.name === 'XSRF-TOKEN')).toBe(true)
  expect((await page.request.get('/api/auth/me')).status()).toBe(200)
  expect((await page.request.get('/api/owner/access')).status()).toBe(200)
  expect((await page.request.get('/api/tenant/access')).status()).toBe(403)

  await page.reload()
  await expect(page).toHaveURL(/\/owner\/?$/)
  await expect(page.getByText('e2e_owner', { exact: true }).first()).toBeVisible()
  const previousSession = (await context.cookies()).map((cookie) => `${cookie.name}=${cookie.value}`).join('; ')
  await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click()
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
  expect((await page.request.get('/api/auth/me')).status()).toBe(401)
  expect((await request.get('/api/auth/me', { headers: { Cookie: previousSession } })).status()).toBe(401)
  await page.goto('/owner')
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
})

test('tenant reaches their own area and cannot enter the owner area', async ({ page }) => {
  await login(page, 'e2e_tenant')
  await expect(page).toHaveURL(/\/tenant\/?$/)
  await screenshot(page, 'tenant')
  expect((await page.request.get('/api/tenant/access')).status()).toBe(200)
  expect((await page.request.get('/api/owner/access')).status()).toBe(403)
  await page.goto('/owner')
  await expect(page).toHaveURL(/\/(?:tenant|forbidden)\/?$/)
})

test('wrong password stays on login and displays the backend message', async ({ page }) => {
  await page.goto('/login')
  await fillLogin(page, 'e2e_owner', 'Incorrect-password!')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await expect(page.getByText(/Tên đăng nhập hoặc mật khẩu không đúng/).first()).toBeVisible()
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
  expect((await page.request.get('/api/auth/me')).status()).toBe(401)
})

for (const username of ['e2e_disabled', 'e2e_unknown_role']) {
  test(`${username} cannot obtain an authenticated session`, async ({ page }) => {
    await page.goto('/login')
    await fillLogin(page, username)
    await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
    await expect(page.getByText(/Tên đăng nhập hoặc mật khẩu không đúng/).first()).toBeVisible()
    expect((await page.request.get('/api/auth/me')).status()).toBe(401)
  })
}

test('real CSRF rejects mutations without the XSRF header', async ({ page }) => {
  await page.goto('/login')
  expect((await page.request.get('/api/auth/csrf-cookie')).status()).toBe(204)
  const result = await page.request.post('/api/auth/login', {
    data: { username: 'e2e_owner', password: fixturePassword },
  })
  expect(result.status()).toBe(419)
  expect((await page.request.get('/api/auth/me')).status()).toBe(401)
  // The UI then sends the actual XSRF header and can sign in normally.
  await login(page, 'e2e_owner')
  await expect(page).toHaveURL(/\/owner\/?$/)
})

test('password change validates the current password and requires a new login', async ({ page }) => {
  const newPassword = 'E2e-New-Password-2077!'
  await login(page, 'e2e_password_owner')
  await page.getByRole('link', { name: 'Tài khoản', exact: true }).first().click()
  await expect(page).toHaveURL(/\/account\/?$/)
  await screenshot(page, 'account')
  await page.getByLabel(/^Mật khẩu hiện tại(?:\s*\*)?$/).fill('Incorrect-password!')
  await page.getByLabel(/^Mật khẩu mới(?:\s*\*)?$/).fill(newPassword)
  await page.getByLabel(/^Xác nhận mật khẩu mới(?:\s*\*)?$/).fill(newPassword)
  await page.getByRole('button', { name: 'Lưu mật khẩu mới', exact: true }).click()
  await expect(page.getByText('Mật khẩu hiện tại không đúng.', { exact: true }).first()).toBeVisible()
  expect((await page.request.get('/api/auth/me')).status()).toBe(200)

  await page.getByLabel(/^Mật khẩu hiện tại(?:\s*\*)?$/).fill(fixturePassword)
  await page.getByRole('button', { name: 'Lưu mật khẩu mới', exact: true }).click()
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
  expect((await page.request.get('/api/auth/me')).status()).toBe(401)

  await fillLogin(page, 'e2e_password_owner', fixturePassword)
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await expect(page.getByText(/Tên đăng nhập hoặc mật khẩu không đúng/).first()).toBeVisible()
  await login(page, 'e2e_password_owner', newPassword)
  await expect(page).toHaveURL(/\/owner\/?$/)
})

test('repeated wrong passwords display the real rate-limit message', async ({ page, context }) => {
  await page.goto('/login')
  await page.request.get('/api/auth/csrf-cookie')
  const token = (await context.cookies()).find((cookie) => cookie.name === 'XSRF-TOKEN')
  expect(token).toBeDefined()

  for (let attempt = 0; attempt < 5; attempt += 1) {
    const result = await page.request.post('/api/auth/login', {
      data: { username: 'e2e_throttled', password: 'Incorrect-password!' },
      headers: { 'X-XSRF-TOKEN': decodeURIComponent(token!.value) },
    })
    expect(result.status()).toBe(401)
  }

  await fillLogin(page, 'e2e_throttled')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await expect(page.getByText(/Bạn đăng nhập sai quá nhiều lần/).first()).toBeVisible()
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
})

test('mobile login and navigation fit a 390px screen', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/login')
  await expect(page.getByLabel('Tên đăng nhập', { exact: true })).toBeVisible()
  await expectNoHorizontalOverflow(page)
  await screenshot(page, 'login-mobile')
  await login(page, 'e2e_owner')
  await expect(page).toHaveURL(/\/owner\/?$/)
  await expectNoHorizontalOverflow(page)

  const menuButton = page.getByRole('button', { name: 'Mở menu', exact: true })
  await menuButton.click()
  await expect(page.getByRole('dialog', { name: 'Điều hướng chính', exact: true })).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(menuButton).toHaveAttribute('aria-expanded', 'false')
  await menuButton.click()
  await page.getByRole('dialog', { name: 'Điều hướng chính', exact: true })
    .getByRole('link', { name: 'Tài khoản', exact: true }).click()
  await expect(page).toHaveURL(/\/account\/?$/)
  await expect(page.getByRole('heading', { name: 'Tài khoản của bạn', exact: true })).toBeVisible()
  await expect(menuButton).toHaveAttribute('aria-expanded', 'false')
  await expectNoHorizontalOverflow(page)
  await screenshot(page, 'account-mobile')
})

test('logging out synchronizes a second open tab', async ({ page, context }) => {
  await login(page, 'e2e_owner')
  const secondPage = await context.newPage()
  await secondPage.goto('/owner')
  await expect(secondPage.getByText('e2e_owner', { exact: true }).first()).toBeVisible()
  await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click()
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
  await expect(secondPage).toHaveURL(/\/login(?:\?|$)/)
  expect((await secondPage.request.get('/api/auth/me')).status()).toBe(401)
})

test('a failed logout keeps the session and offers a visible retry error', async ({ page }) => {
  await login(page, 'e2e_owner')
  await page.route('**/api/auth/logout', (route) => route.abort('failed'))
  await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click()
  await expect(page.getByRole('alert')).toContainText('Chưa thể đăng xuất')
  await expect(page).toHaveURL(/\/owner\/?$/)
  await expect(page.getByText('e2e_owner', { exact: true }).first()).toBeVisible()
  expect((await page.request.get('/api/auth/me')).status()).toBe(200)

  await page.unroute('**/api/auth/logout')
  await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click()
  await expect(page).toHaveURL(/\/login(?:\?|$)/)
  expect((await page.request.get('/api/auth/me')).status()).toBe(401)
})

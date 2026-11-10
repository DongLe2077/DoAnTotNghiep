import assert from 'node:assert/strict'
import { chromium, devices, expect } from '@playwright/test'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

// Read-only production review: the only allowed writes are login/logout.
const origin = 'https://rentbase.alwaysdata.net'
const ownerPassword = process.env.RENTBASE_REVIEW_OWNER_PASSWORD
const tenantPassword = process.env.RENTBASE_REVIEW_TENANT_PASSWORD
assert.ok(ownerPassword && tenantPassword, 'Set RENTBASE_REVIEW_OWNER_PASSWORD and RENTBASE_REVIEW_TENANT_PASSWORD before running the online review.')
const output = resolve(dirname(fileURLToPath(import.meta.url)), '../.cache/mobile-review-20261106/online')
mkdirSync(output, { recursive: true })
const browser = await chromium.launch({ channel: 'chrome' })
const result = { startedAt: new Date().toISOString(), browser: browser.version(), origin, emulated: true, physicalAndroid: false, checks: [], failures: [], blockedWrites: [], pageErrors: [], serverErrors: [] }
function persist() { writeFileSync(resolve(output, 'verification.json'), JSON.stringify(result, null, 2)) }
function check(condition, description) { assert.ok(condition, description); result.checks.push(description); persist() }
async function screenshot(page, name) { await page.screenshot({ path: resolve(output, `${name}.png`), animations: 'disabled', scale: 'css' }) }
async function layout(page, label) {
  const dimensions = await page.evaluate(() => {
    const header = document.querySelector('.topbar'), title = header?.querySelector('.page-heading')?.getBoundingClientRect(), actions = header?.querySelector('.topbar-actions')?.getBoundingClientRect(), rect = header?.getBoundingClientRect()
    return { overflow: document.documentElement.scrollWidth > innerWidth + 1, fits: !rect || rect.left >= -1 && rect.right <= innerWidth + 1, overlaps: title && actions && title.right > actions.left + 1 }
  })
  check(!dimensions.overflow && dimensions.fits && !dimensions.overlaps, `${label}: no horizontal overflow or header overlap`)
}
async function visit(page, path) {
  await page.goto(path)
  await expect(page).toHaveURL(`${origin}${path}`)
  await expect(page.locator('#main-content h1').first()).toBeVisible()
  await expect(page.locator('#main-content .page-state--loading')).toHaveCount(0)
  await expect(page.locator('#main-content .page-state--error')).toHaveCount(0)
  await page.evaluate(() => document.fonts.ready)
  await layout(page, path)
}
async function session(username) {
  const context = await browser.newContext({ ...devices['Pixel 7'], deviceScaleFactor: 1, viewport: { width: 390, height: 844 }, baseURL: origin, locale: 'vi-VN', acceptDownloads: true })
  await context.route('**/api/**', async route => {
    const request = route.request(), url = new URL(request.url())
    if (url.origin === origin && (['GET', 'HEAD', 'OPTIONS'].includes(request.method()) || request.method() === 'POST' && ['/api/auth/login', '/api/auth/logout'].includes(url.pathname))) return route.continue()
    result.blockedWrites.push({ method: request.method(), path: url.pathname }); persist()
    return route.abort('blockedbyclient')
  })
  const page = await context.newPage(); page.setDefaultTimeout(20_000)
  page.on('pageerror', error => result.pageErrors.push(error.message))
  page.on('response', response => { if (response.status() >= 500) result.serverErrors.push({ status: response.status(), url: response.url() }) })
  await page.goto('/login')
  await expect(page.getByRole('heading', { name: 'Đăng nhập', exact: true })).toBeVisible()
  await layout(page, `login-${username}`)
  if (username === 'admin') await screenshot(page, '01-login-390')
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username)
  await page.getByLabel('Mật khẩu', { exact: true }).fill(username === 'admin' ? ownerPassword : tenantPassword)
  const response = page.waitForResponse(r => r.request().method() === 'POST' && r.url() === `${origin}/api/auth/login`)
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  assert.equal((await response).status(), 200)
  await expect(page).toHaveURL(`${origin}/${username === 'admin' ? 'owner' : 'tenant'}`)
  check(true, `${username}: login reaches the correct role`)
  return { context, page }
}
async function menu(page, label) {
  await page.getByRole('button', { name: 'Mở menu', exact: true }).click()
  const navigation = page.getByRole('dialog', { name: 'Điều hướng chính', exact: true })
  await expect(navigation).toBeVisible()
  await screenshot(page, `menu-${label}`)
  await navigation.getByRole('link', { name: label === 'owner' ? 'Phòng' : 'Hóa đơn', exact: true }).click()
  await expect(navigation).toHaveCount(0)
  check(true, `${label}: mobile menu opens, navigates and closes`)
}
async function logout(page, role) {
  await page.getByRole('button', { name: 'Đăng xuất', exact: true }).click()
  await expect(page).toHaveURL(/\/login\?reason=logged-out$/)
  assert.equal((await page.request.get('/api/auth/me')).status(), 401)
  check(true, `${role}: logout invalidates its session`)
}

try {
  const owner = await session('admin')
  const paths = ['/owner', '/owner/rooms', '/owner/services', '/owner/tenants', '/owner/groups', '/owner/stays', '/owner/stays/new', '/owner/meters', '/owner/meters/bulk', '/owner/invoices', '/owner/invoices/new', '/owner/payments', '/owner/receivables', '/owner/cash', '/owner/maintenance', '/owner/announcements', '/owner/announcements/new', '/owner/property', '/account', '/notifications']
  for (const path of paths) { await visit(owner.page, path); process.stdout.write(`Online mobile OK: ${path}\n`) }
  await visit(owner.page, '/owner')
  await owner.page.getByLabel('Tháng theo dõi', { exact: true }).fill('2026-10')
  await owner.page.getByRole('button', { name: 'Cập nhật', exact: true }).click()
  await expect(owner.page.getByRole('heading', { name: 'Hóa đơn kỳ 2026-10', exact: true })).toBeVisible()
  await screenshot(owner.page, '02-owner-dashboard-390')
  check(true, 'Owner can select the demonstration invoice period and see charts')
  await owner.page.mouse.wheel(0, 1100)
  await expect.poll(() => owner.page.evaluate(() => {
    const header = document.querySelector('.topbar').getBoundingClientRect(), notices = document.querySelector('.pwa-notices')?.getBoundingClientRect()
    return Math.abs(header.top - (notices?.height ?? 0))
  })).toBeLessThanOrEqual(2)
  await screenshot(owner.page, '03-owner-sticky-header-390')
  check(true, 'Owner header remains at the top after scrolling')
  await menu(owner.page, 'owner')
  for (const size of [{ width: 320, height: 740 }, { width: 844, height: 390 }]) {
    await owner.page.setViewportSize(size)
    for (const path of ['/owner', '/owner/meters/bulk', '/owner/invoices/new', '/owner/receivables']) await visit(owner.page, path)
    await screenshot(owner.page, `owner-receivables-${size.width}`)
  }
  assert.equal((await owner.page.request.get('/api/tenant/access')).status(), 403)
  check(true, 'Owner cannot access tenant-only API')
  await logout(owner.page, 'owner'); await owner.context.close()

  const tenant = await session('user')
  for (const path of ['/tenant', '/tenant/invoices', '/tenant/maintenance', '/tenant/maintenance/new', '/tenant/profile', '/tenant/stays', '/account', '/notifications']) { await visit(tenant.page, path); process.stdout.write(`Online mobile OK: ${path}\n`) }
  await visit(tenant.page, '/tenant'); await screenshot(tenant.page, '04-tenant-home-390'); await menu(tenant.page, 'tenant')
  await visit(tenant.page, '/tenant/invoices')
  await tenant.page.getByRole('link', { name: 'Xem hóa đơn và lịch sử thanh toán →', exact: true }).first().click()
  await expect(tenant.page.locator('#main-content .page-state--loading')).toHaveCount(0)
  await expect(tenant.page.getByRole('button', { name: 'Tải PDF hóa đơn', exact: true })).toBeVisible()
  await layout(tenant.page, 'tenant-invoice-detail'); await screenshot(tenant.page, '05-tenant-invoice-390')
  const pendingDownload = tenant.page.waitForEvent('download')
  await tenant.page.getByRole('button', { name: 'Tải PDF hóa đơn', exact: true }).click()
  const download = await pendingDownload; await download.saveAs(resolve(output, 'tenant-invoice.pdf'))
  const pdf = readFileSync(resolve(output, 'tenant-invoice.pdf'))
  check(await download.failure() === null && pdf.subarray(0, 5).toString() === '%PDF-' && pdf.length > 5000, 'Authorized tenant downloads an actual invoice PDF')
  await visit(tenant.page, '/tenant/maintenance')
  await tenant.page.getByRole('link', { name: 'Xem tiến độ và ảnh →', exact: true }).first().click()
  await expect(tenant.page.locator('#main-content .page-state--loading')).toHaveCount(0)
  await tenant.page.getByRole('button', { name: 'Xem ảnh báo hỏng 1', exact: true }).click()
  const photo = tenant.page.getByRole('dialog')
  await expect(photo.getByRole('img')).toBeVisible()
  check(await photo.getByRole('img').evaluate(image => image.complete && image.naturalWidth > 0), 'Authorized tenant opens a loaded private incident photo')
  await screenshot(tenant.page, '06-tenant-photo-390')
  await photo.getByRole('button', { name: 'Đóng hộp thoại', exact: true }).click()
  for (const size of [{ width: 320, height: 740 }, { width: 844, height: 390 }]) {
    await tenant.page.setViewportSize(size)
    for (const path of ['/tenant', '/tenant/maintenance/new']) await visit(tenant.page, path)
    await screenshot(tenant.page, `tenant-new-report-${size.width}`)
  }
  assert.equal((await tenant.page.request.get('/api/owner/access')).status(), 403)
  await tenant.page.goto('/owner'); await expect(tenant.page).toHaveURL(`${origin}/forbidden`)
  check(true, 'Tenant is denied both owner API and owner page')
  await logout(tenant.page, 'tenant'); await tenant.context.close()
  check(result.blockedWrites.length === 0, 'No business writes were attempted on production')
  check(result.pageErrors.length === 0 && result.serverErrors.length === 0, 'No uncaught page errors or HTTP 5xx during the online review')
  result.completedAt = new Date().toISOString(); persist()
  process.stdout.write(`Online mobile review passed: ${result.checks.length} checks\n`)
} catch (error) {
  result.failures.push(String(error)); persist()
  for (const context of browser.contexts()) for (const page of context.pages()) await page.screenshot({ path: resolve(output, 'failure.png'), animations: 'disabled', scale: 'css' }).catch(() => {})
  throw error
} finally { await browser.close() }

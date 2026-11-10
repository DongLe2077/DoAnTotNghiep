import assert from 'node:assert/strict'
import { chromium, expect } from '@playwright/test'
import { spawn } from 'node:child_process'
import { cpSync, createWriteStream, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { setTimeout as pause } from 'node:timers/promises'
import { createServer } from 'node:net'
import { createMobileGateway } from './mobile-gateway.mjs'

const root = process.cwd(), output = resolve(root, process.env.PWA_RUNTIME_OUTPUT ?? '.cache/pwa-runtime-review'), directory = resolve(output, 'dist')
mkdirSync(output, { recursive: true }); cpSync(resolve(root, 'dist'), directory, { recursive: true })
const origin = 'http://127.0.0.1:15173', checks = [], password = 'E2e-Password-2077!'
for (const port of [15173, 18000]) await new Promise((resolve, reject) => { const probe = createServer(); probe.once('error', reject); probe.listen(port, '127.0.0.1', () => probe.close(resolve)) })
const backend = spawn(process.execPath, ['tests/e2e/support/serve-backend.mjs'], { cwd: root, env: { ...process.env, PHP_BINARY: process.env.PHP_BINARY || 'C:\\xampp\\php\\php.exe', E2E_API_PORT: '18000', E2E_FRONTEND_PORT: '15173' }, stdio: ['ignore', 'pipe', 'pipe', 'ipc'], windowsHide: true })
const log = createWriteStream(resolve(output, 'backend.log')); backend.stdout.pipe(log, { end: false }); backend.stderr.pipe(log, { end: false })
let gateway, browser
function check(value, description) { assert.ok(value, description); checks.push(description) }
async function json(page, path) { const response = await page.request.get(`/api${path}`); assert.equal(response.status(), 200, `${path}: ${await response.text()}`); return response.json() }
async function login(page, username) {
  await page.goto('/login'); await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username); await page.getByLabel('Mật khẩu', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click(); await expect(page).toHaveURL(username === 'e2e_owner' ? /\/owner$/ : /\/tenant$/)
  await page.evaluate(() => navigator.serviceWorker.ready); await page.waitForFunction(() => !!navigator.serviceWorker.controller)
  const ready = page.getByRole('button', { name: 'Đóng thông báo sẵn sàng', exact: true }); if (await ready.count()) await ready.click()
}
async function cachePaths(page) { return page.evaluate(async () => (await Promise.all((await caches.keys()).map(async key => (await (await caches.open(key)).keys()).map(request => new URL(request.url).pathname)))).flat()) }
async function screenshot(page, name) { await page.screenshot({ path: resolve(output, `${name}.png`), animations: 'disabled' }) }
try {
  const until = Date.now() + 40_000
  while (Date.now() < until) { try { if ((await fetch('http://127.0.0.1:18000/up')).ok) break } catch {} await pause(300) }
  assert.equal((await fetch('http://127.0.0.1:18000/up')).status, 200)
  gateway = createMobileGateway({ directory, backendPort: 18000, getOrigin: () => origin })
  await new Promise((resolve, reject) => { gateway.once('error', reject); gateway.listen(15173, '127.0.0.1', resolve) })
  browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' })
  const context = await browser.newContext({ baseURL: origin, viewport: { width: 393, height: 851 }, isMobile: true, hasTouch: true, locale: 'vi-VN', timezoneId: 'Asia/Ho_Chi_Minh' })
  const owner = await context.newPage(); owner.setDefaultTimeout(15_000)
  await login(owner, 'e2e_owner')
  const ownerId = (await json(owner, '/auth/me')).data.id
  check(await owner.evaluate(() => window.isSecureContext && !!navigator.serviceWorker.controller), 'Built frontend is controlled by the real service worker in a secure context')
  for (const width of [320, 393, 430, 851]) {
    await owner.setViewportSize({ width, height: width === 851 ? 393 : 851 })
    await owner.getByRole('button', { name: 'Mở menu', exact: true }).click(); await expect(owner.getByRole('dialog', { name: 'Điều hướng chính' })).toBeVisible()
    await owner.keyboard.press('Escape')
    check(await owner.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Owner menu and dashboard fit ${width}px including landscape`)
  }
  await owner.setViewportSize({ width: 393, height: 851 })
  await owner.goto('/owner/invoices/1'); await expect(owner.getByRole('button', { name: 'Ghi nhận thu tiền', exact: true })).toBeVisible()
  const before = (await json(owner, '/owner/invoices/1')).data
  const pdf = await owner.request.get('/api/owner/invoices/1/pdf'); check(pdf.status() === 200 && (await pdf.body()).subarray(0, 5).toString() === '%PDF-', 'Private owner PDF is a real PDF under the service worker')
  await owner.getByRole('button', { name: 'Ghi nhận thu tiền', exact: true }).click()
  await owner.getByLabel('Số tiền thực nhận (bắt buộc)', { exact: true }).fill('100000')
  await owner.evaluate(() => { Object.defineProperty(visualViewport, 'height', { value: 350, configurable: true }); visualViewport.dispatchEvent(new Event('resize')) })
  await expect(owner.locator('body')).toHaveClass(/keyboard-open/)
  const keyboardDialog = await owner.getByRole('dialog', { name: 'Ghi nhận tiền đã thu', exact: true }).boundingBox()
  check(keyboardDialog.y >= 0 && keyboardDialog.y + keyboardDialog.height <= 350, 'Simulated reduced visual viewport keeps the payment dialog above the keyboard area')
  await owner.evaluate(() => { delete visualViewport.height; visualViewport.dispatchEvent(new Event('resize')) })
  await owner.getByRole('button', { name: 'Xem lại lần thu', exact: true }).click()
  let writes = 0; owner.on('request', request => { if (request.method() === 'POST' && request.url().includes('/payments')) writes++ })
  await context.setOffline(true)
  await owner.getByRole('button', { name: 'Xác nhận đã nhận tiền', exact: true }).click()
  await expect(owner.getByRole('button', { name: 'Gửi lại lần thu đang chờ', exact: true })).toBeVisible()
  const key = `rental:pending-payment:${ownerId}:1`, pending = await owner.evaluate(key => sessionStorage.getItem(key), key)
  check(pending && writes === 0, 'Offline confirmation retains the payment intent without sending a write')
  await owner.keyboard.press('Escape'); await screenshot(owner, 'owner-offline')
  await context.setOffline(false); await pause(300)
  check(writes === 0 && (await json(owner, '/owner/invoices/1')).data.paid_amount === before.paid_amount, 'Restoring connectivity never replays the pending payment automatically')
  await context.setOffline(true); await owner.reload()
  await expect(owner.getByRole('heading', { name: 'Chưa kết nối được với máy chủ', exact: true })).toBeVisible()
  check(!await owner.getByText(before.invoice_number, { exact: true }).count(), 'Cold offline opening serves the public shell and no cached invoice/account data')
  const privateOffline = await owner.evaluate(async () => { try { await fetch('/api/owner/invoices/1/pdf'); return false } catch { return true } })
  check(privateOffline, 'Private PDF never falls back to cached HTML while offline')
  await screenshot(owner, 'cold-offline')
  await context.setOffline(false); await owner.getByRole('button', { name: 'Thử kết nối lại', exact: true }).click(); await expect(owner).toHaveURL(/\/owner\/invoices\/1$/)
  check(await owner.evaluate(key => sessionStorage.getItem(key), key) === pending, 'Cold opening and reconnection preserve the exact payment idempotency key and payload')
  await owner.getByRole('button', { name: 'Kiểm tra lần thu đang chờ', exact: true }).click()
  await owner.getByRole('button', { name: 'Gửi lại lần thu đang chờ', exact: true }).click()
  await expect(owner.getByRole('dialog')).toHaveCount(0)
  check(writes === 1 && (await json(owner, '/owner/invoices/1')).data.paid_amount === '100000.00' && await owner.evaluate(key => sessionStorage.getItem(key), key) === null, 'Only explicit retry sends the payment once, reconciles the balance and clears its pending intent')
  const downloadEvent = owner.waitForEvent('download'); await owner.getByRole('button', { name: 'Tải PDF hóa đơn', exact: true }).click(); const download = await downloadEvent; await download.saveAs(resolve(output, 'owner-invoice.pdf'))
  check(readFileSync(resolve(output, 'owner-invoice.pdf')).subarray(0, 5).toString() === '%PDF-', 'Android-sized UI downloads an authenticated invoice PDF')
  await owner.goto('/owner')
  const other = await context.newPage(); await other.goto('/owner/property'); await other.getByRole('heading', { name: 'Thông tin nhà trọ', exact: true }).waitFor()
  const field = other.locator('form input').first(), original = await field.inputValue(); await field.fill(`${original} PWA draft`)
  await owner.evaluate(() => { window.__beforePwaUpdate = 'keep' })
  const workerPath = resolve(directory, 'sw.js'); writeFileSync(workerPath, `${readFileSync(workerPath, 'utf8')}\n// PWA acceptance update ${Date.now()}\n`)
  await owner.evaluate(async () => { const registration = await navigator.serviceWorker.getRegistration(); await registration.update() })
  const updates = owner.getByRole('region', { name: 'Cập nhật ứng dụng' })
  await expect(updates).toBeVisible()
  await updates.getByRole('button', { name: 'Để sau', exact: true }).click(); await expect(updates.getByRole('button', { name: 'Xem cập nhật', exact: true })).toBeVisible()
  check(await owner.evaluate(() => window.__beforePwaUpdate) === 'keep', 'Deferring an available update does not reload the page')
  await updates.getByRole('button', { name: 'Xem cập nhật', exact: true }).click(); await updates.getByRole('button', { name: 'Cập nhật ngay', exact: true }).click()
  await expect(updates).toContainText('Một tab còn đang thao tác')
  check(await field.inputValue() === `${original} PWA draft` && await owner.evaluate(() => window.__beforePwaUpdate) === 'keep', 'Another tab with unsaved data vetoes the update without losing its draft')
  await field.fill(original); await other.goto('/owner/invoices/new?occupancy_id=1')
  await other.getByRole('heading', { name: 'Lập hóa đơn tháng', exact: true }).waitFor()
  await other.waitForLoadState('networkidle')
  await updates.getByRole('button', { name: 'Cập nhật ngay', exact: true }).click(); await expect(updates).toContainText('Một tab còn đang thao tác')
  check(await owner.evaluate(() => window.__beforePwaUpdate) === 'keep', 'A separate invoice-issue screen with a selected stay vetoes updating even outside a form element')
  await other.goto('/owner/invoices/1')
  await expect(other.getByRole('button', { name: 'Làm mới số dư', exact: true })).toBeVisible()
  let release, held, settled
  const gate = new Promise(resolve => { release = resolve }), started = new Promise(resolve => { held = resolve }), completed = new Promise(resolve => { settled = resolve })
  await other.route('**/api/owner/invoices/1', async route => { try { const response = await route.fetch(); held(); await gate; await route.fulfill({ response }) } finally { settled() } })
  await other.getByRole('button', { name: 'Làm mới số dư', exact: true }).click(); await started
  await updates.getByRole('button', { name: 'Cập nhật ngay', exact: true }).click(); await expect(updates).toContainText('Một tab còn đang thao tác')
  check(await owner.evaluate(() => window.__beforePwaUpdate) === 'keep', 'An in-flight API request in another tab vetoes activation')
  release(); await completed; await other.unroute('**/api/owner/invoices/1'); await expect(other.getByRole('button', { name: 'Làm mới số dư', exact: true })).toBeVisible(); await other.goto('/owner')
  const pendingKey = `rental.cash.pending.v1:${ownerId}:pwa-review`
  await owner.evaluate(key => localStorage.setItem(key, '{"review":"pending"}'), pendingKey)
  await expect(updates.getByRole('button', { name: 'Cập nhật ngay', exact: true })).toBeDisabled()
  check(await owner.evaluate(key => localStorage.getItem(key), pendingKey) === '{"review":"pending"}', 'An unresolved intent blocks updating without modifying or removing the stored value')
  await owner.evaluate(key => localStorage.removeItem(key), pendingKey)
  const retainedKey = `rental.cash.pending.v1:${ownerId + 1000}:pwa-review`, retained = '{"idempotency_key":"keep-other-account-2026"}'
  await owner.evaluate(({ key, value }) => localStorage.setItem(key, value), { key: retainedKey, value: retained })
  await expect(updates.getByRole('button', { name: 'Cập nhật ngay', exact: true })).toBeEnabled()
  await screenshot(owner, 'update-ready')
  await updates.getByRole('button', { name: 'Cập nhật ngay', exact: true }).click()
  await owner.waitForFunction(() => window.__beforePwaUpdate === undefined)
  await expect(owner.getByRole('heading', { name: 'Tổng quan', exact: true })).toBeVisible()
  await expect(other.getByRole('heading', { name: 'Tổng quan', exact: true })).toBeVisible()
  await expect(updates).toHaveCount(0)
  check(await owner.evaluate(({ key, value }) => localStorage.getItem(key) === value, { key: retainedKey, value: retained }), 'Confirmed update reloads safe tabs and preserves another account’s pending payload byte-for-byte')
  const cached = await cachePaths(owner)
  check(cached.length > 100 && cached.every(path => /^\/(?:index\.html|favicon\.svg|assets\/|fonts\/|icons\/)/.test(path)), 'After private invoices and PDF downloads, every service-worker cache entry remains a public build asset')
  await owner.getByRole('button', { name: 'Đăng xuất', exact: true }).click(); await expect(other).toHaveURL(/\/login/)
  await context.setOffline(true); await owner.goto('/owner/invoices/1'); await expect(owner.getByRole('heading', { name: 'Chưa kết nối được với máy chủ', exact: true })).toBeVisible()
  check(!await owner.getByText(before.invoice_number, { exact: true }).count(), 'Logging out then opening offline cannot recover the prior private invoice')
  await context.close()

  const tenantContext = await browser.newContext({ baseURL: origin, viewport: { width: 393, height: 851 }, isMobile: true, hasTouch: true, locale: 'vi-VN' })
  const tenant = await tenantContext.newPage(); tenant.setDefaultTimeout(15_000); await login(tenant, 'e2e_notify_tenant')
  await tenant.goto('/tenant/maintenance/new')
  await tenant.getByLabel('Tiêu đề sự cố (bắt buộc)', { exact: true }).fill('PWA camera input acceptance')
  await tenant.getByLabel('Mô tả sự cố (bắt buộc)', { exact: true }).fill('Ảnh minh họa giả để kiểm tra luồng báo hỏng trên điện thoại.')
  await expect(tenant.getByRole('button', { name: 'Chụp ảnh sự cố', exact: true })).toBeVisible()
  await tenant.locator('input[capture="environment"]').setInputFiles(resolve(root, 'public/icons/icon-192.png'))
  await expect(tenant.getByRole('img', { name: 'Ảnh đã chọn 1', exact: true })).toBeVisible()
  await tenant.getByRole('button', { name: 'Xem lại trước khi gửi', exact: true }).click(); await tenant.getByRole('button', { name: 'Xác nhận gửi báo hỏng', exact: true }).click()
  await expect(tenant).toHaveURL(/\/tenant\/maintenance\/\d+\?created=1$/)
  const reportId = new URL(tenant.url()).pathname.split('/').at(-1), report = (await json(tenant, `/tenant/maintenance-requests/${reportId}`)).data
  check(report.photos.length === 1 && (await tenant.request.get(report.photos[0].url)).status() === 200, 'Tenant camera input accepts a fixture file and the real backend stores a private report photo')
  await screenshot(tenant, 'tenant-repair-photo')
  await tenant.goto('/tenant/invoices/1'); const tenantPdf = await tenant.request.get('/api/tenant/invoices/1/pdf')
  check(tenantPdf.status() === 200 && (await tenantPdf.body()).subarray(0, 5).toString() === '%PDF-', 'Tenant PDF remains accessible by invoice grant under the service worker')
  check((await tenant.request.get('/api/owner/property')).status() === 403, 'PWA installation and cache do not grant tenant owner privileges')
  await tenant.goto('/tenant/invoices'); await tenant.getByRole('link', { name: /Xem hóa đơn/ }).first().click(); await expect(tenant).toHaveURL(/\/tenant\/invoices\/\d+$/); await tenant.getByRole('heading', { name: /Hóa đơn phòng/ }).waitFor(); await tenant.goBack(); await expect(tenant).toHaveURL(/\/tenant\/invoices$/)
  check(true, 'Phone back navigation returns from invoice detail to its list')
  for (const width of [320, 393, 430, 851]) {
    await tenant.setViewportSize({ width, height: width === 851 ? 393 : 851 }); await tenant.goto('/tenant')
    check(await tenant.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Tenant home and navigation fit ${width}px including landscape`)
  }
  const tenantCached = await cachePaths(tenant); check(tenantCached.every(path => !path.startsWith('/api/')), 'Repair upload, photo viewing and tenant PDF leave no private API response in Cache Storage')
  await tenantContext.close()
  writeFileSync(resolve(output, 'verification.json'), JSON.stringify({ checkedAt: new Date().toISOString(), checks, serviceWorkerEnabled: true, actualLaravel: true, isolatedSQLite: true, physicalAndroidInstallation: false, cameraFileInjection: true }, null, 2))
  console.log(`PWA runtime acceptance passed: ${checks.length} checks.`)
} catch (error) {
  if (browser) for (const context of browser.contexts()) for (const page of context.pages()) await page.screenshot({ path: resolve(output, 'failure.png'), animations: 'disabled' }).catch(() => {})
  writeFileSync(resolve(output, 'failure.txt'), String(error.stack || error))
  writeFileSync(resolve(output, 'progress.json'), JSON.stringify({ checks }, null, 2))
  console.error(String(error.message || error).split('\n').slice(0, 14).join('\n'))
  process.exitCode = 1
} finally {
  await browser?.close()
  if (gateway) { gateway.closeAllConnections(); await new Promise(resolve => gateway.close(resolve)) }
  if (backend.connected) backend.send('shutdown'); else backend.kill()
  await Promise.race([new Promise(resolve => backend.once('exit', resolve)), pause(5000)]); log.end()
}

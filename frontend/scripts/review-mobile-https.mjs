import { chromium } from '@playwright/test'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const frontend = resolve(dirname(fileURLToPath(import.meta.url)), '..'), root = resolve(frontend, '.cache/mobile-https')
const runtime = JSON.parse(readFileSync(resolve(root, 'status.json'), 'utf8')), access = JSON.parse(readFileSync(resolve(root, 'access.json'), 'utf8')), scenario = JSON.parse(readFileSync(resolve(root, 'scenario.json'), 'utf8'))
if (!runtime.running || !/^https:\/\/[a-z0-9-]+\.trycloudflare\.com$/.test(runtime.origin)) throw new Error('Start the isolated mobile HTTPS demo first.')
const output = resolve(root, 'review'); mkdirSync(output, { recursive: true })
const checks = [], browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' })
function check(value, description) { if (!value) throw new Error(description); checks.push(description) }
async function open(username) {
  const context = await browser.newContext({ baseURL: runtime.origin, viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, locale: 'vi-VN', timezoneId: 'Asia/Ho_Chi_Minh' })
  const page = await context.newPage()
  const cdp = await context.newCDPSession(page)
  await cdp.send('Emulation.setSafeAreaInsetsOverride', { insets: { top: 30, bottom: 34, left: 0, right: 0 } })
  await page.goto('/login')
  check(await page.evaluate(() => window.isSecureContext && location.protocol === 'https:'), 'Browser is a real HTTPS secure context')
  await page.evaluate(() => navigator.serviceWorker.ready)
  await page.waitForFunction(() => !!navigator.serviceWorker.controller)
  check(await page.evaluate(() => navigator.serviceWorker.controller.scriptURL === `${location.origin}/sw.js`), `${username} is controlled by the production worker over public HTTPS`)
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username)
  await page.getByLabel('Mật khẩu', { exact: true }).fill(access.accounts[username].password)
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await page.waitForURL(username === 'admin' ? '**/owner' : '**/tenant')
  await page.waitForFunction(() => document.querySelectorAll('.page-state--loading').length === 0)
  check(await page.locator('.page-state--error').count() === 0, `${username} home loads through the HTTPS gateway`)
  const cookies = await context.cookies()
  check(cookies.some(cookie => cookie.name === 'rental_mobile_demo_session' && cookie.secure && cookie.httpOnly && cookie.sameSite === 'Lax'), `${username} session cookie is Secure/HttpOnly/SameSite=Lax`)
  check(cookies.some(cookie => cookie.name === 'XSRF-TOKEN' && cookie.secure && !cookie.httpOnly), `${username} CSRF cookie is secure and available to the client`)
  check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${username} home fits the phone viewport`)
  for (const width of [320, 390, 430]) {
    await page.setViewportSize({ width, height: 844 })
    const install = page.getByRole('button', { name: 'Cài ứng dụng Nhà Trọ', exact: true })
    const rect = await install.boundingBox()
    check(rect && rect.x >= 0 && rect.x + rect.width <= width && rect.height >= 44 && await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${username} install action and home fit ${width}px`)
  }
  await page.setViewportSize({ width: 390, height: 844 })
  check(await page.evaluate(() => getComputedStyle(document.body).paddingTop === '30px' && getComputedStyle(document.body).paddingBottom === '34px'), `${username} respects simulated top and bottom safe areas`)
  await page.getByRole('button', { name: 'Mở menu', exact: true }).click()
  check(await page.evaluate(() => { const sidebar = document.querySelector('#app-navigation'), account = sidebar.querySelector('.sidebar-account'); return sidebar.getBoundingClientRect().bottom - account.getBoundingClientRect().bottom >= 34 }), `${username} mobile menu keeps the account above the gesture area`)
  await page.keyboard.press('Escape')
  await page.getByRole('button', { name: 'Cài ứng dụng Nhà Trọ', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Cài Nhà Trọ trên thiết bị' })
  await dialog.waitFor({ state: 'visible' })
  check(await dialog.evaluate(element => { const box = element.getBoundingClientRect(); return box.top >= 30 && box.bottom <= innerHeight - 34 }), `${username} installation dialog fits between the safe areas`)
  check(await dialog.getByText('Đây là địa chỉ thử tạm thời.', { exact: false }).isVisible(), `${username} sees the temporary installation address explanation`)
  await dialog.getByRole('button', { name: 'Android', exact: true }).click()
  const dialogTop = (await dialog.boundingBox()).y, headingTop = (await dialog.getByRole('heading', { name: 'Cài Nhà Trọ trên thiết bị', exact: true }).boundingBox()).y
  if (headingTop < dialogTop) throw new Error('Installation instructions scrolled the dialog title out of view.')
  if (username === 'admin') await page.screenshot({ path: resolve(output, 'pwa-install.png'), animations: 'disabled' })
  await dialog.getByRole('button', { name: 'Đóng hướng dẫn', exact: true }).click()
  await page.screenshot({ path: resolve(output, `${username}-home.png`), fullPage: true, animations: 'disabled' })
  return { context, page }
}
try {
  const { context: ownerContext, page: owner } = await open('admin')
  const { context: tenantContext, page: tenant } = await open('user')
  const manifestResponse = await owner.request.get('/manifest.webmanifest')
  const workerResponse = await owner.request.get('/sw.js')
  check(workerResponse.status() === 200 && workerResponse.headers()['cache-control'] === 'no-cache' && workerResponse.headers()['service-worker-allowed'] === '/', 'Public HTTPS serves the scoped worker with revalidation')
  check(manifestResponse.status() === 200 && manifestResponse.headers()['content-type'].includes('application/manifest+json') && manifestResponse.headers()['cache-control'] === 'no-cache', 'Public HTTPS manifest is available with revalidation and the correct MIME type')
  const manifest = await manifestResponse.json()
  check(manifest.name === 'Nhà Trọ' && manifest.start_url === '/' && manifest.scope === '/' && manifest.display === 'standalone', 'Public HTTPS installation opens the role-aware root in standalone mode')
  const appleIcon = await owner.locator('link[rel="apple-touch-icon"]').getAttribute('href')
  for (const icon of [...manifest.icons, { src: appleIcon, sizes: '180x180' }]) {
    const response = await owner.request.get(icon.src), data = await response.body(), size = Number(icon.sizes.split('x')[0])
    check(response.status() === 200 && response.headers()['content-type'] === 'image/png' && data.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10])) && data.readUInt32BE(16) === size && data.readUInt32BE(20) === size, `Public HTTPS delivers app icon ${icon.src} at the declared size`)
  }
  for (const [role, page] of [['owner', owner], ['tenant', tenant]]) {
    const invoiceResponse = await page.request.get(`/api/${role}/invoices/${scenario.single_invoice_id}`)
    check(invoiceResponse.status() === 200 && invoiceResponse.headers()['cache-control'].includes('no-store'), `${role} private invoice is available without caching`)
    const invoice = (await invoiceResponse.json()).data
    check(invoice.total_amount === '3080000.00' && invoice.paid_amount === '1000000.00', `${role} original synthetic invoice amounts match`)
    const pdf = await page.request.get(`/api/${role}/invoices/${scenario.single_invoice_id}/pdf`)
    check(pdf.status() === 200 && (await pdf.body()).subarray(0, 5).toString() === '%PDF-', `${role} PDF downloads through public HTTPS`)
    check(pdf.headers()['cache-control'].includes('no-store'), `${role} PDF forbids caching`)
    const report = (await (await page.request.get(`/api/${role}/maintenance-requests/${scenario.open_report_id}`)).json()).data
    check(report.photos.length > 0, `${role} sees the intended report photo`)
    const photo = await page.request.get(report.photos[0].url)
    check(photo.status() === 200 && String(photo.headers()['content-type']).startsWith('image/'), `${role} private photo loads over HTTPS`)
    check(photo.headers()['cache-control'].includes('no-store'), `${role} private photo forbids caching`)
  }
  check((await tenant.request.get('/api/owner/property')).status() === 403, 'Tenant cannot open owner property API')
  check((await tenant.request.get(`/api/tenant/invoices/${scenario.group_invoice_ids[0]}`)).status() === 404, 'Tenant cannot read an invoice without a grant')
  check((await owner.request.post('/api/auth/logout', { data: {} })).status() === 419, 'Missing CSRF token is rejected through HTTPS')
  check((await owner.request.get('/api/auth/me')).status() === 200, 'Rejected logout retains its live session')
  for (const path of ['/.env', '/backend/.env', '/.cache/mobile-https/access.json', '/src/main.ts', '/@fs/D:/KLTN/backend/.env']) check((await owner.request.get(path)).status() === 404, `Public gateway blocks ${path}`)
  const unknown = await owner.request.get('/api/missing-mobile-route')
  check(unknown.status() === 404 && String(unknown.headers()['content-type']).includes('application/json'), 'API 404 stays JSON and never becomes frontend HTML')
  for (const [role, page] of [['owner', owner], ['tenant', tenant]]) {
    check(await page.evaluate(async () => { const urls = (await Promise.all((await caches.keys()).map(async key => (await (await caches.open(key)).keys()).map(request => new URL(request.url).pathname)))).flat(); return urls.length > 100 && urls.every(path => /^\/(?:index\.html|favicon\.svg|assets\/|fonts\/|icons\/)/.test(path)) }), `${role} HTTPS Cache Storage contains only public build files after private API, photo and PDF use`)
  }
  await ownerContext.setOffline(true); await owner.reload()
  await owner.getByRole('heading', { name: 'Chưa kết nối được với máy chủ', exact: true }).waitFor()
  check(await owner.getByRole('main').count() > 0 && await owner.getByText('admin', { exact: true }).count() === 0, 'Cold offline opening over public HTTPS shows the public connection screen without the previous account')
  check(await owner.evaluate(async () => { try { await fetch('/api/owner/invoices/2/pdf'); return false } catch { return true } }), 'Public HTTPS private PDF cannot be recovered offline or replaced by HTML')
  await ownerContext.setOffline(false); await owner.getByRole('button', { name: 'Thử kết nối lại', exact: true }).click(); await owner.waitForURL('**/owner')
  check((await owner.request.get('/api/auth/me')).status() === 200, 'Public HTTPS session is revalidated on reconnection')
  await owner.getByRole('button', { name: 'Đăng xuất', exact: true }).click()
  await owner.waitForURL('**/login?reason=logged-out')
  check((await owner.request.get('/api/auth/me')).status() === 401, 'Owner logout revokes the session through HTTPS')
  check((await tenant.request.get('/api/auth/me')).status() === 200, 'Independent tenant session survives owner logout')
  await tenant.getByRole('button', { name: 'Đăng xuất', exact: true }).click()
  await tenant.waitForURL('**/login?reason=logged-out')
  check((await tenant.request.get('/api/auth/me')).status() === 401, 'Tenant logout works through HTTPS')
  const anonymous = await browser.newContext({ baseURL: runtime.origin })
  const guest = await anonymous.newPage()
  check((await guest.request.get(`/api/owner/invoices/${scenario.single_invoice_id}/pdf`)).status() === 401, 'Anonymous visitors cannot fetch private PDFs')
  await guest.request.get('/api/auth/csrf-cookie')
  const cookie = (await anonymous.cookies()).find(item => item.name === 'XSRF-TOKEN')
  const oldFixture = access.simple_demo_passwords === true ? { username: 'e2e_owner', password: 'E2e-Password-2077!' } : { username: 'admin', password: '123' }
  const weak = await guest.request.post('/api/auth/login', { data: oldFixture, headers: { 'X-XSRF-TOKEN': decodeURIComponent(cookie.value) } })
  check(weak.status() === 401, 'Unselected original source-fixture credentials are not usable publicly')
  await ownerContext.close(); await tenantContext.close(); await anonymous.close()
  writeFileSync(resolve(output, 'verification.json'), JSON.stringify({ checkedAt: new Date().toISOString(), origin: runtime.origin, checks, syntheticDataOnly: true, phoneViewportEmulation: true, physicalPhoneTest: false }, null, 2))
  process.stdout.write(`Public HTTPS mobile review passed: ${checks.length} checks.\n`)
} finally { await browser.close() }

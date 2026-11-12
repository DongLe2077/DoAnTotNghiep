import assert from 'node:assert/strict'
import { chromium, expect } from '@playwright/test'
import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { createMobileGateway } from './mobile-gateway.mjs'

const frontend = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const output = resolve(frontend, '.cache/pwa-install-review'); mkdirSync(output, { recursive: true })
let origin = ''
const gateway = createMobileGateway({ directory: resolve(frontend, 'dist'), backendPort: 1, getOrigin: () => origin })
await new Promise(resolve => gateway.listen(0, '127.0.0.1', resolve))
origin = `http://127.0.0.1:${gateway.address().port}`
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' })
const checks = []
function check(value, description) { assert.ok(value, description); checks.push(description) }
async function open({ width = 390, height = 844, ios = false, android = false, standalone = false } = {}) {
  const context = await browser.newContext({ baseURL: origin, viewport: { width, height }, locale: 'vi-VN', ...(ios ? { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1', isMobile: true, hasTouch: true } : android ? { userAgent: 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36', isMobile: true, hasTouch: true } : {}) })
  // Auth is deliberately unavailable in these isolated UI checks; real sessions are checked over HTTPS separately.
  await context.route('**/api/**', route => route.fulfill({ status: 401, contentType: 'application/json', body: '{"message":"Unauthenticated."}' }))
  if (standalone) await context.addInitScript(() => { Object.defineProperty(navigator, 'standalone', { value: true }) })
  const page = await context.newPage()
  await page.goto('/login')
  await expect(page.getByRole('heading', { name: 'Đăng nhập', exact: true })).toBeVisible()
  return { context, page }
}
async function prompt(page, outcome, rejects = false) {
  return page.evaluate(({ outcome, rejects }) => {
    const event = new Event('beforeinstallprompt', { cancelable: true })
    window.__installCalls = 0
    Object.defineProperty(event, 'prompt', { value: () => {
      window.__installCalls++
      return rejects ? Promise.reject(new Error('installation unavailable')) : Promise.resolve()
    } })
    Object.defineProperty(event, 'userChoice', { value: Promise.resolve({ outcome }) })
    return !window.dispatchEvent(event)
  }, { outcome, rejects })
}
let installabilityDiagnostics
try {
  const { context, page } = await open()
  const cdp = await context.newCDPSession(page)
  await cdp.send('Page.enable')
  const app = await cdp.send('Page.getAppManifest')
  check(app.url === `${origin}/manifest.webmanifest` && app.errors.length === 0, 'Chrome parses the linked web manifest without errors')
  const manifest = JSON.parse(app.data)
  check(manifest.name === 'Nhà Trọ' && manifest.short_name === 'Nhà Trọ' && manifest.lang === 'vi', 'Manifest uses the Vietnamese app identity')
  check(manifest.id === '/' && manifest.scope === '/' && manifest.start_url === '/' && manifest.display === 'standalone', 'Installation starts at the role-aware root in standalone mode')
  const response = await page.request.get('/manifest.webmanifest')
  check(response.status() === 200 && response.headers()['cache-control'] === 'no-cache' && response.headers()['content-type'].includes('application/manifest+json'), 'Manifest is public, correctly typed and revalidated')
  check(await page.locator('meta[name="theme-color"]').getAttribute('content') === manifest.theme_color, 'Browser theme matches the manifest')
  check(await page.locator('meta[name="apple-mobile-web-app-title"]').getAttribute('content') === manifest.name, 'Apple app metadata matches the manifest')
  const appleIcon = await page.locator('link[rel="apple-touch-icon"]').getAttribute('href')
  check(appleIcon === '/icons/apple-touch-icon.png?v=rentbase', 'Apple has a dedicated touch icon')
  check(await page.locator('link[rel="icon"]').getAttribute('href') === '/icons/rentbase-mark.svg', 'Browser favicon uses the RentBase mark')
  const background = manifest.theme_color.slice(1).match(/../g).map(value => Number.parseInt(value, 16))
  for (const icon of [...manifest.icons, { src: appleIcon, sizes: '180x180', purpose: 'apple' }]) {
    const image = await page.request.get(icon.src), bytes = await image.body(), size = Number(icon.sizes.split('x')[0])
    check(image.status() === 200 && image.headers()['content-type'] === 'image/png' && bytes.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10])) && bytes.readUInt32BE(16) === size && bytes.readUInt32BE(20) === size, `${icon.purpose} icon ${icon.sizes} is a real, correctly sized PNG`)
    if (icon.purpose === 'maskable') {
      const safe = await page.evaluate(async ({ src, size, background }) => {
        const image = new Image(); image.src = src; await image.decode()
        const canvas = document.createElement('canvas'); canvas.width = canvas.height = size
        const ctx = canvas.getContext('2d'); ctx.drawImage(image, 0, 0)
        const data = ctx.getImageData(0, 0, size, size).data
        for (let y = 0; y < size; y++) for (let x = 0; x < size; x++) {
          const offset = (y * size + x) * 4
          if (data[offset + 3] !== 255) return false
          if (background.some((value, index) => data[offset + index] !== value) && Math.hypot(x + .5 - size / 2, y + .5 - size / 2) > size * .4) return false
        }
        return true
      }, { src: icon.src, size, background })
      check(safe, `Maskable ${icon.sizes} is opaque and the house fits inside the safe circle`)
    }
  }
  installabilityDiagnostics = await cdp.send('Page.getInstallabilityErrors')
  const trigger = page.getByRole('button', { name: 'Cài ứng dụng Nhà Trọ', exact: true })
  await trigger.click()
  const dialog = page.getByRole('dialog', { name: 'Cài Nhà Trọ trên thiết bị' })
  await expect(dialog).toBeVisible()
  for (const [device, text] of [['Android', 'Chrome'], ['iPhone / iPad', 'Safari'], ['Máy tính', 'Edge']]) {
    await dialog.getByRole('button', { name: device, exact: true }).click()
    await expect(dialog.locator('ol')).toContainText(text)
    check(true, `${device} instructions can be selected without a native prompt`)
  }
  await page.keyboard.press('Escape')
  await expect(dialog).not.toBeVisible(); await expect(trigger).toBeFocused()
  check(true, 'Escape closes instructions and restores focus to the install button')
  check(await prompt(page, 'dismissed'), 'Deferred browser prompt is intercepted without opening a dialog automatically')
  await expect(dialog).not.toBeVisible()
  await trigger.click(); await dialog.getByRole('button', { name: 'Cài trên thiết bị này', exact: true }).click()
  await expect(dialog.getByRole('status')).toContainText('để việc cài lại sau')
  check(await page.evaluate(() => window.__installCalls === 1), 'A dismissed native prompt is invoked once and not replayed')
  await expect(dialog.getByRole('button', { name: 'Cài trên thiết bị này', exact: true })).toHaveCount(0)
  await prompt(page, 'accepted', true)
  await dialog.getByRole('button', { name: 'Cài trên thiết bị này', exact: true }).click()
  await expect(dialog.getByRole('alert')).toContainText('menu trình duyệt')
  check(true, 'Native prompt failure keeps manual installation guidance available')
  await prompt(page, 'accepted')
  await dialog.getByRole('button', { name: 'Cài trên thiết bị này', exact: true }).click()
  await expect(dialog.getByRole('status')).toContainText('Đã gửi yêu cầu cài')
  check(await trigger.count() === 1, 'Accepting a prompt does not falsely claim that installation has finished')
  await page.evaluate(() => window.dispatchEvent(new Event('appinstalled')))
  await expect(trigger).toHaveCount(0); await expect(dialog).not.toBeVisible()
  check(true, 'Confirmed app installation closes instructions and removes the install action')
  await page.evaluate(() => navigator.serviceWorker.ready)
  check(await page.evaluate(async () => { const keys = await caches.keys(); const urls = (await Promise.all(keys.map(async key => (await (await caches.open(key)).keys()).map(request => new URL(request.url).pathname)))).flat(); return keys.length > 0 && urls.length > 0 && urls.every(url => /^\/(?:index\.html|favicon\.svg|assets\/|fonts\/|icons\/)/.test(url)) }), 'Service worker stores only public build assets, with no API or private responses')
  await context.close()

  for (const [width, height] of [[320, 568], [390, 844], [430, 932], [960, 480], [1280, 720]]) {
    const { context, page } = await open({ width, height, android: true })
    const trigger = page.getByRole('button', { name: 'Cài ứng dụng Nhà Trọ', exact: true })
    await trigger.click()
    const dialog = page.getByRole('dialog'), content = dialog.locator('.content')
    await expect(dialog.getByRole('button', { name: 'Android', exact: true })).toHaveAttribute('aria-pressed', 'true')
    const fits = await dialog.evaluate(dialog => {
      const rect = dialog.getBoundingClientRect(), footer = dialog.querySelector('footer').getBoundingClientRect(), content = dialog.querySelector('.content')
      return document.documentElement.scrollWidth <= innerWidth + 1 && dialog.scrollWidth <= dialog.clientWidth + 1 && rect.left >= 0 && rect.right <= innerWidth + 1 && rect.bottom <= innerHeight + 1 && footer.bottom <= rect.bottom && footer.top >= rect.top && content.clientHeight > 24
    })
    check(fits, `Android instructions fit ${width}x${height} with an accessible close action`)
    await content.hover(); await page.mouse.wheel(0, 1800)
    await expect.poll(() => content.evaluate(element => element.scrollTop)).toBeGreaterThan(0)
    await dialog.getByRole('button', { name: 'Đóng hướng dẫn', exact: true }).click()
    await expect(dialog).not.toBeVisible()
    await trigger.click()
    await expect.poll(() => content.evaluate(element => element.scrollTop)).toBe(0)
    check(true, `Reopening Android instructions starts at the first step at ${width}x${height}`)
    if (width === 390 || width === 960) await page.screenshot({ path: resolve(output, `android-install-${width}.png`), animations: 'disabled' })
    await context.close()
  }

  for (const width of [320, 390, 430]) {
    const { context, page } = await open({ width, ios: true })
    await page.getByRole('button', { name: 'Cài ứng dụng Nhà Trọ', exact: true }).click()
    const dialog = page.getByRole('dialog')
    await expect(dialog.getByRole('button', { name: 'iPhone / iPad', exact: true })).toHaveAttribute('aria-pressed', 'true')
    const fits = await page.evaluate(() => { const dialog = document.querySelector('dialog[open]'), rect = dialog.getBoundingClientRect(); return document.documentElement.scrollWidth <= innerWidth + 1 && dialog.scrollWidth <= dialog.clientWidth + 1 && rect.left >= 0 && rect.right <= innerWidth && rect.bottom <= innerHeight })
    check(fits, `Login and iPhone instructions fit ${width}px with a scrollable dialog`)
    if (width === 390) await page.screenshot({ path: resolve(output, 'iphone-install.png'), animations: 'disabled' })
    await context.close()
  }
  const standalone = await open({ standalone: true })
  await expect(standalone.page.getByRole('button', { name: 'Cài ứng dụng Nhà Trọ', exact: true })).toHaveCount(0)
  check(true, 'An app already opened in standalone mode does not offer installation')
  await standalone.context.close()
  // Playwright's ordinary contexts are private windows, which Chromium cannot install from.
  // Check browser eligibility again in an ordinary persistent profile, not by ignoring that error.
  const regular = await chromium.launchPersistentContext(resolve(output, 'browser-profile'), { channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome', headless: true, baseURL: origin })
  try {
    await regular.route('**/api/**', route => route.fulfill({ status: 401, contentType: 'application/json', body: '{"message":"Unauthenticated."}' }))
    const page = await regular.newPage(); await page.goto('/login')
    await expect(page.getByRole('heading', { name: 'Đăng nhập', exact: true })).toBeVisible()
    const session = await regular.newCDPSession(page); await session.send('Page.enable')
    installabilityDiagnostics = await session.send('Page.getInstallabilityErrors')
    check(installabilityDiagnostics.installabilityErrors.length === 0, `Chrome normal profile reports no installation blockers: ${JSON.stringify(installabilityDiagnostics)}`)
  } finally { await regular.close() }
  writeFileSync(resolve(output, 'verification.json'), JSON.stringify({ checkedAt: new Date().toISOString(), checks, installabilityDiagnostics, mockedNativePromptForLifecycleChecks: true, physicalPhoneInstallation: false }, null, 2))
  process.stdout.write(`PWA installation review passed: ${checks.length} checks. Chrome diagnostics: ${JSON.stringify(installabilityDiagnostics)}\n`)
} catch (error) {
  for (const context of browser.contexts()) for (const page of context.pages()) await page.screenshot({ path: resolve(output, 'failure.png'), animations: 'disabled' }).catch(() => {})
  throw error
} finally {
  await browser.close()
  gateway.closeAllConnections(); await new Promise(resolve => gateway.close(resolve))
}

import { chromium } from '@playwright/test'
import { cpSync, mkdirSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const root = process.cwd(), runtime = JSON.parse(readFileSync(resolve(root, '.cache/mobile-https/status.json'), 'utf8'))
if (!runtime.running || !/^https:\/\/[a-z0-9-]+\.trycloudflare\.com$/.test(runtime.origin)) throw new Error('Start the mobile HTTPS demo first.')
const output = resolve(root, 'docs/images/mobile'); mkdirSync(output, { recursive: true })
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' })
try {
  const context = await browser.newContext({ baseURL: runtime.origin, viewport: { width: 393, height: 851 }, isMobile: true, hasTouch: true, locale: 'vi-VN' })
  const page = await context.newPage(); await page.goto('/login')
  await page.evaluate(() => navigator.serviceWorker.ready); await page.waitForFunction(() => !!navigator.serviceWorker.controller)
  const ready = page.getByRole('button', { name: 'Đóng thông báo sẵn sàng', exact: true }); if (await ready.count()) await ready.click()
  await page.screenshot({ path: resolve(output, '01-login-install.png'), animations: 'disabled' })
  await page.getByRole('button', { name: 'Cài ứng dụng Nhà Trọ', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Cài Nhà Trọ trên thiết bị', exact: true })
  await dialog.getByRole('button', { name: 'Android', exact: true }).click()
  await dialog.locator('.content').evaluate(element => { element.scrollTop = 0 })
  await page.screenshot({ path: resolve(output, '02-android-instructions.png'), animations: 'disabled' })
  cpSync(resolve(root, '.cache/pwa-runtime-review/cold-offline.png'), resolve(output, '03-offline.png'))
  cpSync(resolve(root, '.cache/pwa-runtime-review/update-ready.png'), resolve(output, '04-update.png'))
  console.log('Captured four guide images from the app; no physical phone installation is claimed.')
} finally { await browser.close() }

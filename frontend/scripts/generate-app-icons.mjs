import { chromium } from '@playwright/test'
import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../public/icons')
const appIcon = readFileSync(resolve(root, 'app-icon.svg'), 'utf8')
// The maskable source fills its canvas. Browser icons use the same mark on a tighter, rounded tile.
const mark = appIcon.replace('viewBox="0 0 512 512"', 'viewBox="40 40 432 432"')
  .replace('<rect width="512" height="512"', '<rect x="40" y="40" width="432" height="432" rx="104"')
writeFileSync(resolve(root, 'rentbase-mark.svg'), mark)
writeFileSync(resolve(root, '../favicon.svg'), mark)
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' })
try {
  const page = await browser.newPage()
  for (const [name, size] of [['icon-192', 192], ['icon-512', 512], ['maskable-192', 192], ['maskable-512', 512], ['apple-touch-icon', 180]]) {
    const artwork = name.startsWith('icon-') ? mark : appIcon
    const source = `data:image/svg+xml;base64,${Buffer.from(artwork).toString('base64')}`
    const data = await page.evaluate(async ({ source, size }) => {
      const image = new Image(); image.src = source; await image.decode()
      const canvas = document.createElement('canvas'); canvas.width = size; canvas.height = size
      canvas.getContext('2d').drawImage(image, 0, 0, size, size)
      return canvas.toDataURL('image/png').split(',')[1]
    }, { source, size })
    writeFileSync(resolve(root, `${name}.png`), Buffer.from(data, 'base64'))
  }
  process.stdout.write('Generated the web mark, favicon and five app icons.\n')
} finally { await browser.close() }

import { chromium } from '@playwright/test'
import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

// Extend the existing house mark. All artwork fits the maskable icon's central 80% circle.
const root = resolve(dirname(fileURLToPath(import.meta.url)), '../public/icons')
const source = `data:image/svg+xml;base64,${readFileSync(resolve(root, 'app-icon.svg')).toString('base64')}`
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' })
try {
  const page = await browser.newPage()
  for (const [name, size] of [['icon-192', 192], ['icon-512', 512], ['maskable-192', 192], ['maskable-512', 512], ['apple-touch-icon', 180]]) {
    const data = await page.evaluate(async ({ source, size }) => {
      const image = new Image(); image.src = source; await image.decode()
      const canvas = document.createElement('canvas'); canvas.width = size; canvas.height = size
      canvas.getContext('2d').drawImage(image, 0, 0, size, size)
      return canvas.toDataURL('image/png').split(',')[1]
    }, { source, size })
    writeFileSync(resolve(root, `${name}.png`), Buffer.from(data, 'base64'))
  }
  process.stdout.write('Generated five local PNG app icons.\n')
} finally { await browser.close() }

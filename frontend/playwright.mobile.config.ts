import { defineConfig, devices } from '@playwright/test'
import base from './playwright.config'

// Desktop Chrome with mobile emulation, not an Android-device acceptance run.
const criticalFlows = [
  'owner signs in with real cookies', 'tenant reaches their own area',
  'wrong password stays on login', 'mobile login and navigation',
  'CT1 rooms', 'CT1 service prices', 'CT2 creates a profile',
  'CT3 single-person', 'CT3 checks in two people',
  'CT4 records edits', 'CT5 payment lost response',
  'CT6 reviews money', 'CT6 retries the identical durable creation',
  'CT7 handles private photos', 'CT8 dashboard distinguishes',
  'CT8 interactive invoice', 'CT5 receivables offer',
  'NT1 shows own', 'NT2 follows grants', 'NT2 payOS return', 'NT3 lost photo response',
  'NT3 validates local files', 'NT tenant privacy',
  'invoice adjustment lost response', 'owner can cancel and restore',
  'overdue filter and grouped', 'issuing a new invoice',
  'bulk meters require', 'lost committed row response',
  'voiding a mistaken receipt', 'new expense requires explicit linking',
  'owner and authorized tenant download actual',
  'owner previews all room members', 'owner and author see real update history',
  'forbidden page explains', 'owner pages and low-height', 'tenant pages retain',
  'header stays reachable', 'month control and update', 'tenant header and bottom',
  'pages fit tablet and desktop',
]

const reportRoot = process.env.MOBILE_REVIEW_OUTPUT ?? '.cache/mobile-review'
process.env.E2E_RESPONSIVE_SCREENSHOTS = `${reportRoot}/screenshots/responsive`

export default defineConfig({
  ...base,
  testMatch: '**/*.spec.ts',
  grep: new RegExp(criticalFlows.join('|')),
  maxFailures: 4,
  outputDir: `${reportRoot}/test-results`,
  reporter: [
    ['list'],
    ['html', { outputFolder: `${reportRoot}/report`, open: 'never' }],
    ['json', { outputFile: `${reportRoot}/results.json` }],
  ],
  projects: [{
    name: 'chrome-mobile',
    use: { ...devices['Pixel 7'], channel: 'chrome', viewport: { width: 390, height: 844 }, deviceScaleFactor: 1 },
  }],
})

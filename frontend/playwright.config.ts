import { defineConfig, devices } from '@playwright/test'

const frontendPort = Number(process.env.E2E_FRONTEND_PORT ?? 15173)
const backendPort = Number(process.env.E2E_API_PORT ?? 18000)
const frontendUrl = `http://127.0.0.1:${frontendPort}`
const backendUrl = `http://127.0.0.1:${backendPort}`

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 30_000,
  expect: { timeout: 8_000 },
  outputDir: '.cache/e2e/test-results',
  reporter: [['list'], ['html', { outputFolder: '.cache/e2e/report', open: 'never' }]],
  use: {
    baseURL: frontendUrl,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'], channel: process.env.PLAYWRIGHT_CHANNEL } },
  ],
  webServer: [
    {
      command: 'node tests/e2e/support/serve-backend.mjs',
      url: `${backendUrl}/up`,
      timeout: 30_000,
      reuseExistingServer: false,
      env: { E2E_API_PORT: String(backendPort), E2E_FRONTEND_PORT: String(frontendPort) },
    },
    {
      command: `npm run dev -- --host 127.0.0.1 --port ${frontendPort} --strictPort`,
      url: frontendUrl,
      timeout: 30_000,
      reuseExistingServer: false,
      env: { API_PROXY_TARGET: backendUrl },
    },
  ],
})

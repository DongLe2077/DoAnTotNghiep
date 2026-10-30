import { chromium } from '@playwright/test'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const frontend = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const output = resolve(frontend, '.cache/presentation-review')
mkdirSync(output, { recursive: true })
const scenario = JSON.parse(readFileSync(resolve(frontend, '.cache/presentation/scenario.json'), 'utf8'))
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' })
const evidence = { checkedAt: new Date().toISOString(), scenario, checks: [], screenshots: [] }
function check(condition, name) { if (!condition) throw new Error(name); evidence.checks.push(name) }
async function open(username, viewport = { width: 1440, height: 1000 }) {
  const context = await browser.newContext({ viewport, baseURL: 'http://127.0.0.1:5175' })
  const page = await context.newPage()
  await page.goto('/login')
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username)
  await page.getByLabel('Mật khẩu', { exact: true }).fill('123')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await page.waitForURL(username === 'admin' ? '**/owner' : '**/tenant')
  return { context, page }
}
async function shot(page, path, name) {
  await page.goto(path)
  await page.locator('#main-content h1').first().waitFor()
  await page.waitForFunction(() => document.querySelectorAll('.page-state--loading').length === 0)
  check(await page.locator('.page-state--error').count() === 0, `No load error: ${path}`)
  await page.evaluate(() => document.fonts.ready)
  check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `Fits screen: ${name}`)
  await page.screenshot({ path: resolve(output, `${name}.png`), fullPage: true, animations: 'disabled' })
  evidence.screenshots.push(`${name}.png`)
}
try {
  const { context: ownerContext, page: owner } = await open('admin')
  await shot(owner, '/owner', 'owner-dashboard')
  await shot(owner, '/owner/property', 'owner-property')
  await shot(owner, `/owner/invoices/${scenario.group_invoice_ids[1]}`, 'owner-partial-overdue')
  const invoice = (await (await owner.request.get(`/api/owner/invoices/${scenario.group_invoice_ids[1]}`)).json()).data
  check(invoice.total_amount === scenario.group_total, 'Invoice total matches the scenario')
  check(invoice.paid_amount === '500000.00', 'Group partial payment retained')
  check(invoice.outstanding_amount === '2580000.00', 'Group remaining debt matches')
  const pdf = await owner.request.get(`/api/owner/invoices/${invoice.id}/pdf`)
  check(pdf.status() === 200 && (await pdf.body()).subarray(0, 5).toString() === '%PDF-', 'Owner PDF from presentation data')
  await shot(owner, `/owner/maintenance/${scenario.resolved_report_id}`, 'owner-repair-history-cost')
  const report = (await (await owner.request.get(`/api/owner/maintenance-requests/${scenario.resolved_report_id}`)).json()).data
  check(report.status === 'resolved' && report.photos.length > 0, 'Resolved repair has its photo')
  const photoPath = `/api/owner/maintenance-requests/${report.id}/photos/${report.photos[0].id}`
  const photo = await owner.request.get(photoPath)
  check(photo.status() === 200 && String(photo.headers()['content-type']).startsWith('image/'), 'Private photo opens')
  await shot(owner, `/owner/meters/bulk`, 'owner-bulk-meters')
  await owner.setViewportSize({ width: 390, height: 844 })
  await shot(owner, '/owner', 'owner-mobile')
  const { context: tenantContext, page: tenant } = await open('user', { width: 390, height: 844 })
  await shot(tenant, '/tenant', 'tenant-room-mobile')
  await shot(tenant, `/tenant/invoices/${scenario.single_invoice_id}`, 'tenant-invoice-mobile')
  const tenantPdf = await tenant.request.get(`/api/tenant/invoices/${scenario.single_invoice_id}/pdf`)
  check(tenantPdf.status() === 200 && (await tenantPdf.body()).subarray(0, 5).toString() === '%PDF-', 'Authorized tenant PDF')
  check((await tenant.request.get('/api/owner/property')).status() === 403, 'Tenant cannot edit property')
  await shot(tenant, `/tenant/maintenance/${scenario.open_report_id}`, 'tenant-open-repair-mobile')
  const { context: memberContext, page: member } = await open('thanhvien_demo', { width: 390, height: 844 })
  await shot(member, '/tenant', 'tenant-member-mobile')
  check((await member.request.get(`/api/tenant/invoices/${scenario.group_invoice_ids[1]}`)).status() === 404, 'Ordinary member has no implicit invoice grant')
  const eligible = (await (await member.request.get('/api/tenant/maintenance-requests/eligible-occupancies')).json()).data
  check(eligible.length === 0, 'Only the representative can submit a report')
  await ownerContext.close(); await tenantContext.close(); await memberContext.close()
  writeFileSync(resolve(output, 'review.json'), JSON.stringify(evidence, null, 2))
  process.stdout.write(`Presentation review passed: ${evidence.checks.length} checks, ${evidence.screenshots.length} screenshots.\n${output}\n`)
} finally { await browser.close() }

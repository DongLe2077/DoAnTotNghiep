import { chromium } from '@playwright/test'
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

// Writes exclusively through the isolated presentation server; restore its checkpoint after rehearsal.
const frontend = resolve(dirname(fileURLToPath(import.meta.url)), '..'), output = resolve(frontend, '.cache/presentation-review')
const scenario = JSON.parse(readFileSync(resolve(frontend, '.cache/presentation/scenario.json'), 'utf8'))
mkdirSync(output, { recursive: true })
const browser = await chromium.launch({ channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' })
const checks = []
function check(value, name) { if (!value) throw new Error(name); checks.push(name) }
async function login(username) {
  const context = await browser.newContext({ baseURL: 'http://127.0.0.1:5175' }), page = await context.newPage()
  await page.goto('/login')
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill(username)
  await page.getByLabel('Mật khẩu', { exact: true }).fill('123')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await page.waitForURL(username === 'admin' ? '**/owner' : '**/tenant')
  check(await page.getByText(/Bản trình đánh giá · Dữ liệu giả riêng/).count() === 1, 'Presentation banner confirmed before writes')
  return { context, page }
}
async function write(page, path, data, method = 'POST') {
  await page.request.get('/api/auth/csrf-cookie')
  const token = (await page.context().cookies()).find(item => item.name === 'XSRF-TOKEN')
  const response = await page.request.fetch(`/api${path}`, { method, data, headers: { 'X-XSRF-TOKEN': decodeURIComponent(token.value), Accept: 'application/json' } })
  check(response.ok(), `Write succeeds: ${path}`)
  return response.json()
}
try {
  const { context: ownerContext, page: owner } = await login('admin')
  const { context: tenantContext, page: tenant } = await login('user')
  const initial = (await (await owner.request.get(`/api/owner/invoices/${scenario.single_invoice_id}`)).json()).data
  check(initial.paid_amount === '1000000.00', 'Initial baseline payment; restore before repeating this rehearsal')
  await owner.goto(`/owner/invoices/${scenario.single_invoice_id}`)
  await owner.getByRole('button', { name: 'Ghi nhận thu tiền', exact: true }).click()
  const dialog = owner.getByRole('dialog', { name: 'Ghi nhận tiền đã thu', exact: true })
  await dialog.getByLabel(/^Số tiền thực nhận/).fill('100000')
  await dialog.getByRole('button', { name: 'Xem lại lần thu', exact: true }).click()
  const received = owner.waitForResponse(response => response.request().method() === 'POST' && response.url().endsWith(`/invoices/${scenario.single_invoice_id}/payments`))
  await dialog.getByRole('button', { name: 'Xác nhận đã nhận tiền', exact: true }).click()
  const response = await received, payload = response.request().postDataJSON()
  check(response.status() === 201, 'UI records one additional receipt')
  const replay = await write(owner, `/owner/invoices/${scenario.single_invoice_id}/payments`, payload)
  check(replay.replayed === true, 'Same confirmed receipt replay does not duplicate it')
  const tenantInvoice = (await (await tenant.request.get(`/api/tenant/invoices/${scenario.single_invoice_id}`)).json()).data
  check(tenantInvoice.paid_amount === '1100000.00' && tenantInvoice.outstanding_amount === '1980000.00', 'Tenant sees matching received amount and remaining debt')
  await tenant.goto(`/tenant/invoices/${scenario.single_invoice_id}`)
  await tenant.locator('.page-state--loading').waitFor({ state: 'detached' })
  const pdf = await tenant.request.get(`/api/tenant/invoices/${scenario.single_invoice_id}/pdf`)
  check(pdf.status() === 200 && (await pdf.body()).subarray(0, 5).toString() === '%PDF-', 'Tenant PDF after the new payment')
  const preview = (await write(owner, `/owner/occupancies/${scenario.ready_occupancy_id}/invoices/preview`, { billing_month: scenario.ready_billing_month })).data
  check(preview.can_issue && preview.total_amount === scenario.group_total, 'Ready room invoice preview agrees with the fixture')
  const issued = (await write(owner, `/owner/occupancies/${scenario.ready_occupancy_id}/invoices`, { billing_month: scenario.ready_billing_month, preview_token: preview.preview_token })).data
  check(issued.room.room_number === scenario.ready_room, 'Invoice is issued for the intended room')
  const report = (await (await owner.request.get(`/api/owner/maintenance-requests/${scenario.open_report_id}`)).json()).data
  await write(owner, `/owner/maintenance-requests/${report.id}`, { status: 'in_progress', resolution: 'Đã tiếp nhận trong lượt tập trình sản phẩm.', version_token: report.version_token }, 'PATCH')
  const tenantReport = (await (await tenant.request.get(`/api/tenant/maintenance-requests/${report.id}`)).json()).data
  check(tenantReport.status === 'in_progress' && tenantReport.photos.length > 0, 'Tenant sees the real processing update and retained photo')
  const history = (await (await tenant.request.get(`/api/tenant/maintenance-requests/${report.id}/history`)).json()).data
  check(history.some(item => item.after.status === 'in_progress'), 'Processing event is retained in tenant history')
  writeFileSync(resolve(output, 'rehearsal.json'), JSON.stringify({ checkedAt: new Date().toISOString(), checks, invoiceId: issued.id, dataChanged: true, restoreCheckpointAfterUse: true }, null, 2))
  await ownerContext.close(); await tenantContext.close()
  process.stdout.write(`Presentation rehearsal passed: ${checks.length} checks. Restore the checkpoint before presenting the original figures.\n`)
} finally { await browser.close() }

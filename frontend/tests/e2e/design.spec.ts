import { expect, test, type Locator, type Page } from '@playwright/test'
import { mkdirSync } from 'node:fs'
import { resolve } from 'node:path'

type PreviewTraffic = { api: string[]; external: string[]; fonts: string[]; errors: string[] }
const traffic = new WeakMap<Page, PreviewTraffic>()
const screenshotDirectory = resolve(process.cwd(), '.cache/design/screenshots')
mkdirSync(screenshotDirectory, { recursive: true })

test.beforeEach(async ({ page, baseURL }) => {
  const requests: PreviewTraffic = { api: [], external: [], fonts: [], errors: [] }
  const origin = new URL(baseURL!).origin
  traffic.set(page, requests)

  // The public preview must remain usable without an account or a running API.
  await page.route('**/*', async route => {
    const url = new URL(route.request().url())
    if (url.pathname.startsWith('/api/')) {
      requests.api.push(url.pathname)
      await route.abort()
    } else if (url.protocol.startsWith('http') && url.origin !== origin) {
      requests.external.push(url.href)
      await route.abort()
    } else {
      await route.continue()
    }
  })
  page.on('response', response => {
    if (response.url().includes('/fonts/be-vietnam-pro/') && response.ok()) requests.fonts.push(response.url())
  })
  page.on('pageerror', error => requests.errors.push(error.message))
})

test.afterEach(async ({ page }) => {
  expect(traffic.get(page)?.api, 'A design preview must not request application data').toEqual([])
  expect(traffic.get(page)?.external, 'Fonts and design assets must be served locally').toEqual([])
  expect(traffic.get(page)?.errors, 'The preview must render without browser errors').toEqual([])
})

async function expectNoHorizontalOverflow(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true)
}

async function screenshot(page: Page, name: string) {
  await page.evaluate(() => document.fonts.ready)
  await page.screenshot({ path: resolve(screenshotDirectory, `${name}.png`), fullPage: true })
}

async function expectDialogFocusWrap(page: Page, dialog: Locator, firstLabel: string, lastLabel: string) {
  const first = dialog.getByRole('button', { name: firstLabel, exact: true })
  const last = dialog.getByRole('button', { name: lastLabel, exact: true })
  await first.focus()
  await page.keyboard.press('Shift+Tab')
  await expect(last).toBeFocused()
  await page.keyboard.press('Tab')
  await expect(first).toBeFocused()
}

test('public guide loads local Vietnamese fonts and gives accessible form and keyboard feedback', async ({ page }) => {
  await page.goto('/design')
  await expect(page).toHaveURL(/\/design$/)
  await expect(page.getByRole('heading', { name: 'Be Vietnam Pro', exact: true })).toBeVisible()
  await page.evaluate(() => document.fonts.ready)
  expect(await page.evaluate(() => getComputedStyle(document.body).fontFamily)).toContain('Be Vietnam Pro')
  expect(await page.evaluate(() => document.fonts.check('400 16px "Be Vietnam Pro"', 'Nhà trọ Ă Â Ê Ô Ơ Ư'))).toBe(true)
  expect(traffic.get(page)!.fonts.length).toBeGreaterThan(0)

  const roomName = page.getByRole('textbox', { name: /^Tên phòng/ })
  await page.getByRole('button', { name: 'Kiểm tra biểu mẫu', exact: true }).click()
  await expect(roomName).toHaveAttribute('aria-invalid', 'true')
  await expect(roomName).toHaveAttribute('aria-describedby', 'design-room-name-error')
  await expect(page.getByRole('alert')).toHaveText('Vui lòng nhập tên phòng trước khi tiếp tục.')
  await roomName.fill('A203')
  await expect(roomName).toHaveAttribute('aria-invalid', 'false')
  await expect(roomName).toHaveAttribute('aria-describedby', 'design-room-name-hint')
  await page.getByRole('button', { name: 'Kiểm tra biểu mẫu', exact: true }).click()
  await expect(page.getByText('Biểu mẫu hợp lệ. Đây là thao tác thử, thông tin chưa được lưu vào hệ thống.', { exact: true })).toBeVisible()

  const trigger = page.getByRole('button', { name: 'Lưu thay đổi', exact: true })
  await trigger.focus()
  await page.keyboard.press('Enter')
  const dialog = page.getByRole('dialog', { name: 'Kiểm tra trước khi lưu', exact: true })
  await expect(dialog).toBeVisible()
  await expect(dialog).toHaveAttribute('aria-describedby', /\S+/)
  await expect(dialog).toContainText('Đây là bản mẫu. Chưa có thông tin nào được lưu.')
  await expect(dialog.getByRole('button', { name: 'Đóng hộp thoại', exact: true })).toBeFocused()
  await expectDialogFocusWrap(page, dialog, 'Đóng hộp thoại', 'Đóng bản mẫu')
  await page.keyboard.press('Escape')
  await expect(dialog).not.toBeVisible()
  await expect(trigger).toBeFocused()
})

test('owner preview filters rooms, restores detail focus, and checks a form without creating data', async ({ page }) => {
  await page.goto('/design?view=owner')
  await expect(page.getByRole('heading', { name: 'Quản lý phòng', exact: true })).toBeVisible()
  const detailButtons = page.getByRole('button', { name: /^Xem chi tiết phòng / })
  await expect(detailButtons).toHaveCount(4)
  const search = page.getByLabel('Tìm phòng theo tên hoặc tầng', { exact: true })
  const status = page.getByLabel('Trạng thái phòng', { exact: true })
  await status.selectOption('available')
  await expect(detailButtons).toHaveCount(1)
  await expect(page.getByRole('button', { name: 'Xem chi tiết phòng A201', exact: true })).toBeVisible()
  await search.fill('không-có-phòng-này')
  await expect(page.getByRole('heading', { name: 'Chưa tìm thấy phòng phù hợp', exact: true })).toBeVisible()
  await expect(detailButtons).toHaveCount(0)
  await page.getByRole('button', { name: 'Xem tất cả phòng', exact: true }).click()
  await expect(search).toHaveValue('')
  await expect(status).toHaveValue('')
  await expect(detailButtons).toHaveCount(4)
  await search.fill('Tầng 1')
  await expect(detailButtons).toHaveCount(2)
  await page.getByRole('button', { name: 'Xóa bộ lọc', exact: true }).click()

  const detailTrigger = page.getByRole('button', { name: 'Xem chi tiết phòng A101', exact: true })
  await detailTrigger.click()
  const detail = page.getByRole('dialog', { name: 'Phòng A101', exact: true })
  await expect(detail).toBeVisible()
  await expect(detail).toContainText('Giá thuê toàn phòng / tháng')
  await expect(detail).toContainText('3.200.000 ₫')
  await expectDialogFocusWrap(page, detail, 'Đóng chi tiết phòng', 'Đóng chi tiết')
  await page.keyboard.press('Escape')
  await expect(detail).not.toBeVisible()
  await expect(detailTrigger).toBeFocused()

  await page.getByRole('button', { name: 'Thêm phòng', exact: true }).click()
  const form = page.getByRole('dialog', { name: 'Thêm phòng', exact: true })
  const name = form.getByLabel(/^Tên phòng/)
  await expect(name).toBeFocused()
  await expectDialogFocusWrap(page, form, 'Đóng biểu mẫu thêm phòng', 'Kiểm tra biểu mẫu')
  await form.getByRole('button', { name: 'Kiểm tra biểu mẫu', exact: true }).click()
  expect(await name.evaluate(element => (element as HTMLInputElement).validity.valueMissing)).toBe(true)
  await name.fill('A999')
  await form.getByLabel(/^Số người tối đa/).fill('2')
  await form.getByLabel(/^Giá thuê \/ tháng/).fill('2800000')
  await form.getByRole('button', { name: 'Kiểm tra biểu mẫu', exact: true }).click()
  await expect(form.getByRole('status')).toHaveText('Biểu mẫu đã được kiểm tra. Chưa có phòng nào được tạo vì đây là giao diện minh họa.')
  await form.getByRole('button', { name: 'Đóng', exact: true }).click()
  await expect(detailButtons).toHaveCount(4)
  await expect(page.getByRole('button', { name: 'Xem chi tiết phòng A999', exact: true })).toHaveCount(0)
})

test('role tabs show the tenant whole-room price and explain that invoice actions are previews', async ({ page }) => {
  await page.goto('/design')
  const tabs = page.getByRole('navigation', { name: 'Các mẫu giao diện', exact: true })
  await tabs.getByRole('link', { name: 'Mẫu chủ trọ', exact: true }).click()
  await expect(page).toHaveURL(/\/design\?view=owner$/)
  await expect(tabs.getByRole('link', { name: 'Mẫu chủ trọ', exact: true })).toHaveAttribute('aria-current', 'page')
  await tabs.getByRole('link', { name: 'Mẫu người thuê', exact: true }).click()
  await expect(page).toHaveURL(/\/design\?view=tenant$/)
  const room = page.getByRole('article', { name: 'Phòng A102', exact: true })
  await expect(room).toContainText('Giá thuê toàn phòng')
  await expect(room).toContainText('2.800.000')
  await expect(room).toContainText('1 / 2 người')
  await expect(page.getByRole('heading', { name: 'Dịch vụ đang sử dụng', exact: true })).toBeVisible()
  const invoiceTrigger = page.getByRole('button', { name: 'Xem hóa đơn', exact: true })
  await invoiceTrigger.click()
  const invoice = page.getByRole('dialog', { name: 'Hóa đơn của tôi', exact: true })
  await expect(invoice).toBeVisible()
  await expect(invoice).toContainText('Bản mẫu hiện chưa hiển thị hóa đơn thực tế.')
  await expectDialogFocusWrap(page, invoice, 'Đóng thông tin bản mẫu', 'Đã hiểu')
  await page.keyboard.press('Escape')
  await expect(invoice).not.toBeVisible()
  await expect(invoiceTrigger).toBeFocused()
})

test('shared guide fits narrow phones, tablets, and desktop screens', async ({ page }) => {
  for (const width of [360, 390, 768, 1280]) {
    await page.setViewportSize({ width, height: width < 600 ? 844 : 900 })
    await page.goto('/design')
    await expect(page.getByRole('heading', { name: 'Một bảng màu vừa đủ', exact: true })).toBeVisible()
    await expectNoHorizontalOverflow(page)
    if (width === 390 || width === 1280) await screenshot(page, `guide-${width === 390 ? 'mobile' : 'desktop'}`)
  }
})

test('both role previews fit phones, tablets, and desktop screens', async ({ page }) => {
  for (const role of ['owner', 'tenant']) {
    for (const width of [360, 390, 768, 1280]) {
      await page.setViewportSize({ width, height: width < 600 ? 844 : 900 })
      await page.goto(`/design?view=${role}`)
      await expect(page.getByRole('heading', { name: role === 'owner' ? 'Quản lý phòng' : 'Phòng của tôi', exact: true })).toBeVisible()
      await expectNoHorizontalOverflow(page)
      if (width === 390 || width === 1280) await screenshot(page, `${role}-${width === 390 ? 'mobile' : 'desktop'}`)
    }
  }
})

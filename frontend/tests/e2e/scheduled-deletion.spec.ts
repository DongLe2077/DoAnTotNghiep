import { expect, test } from '@playwright/test'

test('owners review the blue deletion dialogs and confirm before purging a queued room', async ({ page }) => {
  test.setTimeout(60_000)
  await page.goto('/login')
  await page.getByLabel('Tên đăng nhập', { exact: true }).fill('e2e_owner')
  await page.getByLabel('Mật khẩu', { exact: true }).fill('E2e-Password-2077!')
  await page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()
  await expect(page).toHaveURL('/owner')

  await page.request.get('/api/auth/csrf-cookie')
  const token = (await page.context().cookies()).find(cookie => cookie.name === 'XSRF-TOKEN')!
  const name = 'PURGE-NOW'
  const created = await page.request.post('/api/owner/rooms', {
    headers: { 'X-XSRF-TOKEN': decodeURIComponent(token.value) },
    data: { room_number: name, capacity: 1, default_monthly_rent: '2000' },
  })
  expect(created.status(), await created.text()).toBe(201)
  const roomId = (await created.json()).data.id

  await page.goto('/owner/rooms')
  await page.getByRole('button', { name: 'Xóa PURGE-NOW', exact: true }).click()
  const schedule = page.getByRole('dialog', { name: 'Xác nhận lên lịch xóa' })
  await expect(schedule).toHaveCSS('--color-primary', '#214cc7')
  await schedule.getByRole('button', { name: 'Xác nhận xóa sau 3 ngày' }).click()
  const pending = page.getByRole('dialog', { name: 'Lịch xóa dữ liệu' })
  await expect(pending.getByRole('button', { name: 'Hủy xóa', exact: true })).toBeVisible()
  await pending.getByRole('button', { name: 'Xóa vĩnh viễn ngay', exact: true }).click()
  const confirm = page.getByRole('dialog', { name: 'Xóa vĩnh viễn ngay' })
  await expect(confirm).toHaveCSS('--color-primary', '#214cc7')
  await expect(confirm.getByText('Phòng · 1')).toBeVisible()
  const close = confirm.getByRole('button', { name: 'Đóng hộp thoại' })
  await expect(close).toBeFocused()
  await page.keyboard.press('Shift+Tab')
  await expect(confirm.getByRole('button', { name: 'Xác nhận xóa vĩnh viễn' })).toBeFocused()
  await page.keyboard.press('Tab')
  await expect(close).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(confirm).toBeHidden()
  expect((await page.request.get('/api/owner/rooms/' + roomId)).status()).toBe(200)

  await page.goto('/owner/pending-deletions')
  const queued = page.getByRole('article').filter({ hasText: name })
  const cancel = queued.getByRole('button', { name: 'Hủy xóa', exact: true })
  await cancel.focus()
  await page.keyboard.press('Enter')
  await expect(page.getByText('Đã hủy xóa PURGE-NOW.')).toBeVisible()
  await expect(queued).toHaveCount(0)
  expect((await page.request.get('/api/owner/rooms/' + roomId)).status()).toBe(200)

  await page.goto('/owner/rooms')
  await page.getByRole('button', { name: 'Xóa PURGE-NOW', exact: true }).click()
  await schedule.getByRole('button', { name: 'Xác nhận xóa sau 3 ngày' }).click()
  await expect(pending).toBeVisible()

  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/owner/pending-deletions')
  const record = page.getByRole('article').filter({ hasText: name })
  await expect(record).toHaveCSS('--color-primary', '#214cc7')
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true)
  await record.getByRole('button', { name: 'Xóa vĩnh viễn ngay', exact: true }).click()
  await expect(confirm.getByText('Phòng · 1')).toBeVisible()
  await confirm.getByRole('button', { name: 'Xác nhận xóa vĩnh viễn' }).click()

  await expect(page.getByText('Đã xóa vĩnh viễn PURGE-NOW.')).toBeVisible()
  await expect(record).toHaveCount(0)
  expect((await page.request.get('/api/owner/rooms/' + roomId)).status()).toBe(404)
})

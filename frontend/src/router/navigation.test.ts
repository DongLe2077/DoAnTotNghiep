import { describe, expect, it } from 'vitest'
import { safeRedirect } from './navigation'

describe('post-login destination', () => {
  it.each(['https://example.com', '//example.com', '/owner\\evil', '/owner/\nunsafe', '/tenant/invoices', '/ownership', '/notifications/external', '/notificationsevil'])('rejects unsafe or wrong-role destination %s', value => {
    expect(safeRedirect(value, 'owner')).toBe('/owner')
  })

  it.each(['/owner/invoices?month=2026-10', '/owner', '/account', '/notifications', '/notifications?unread=1&type=invoice_reminder&page=2'])('preserves an allowed local destination %s', value => {
    expect(safeRedirect(value, 'owner')).toBe(value)
  })

  it('preserves the shared notification destination for a tenant', () => {
    expect(safeRedirect('/notifications?unread=1', 'tenant')).toBe('/notifications?unread=1')
  })
})

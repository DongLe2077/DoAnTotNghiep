import { describe, expect, it } from 'vitest'
import { formatDateTime, notificationTarget, notificationTypeLabel } from './notifications'
import { isNotification, isNotificationPage, type AppNotification } from '@/types/notifications'

const notification: AppNotification = {
  id: 1, type: 'invoice_reminder', data: { title: 'Nhắc hóa đơn', body: 'Có khoản cần thanh toán.' },
  invoice_id: 12, maintenance_request_id: null, is_read: false, read_at: null,
  created_at: '2026-10-22 08:30:00',
}

describe('Vietnamese notification timestamps', () => {
  it('interprets Laravel local timestamps as Vietnam time independent of the execution timezone', () => {
    const local = formatDateTime('2026-10-22 08:30:45')
    expect(local).toBe(formatDateTime('2026-10-22T01:30:45Z'))
    expect(local).toContain('22/10/2026')
    expect(local).toContain('08:30')
  })
  it('converts zoned timestamps across the Vietnam date boundary', () => {
    const result = formatDateTime('2026-10-22T20:05:00Z')
    expect(result).toContain('23/10/2026')
    expect(result).toContain('03:05')
  })
  it.each([null, '', 'not-a-date', '2026-10-22T08:30:00', '2026-99-99T08:30:00Z'])('does not display ambiguous or invalid timestamps: %s', value => {
    expect(formatDateTime(value)).toBe('—')
  })
})

describe('notification subject destinations', () => {
  it('builds only role-local API destinations from supported notification types', () => {
    expect(notificationTarget(notification, 'tenant')).toEqual({ kind: 'invoice', path: '/tenant/invoices/12' })
    expect(notificationTarget(notification, 'owner')).toEqual({ kind: 'invoice', path: '/owner/invoices/12' })
    for (const type of ['maintenance_submitted', 'maintenance_updated'] as const) {
      expect(notificationTarget({ ...notification, type, invoice_id: null, maintenance_request_id: 9 }, 'owner'))
        .toEqual({ kind: 'maintenance', path: '/owner/maintenance-requests/9' })
    }
  })
  it.each([null, 0, -1, 1.5, Infinity, Number.MAX_SAFE_INTEGER + 1, '12', '1/../../owner'])('does not turn an invalid subject id into an API path: %s', id => {
    expect(notificationTarget({ ...notification, invoice_id: id } as AppNotification, 'tenant')).toBeNull()
  })
  it('ignores unknown subject types and unrelated populated id fields', () => {
    expect(notificationTarget({ ...notification, type: 'other_event' } as unknown as AppNotification, 'tenant')).toBeNull()
    expect(notificationTarget({ ...notification, invoice_id: null, maintenance_request_id: 9 }, 'tenant')).toBeNull()
    expect(notificationTarget({ ...notification, type: 'maintenance_updated', maintenance_request_id: null }, 'owner')).toBeNull()
  })
  it('provides a readable label for each supported notification type', () => {
    expect(notificationTypeLabel('invoice_reminder')).toBe('Nhắc hóa đơn')
    expect(notificationTypeLabel('maintenance_submitted')).toBe('Báo hỏng mới')
    expect(notificationTypeLabel('maintenance_updated')).toBe('Cập nhật sửa chữa')
  })
})

describe('notification API response boundary', () => {
  it('accepts the current backend shape and pagination beyond the last page for subsequent normalization', () => {
    expect(isNotification(notification)).toBe(true)
    expect(isNotificationPage({ data: [notification], meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 } })).toBe(true)
    expect(isNotificationPage({ data: [], meta: { current_page: 2, last_page: 1, per_page: 15, total: 1 } })).toBe(true)
  })
  it.each([
    null,
    { ...notification, id: '1' },
    { ...notification, id: 0 },
    { ...notification, type: 'unsupported' },
    { ...notification, data: { title: 42, body: 'Message' } },
    { ...notification, data: { title: 'Title' } },
    { ...notification, is_read: 'false' },
    { ...notification, invoice_id: -1 },
    { ...notification, maintenance_request_id: {} },
    { ...notification, read_at: false },
  ])('rejects malformed notification records before they enter private state: %j', value => {
    expect(isNotification(value)).toBe(false)
  })
  it.each([
    { data: 'not-an-array', meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 } },
    { data: [{}], meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 } },
    { data: [], meta: { current_page: '1', last_page: 1, per_page: 15, total: 0 } },
    { data: [], meta: { current_page: 1, last_page: 0, per_page: 15, total: 0 } },
    { data: [], meta: { current_page: 1, last_page: 1, per_page: 0, total: 0 } },
    { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: -1 } },
  ])('rejects unusable pagination and partial private records: %j', value => {
    expect(isNotificationPage(value)).toBe(false)
  })
})

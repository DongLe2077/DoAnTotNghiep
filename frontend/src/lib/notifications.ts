import type { AppNotification, NotificationType } from '@/types/notifications'
import type { Role } from '@/types/auth'

export function notificationTypeLabel(type: NotificationType): string {
  return { maintenance_submitted: 'Báo hỏng mới', maintenance_updated: 'Cập nhật sửa chữa', invoice_reminder: 'Nhắc hóa đơn', invoice_updated: 'Cập nhật hóa đơn', owner_announcement: 'Thông báo chung' }[type]
}

const dateTime = new Intl.DateTimeFormat('vi-VN', {
  timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit', day: '2-digit',
  hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
})

export function formatDateTime(value: string | null): string {
  if (!value) return '—'
  // Laravel resources explicitly return Vietnam local time in this format.
  const normalized = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value)
    ? `${value.replace(' ', 'T')}+07:00` : value
  if (!/^\d{4}-\d{2}-\d{2}T.+(?:Z|[+-]\d{2}:\d{2})$/.test(normalized)) return '—'
  const parsed = new Date(normalized)
  return Number.isFinite(parsed.getTime()) ? dateTime.format(parsed) : '—'
}

export function notificationTarget(notification: AppNotification, role: Role): { kind: 'invoice' | 'maintenance' | 'announcement'; path: string } | null {
  if (notification.type === 'owner_announcement' && role === 'tenant' && Number.isSafeInteger(notification.announcement_id) && Number(notification.announcement_id) > 0) {
    return { kind: 'announcement', path: `/tenant/announcements/${notification.announcement_id}` }
  }
  if ((notification.type === 'invoice_reminder' || notification.type === 'invoice_updated') && Number.isSafeInteger(notification.invoice_id) && Number(notification.invoice_id) > 0) {
    return { kind: 'invoice', path: `/${role}/invoices/${notification.invoice_id}` }
  }
  if ((notification.type === 'maintenance_submitted' || notification.type === 'maintenance_updated')
    && Number.isSafeInteger(notification.maintenance_request_id) && Number(notification.maintenance_request_id) > 0) {
    return { kind: 'maintenance', path: `/${role}/maintenance-requests/${notification.maintenance_request_id}` }
  }
  return null
}

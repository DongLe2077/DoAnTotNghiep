export const notificationTypes = ['maintenance_submitted', 'maintenance_updated', 'invoice_reminder', 'invoice_updated', 'owner_announcement'] as const
export type NotificationType = typeof notificationTypes[number]

export interface AppNotification {
  id: number
  type: NotificationType
  data: { title: string; body: string; status?: string }
  invoice_id: number | null
  maintenance_request_id: number | null
  announcement_id?: number | null
  is_read: boolean
  read_at: string | null
  created_at: string | null
}

export interface NotificationPage {
  data: AppNotification[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

function record(value: unknown): value is Record<string, unknown> {
  return !!value && typeof value === 'object' && !Array.isArray(value)
}
function nullableId(value: unknown) { return value === null || (Number.isSafeInteger(value) && Number(value) > 0) }

export function isNotification(value: unknown): value is AppNotification {
  return record(value) && Number.isSafeInteger(value.id) && Number(value.id) > 0
    && notificationTypes.includes(value.type as NotificationType)
    && record(value.data) && typeof value.data.title === 'string' && typeof value.data.body === 'string'
    && (value.data.status === undefined || typeof value.data.status === 'string')
    && nullableId(value.invoice_id) && nullableId(value.maintenance_request_id)
    && (value.announcement_id === undefined || nullableId(value.announcement_id))
    && (value.type !== 'owner_announcement' || (Number.isSafeInteger(value.announcement_id) && Number(value.announcement_id) > 0))
    && typeof value.is_read === 'boolean'
    && (value.created_at === null || typeof value.created_at === 'string')
    && (value.read_at === null || typeof value.read_at === 'string')
}

export function isNotificationPage(value: unknown): value is NotificationPage {
  return record(value) && Array.isArray(value.data) && value.data.every(isNotification)
    && record(value.meta) && ['current_page', 'last_page', 'per_page'].every(key => Number.isSafeInteger((value.meta as Record<string, unknown>)[key]) && Number((value.meta as Record<string, unknown>)[key]) > 0)
    && Number.isSafeInteger(value.meta.total) && Number(value.meta.total) >= 0
}

export const maintenanceStatuses = ['submitted', 'in_progress', 'resolved'] as const
export type MaintenanceStatus = typeof maintenanceStatuses[number]

export interface MaintenanceHistoryEntry {
  id: number; revision: number; action: 'submitted' | 'updated' | 'baseline'
  before: { status: MaintenanceStatus | null; resolution: string | null }
  after: { status: MaintenanceStatus; resolution: string | null }
  actor: { label: string; role: 'owner' | 'tenant'; id?: number; username?: string } | null
  created_at: string
}

export interface MaintenancePhoto {
  id: number
  mime_type: string
  byte_size: number
  created_at: string
  url: string
}

export interface MaintenanceRequest {
  id: number
  occupancy_id: number
  submitted_by: number
  representative_assignment_id: number
  room: { id: number; room_number: string }
  group: { id: number; group_code: string }
  reporter: { id: number; full_name: string; phone_number: string } | null
  title: string
  description: string
  status: MaintenanceStatus
  status_label: string
  resolution: string | null
  photos: MaintenancePhoto[]
  submitted_at: string
  updated_at: string
  can_process: boolean
  version_token: string
}

export function maintenanceStatusLabel(status: MaintenanceStatus) {
  return { submitted: 'Chờ tiếp nhận', in_progress: 'Đang xử lý', resolved: 'Đã xử lý' }[status]
}

export function maintenanceStatusTone(status: MaintenanceStatus): 'warning' | 'info' | 'success' {
  return { submitted: 'warning', in_progress: 'info', resolved: 'success' }[status] as 'warning' | 'info' | 'success'
}

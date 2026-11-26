export type InvitationStatus = 'active' | 'expired' | 'revoked' | 'used_up'
export type JoinStatus = 'pending' | 'approved' | 'rejected' | 'withdrawn'

export interface Invitation {
  id: number
  suffix: string
  status: InvitationStatus
  approvals: number
  max_approvals: number
  expires_at: string
}

export interface JoinRequest {
  id: number
  property_id: number
  property_name: string
  status: JoinStatus
  invitation_status: InvitationStatus
  membership_status: 'approved' | 'revoked' | null
  created_at: string
  decided_at: string | null
}

export interface OwnerJoinRequest extends JoinRequest {
  user_id: number
  full_name: string | null
  username: string
  contact_phone: string | null
  tenant_id: number | null
}

export interface MembershipAccount {
  id: number
  username: string
  full_name: string | null
  contact_phone: string | null
  tenant_id: number | null
}

export interface Paged<T> {
  data: T[]
  meta: { current_page: number; last_page: number; total: number }
}

export const joinLabels: Record<JoinStatus, string> = {
  pending: 'Chờ chủ trọ duyệt',
  approved: 'Đã duyệt',
  rejected: 'Đã từ chối',
  withdrawn: 'Đã rút',
}

export const invitationLabels: Record<InvitationStatus, string> = {
  active: 'Còn hiệu lực',
  expired: 'Đã hết hạn',
  revoked: 'Đã thu hồi',
  used_up: 'Đã hết lượt',
}

import { serverNow } from '@/lib/server-clock'

export interface RoomListing {
  id: number
  title: string
  property_name: string
  room_number: string | null
  area: string
  area_m2: number | null
  description: string
  amenities: string | null
  fee_notes: string | null
  monthly_rent: string | null
  capacity: number
  spots: number
  sharing: boolean
  occupied: boolean
  has_photo: boolean
}

export interface OwnerListing extends RoomListing {
  room_id: number | null
  published: boolean
  accepting: boolean
}

export type RequestKind = 'viewing' | 'rent'
export interface RoomRequest {
  id: number
  listing_id: number
  title: string
  kind: RequestKind
  status: string
  people: number
  contact_name: string
  contact_phone: string
  note: string | null
  owner_note: string | null
  preferred_at: string
  appointment_at: string | null
  occupancy_id: number | null
  created_at: string
  moved_in_at: string | null
}

export interface OwnerRoomRequest extends RoomRequest {
  tenant_id: number | null
  tenant_name: string | null
  is_member: boolean
}

export const requestLabels: Record<string, string> = {
  pending: 'Chờ xác nhận', confirmed: 'Đã xác nhận', proposed: 'Đề xuất giờ khác',
  viewed: 'Đã đến xem', cancelled: 'Đã hủy', no_show: 'Không đến',
  new: 'Yêu cầu mới', discussing: 'Đang trao đổi', accepted: 'Đã chấp thuận',
  rejected: 'Đã từ chối', withdrawn: 'Đã rút', moved_in: 'Đã nhận phòng',
}

export const openRequest = (status: string) => ['pending', 'confirmed', 'proposed', 'new', 'discussing', 'accepted'].includes(status)

export function marketTimestamp(value: string) {
  return value.replace('T', ' ') + (value.length === 16 ? ':00' : '')
}

export function vietnamNow() {
  const formatter = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh', dateStyle: 'short', timeStyle: 'short' })
  return formatter.format(serverNow()).replace(' ', 'T')
}

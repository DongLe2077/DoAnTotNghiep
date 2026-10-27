import type { User } from './auth'
import type { ServicePeriod } from './owner'
import type { MaintenanceRequest } from './maintenance'
import type { PaymentStatus } from './billing'
export interface MyProfile { id: number; full_name: string; phone_number: string; identity_number: string | null; account: User }
export interface MyStay {
  id: number; occupancy_id: number; moved_in_at: string; moved_out_at: string | null; is_active: boolean
  is_current_representative: boolean; can_submit_maintenance: boolean
  room: { id: number; room_number: string; capacity: number; operational_status: 'available' | 'maintenance' }
  group: { id: number; group_code: string }; occupancy_started_at: string; occupancy_ended_at: string | null
  monthly_room_rent: string; services?: ServicePeriod[]
}
export interface EligibleStay { occupancy_id: number; room: { id: number; room_number: string }; group: { id: number; group_code: string } }
export type MyMaintenance = Omit<MaintenanceRequest, 'version_token' | 'reporter'>
export function tenantPaymentLabel(status: PaymentStatus) { return { unpaid: 'Chưa thanh toán', partially_paid: 'Thanh toán một phần', paid: 'Đã thanh toán đủ', inconsistent: 'Cần đối chiếu', cancelled: 'Đã hủy' }[status] }

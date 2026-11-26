export interface CheckoutApplication {
  id: number
  occupancy_id: number
  room_number: string
  requester_name: string
  status: 'pending' | 'withdrawn' | 'rejected' | 'confirmed' | 'closed'
  requested_at: string
  ended_at: string | null
  rejection_reason: string | null
}

export const checkoutStatus = {
  pending: 'Chờ xử lý',
  withdrawn: 'Đã rút yêu cầu',
  rejected: 'Đã từ chối',
  confirmed: 'Đã xác nhận trả phòng',
  closed: 'Đợt thuê đã kết thúc',
} satisfies Record<CheckoutApplication['status'], string>

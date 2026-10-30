import type { MeterReading } from './billing'

export interface BulkMeterReadingSummary {
  id: number; occupancy_service_id: number; period_start: string; period_end: string; previous_reading: string; current_reading: string
}
export interface BulkMeterRow {
  row_key: string
  billing_month: string
  occupancy_id: number
  occupancy_service_id: number
  room: { id: number; room_number: string }
  group: { id: number; group_code: string }
  service: { id: number; service_code: string; name: string }
  unit: string
  unit_price: string
  effective_from: string
  effective_until: string | null
  occupancy_started_at: string
  occupancy_ended_at: string | null
  period_start: string | null
  period_end: string | null
  previous_reading: string | null
  baseline_known: boolean
  current_reading: null
  can_record: boolean
  blocked_reason: string | null
  context_token: string
  latest_reading: BulkMeterReadingSummary | null
  month_readings: BulkMeterReadingSummary[]
  invoice_id: number | null
}
export interface BulkMeterPayload {
  billing_month: string
  occupancy_id: number
  occupancy_service_id: number
  period_start: string
  period_end: string
  previous_reading: string
  current_reading: string
  context_token: string
  idempotency_key: string
}
export interface PendingBulkMeter {
  owner_id: number
  label: string
  unit: string
  state: 'queued' | 'unknown'
  payload: BulkMeterPayload
}
export interface BulkMeterResponse {
  data: MeterReading | null
  reading_id: number
  reading_deleted: boolean
  reading_changed: boolean
  replayed: boolean
  message: string
}

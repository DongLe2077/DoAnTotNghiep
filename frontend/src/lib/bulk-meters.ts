import type { BulkMeterPayload, PendingBulkMeter } from '@/types/bulk-meters'

const decimalPattern = /^\d{1,12}(?:\.\d{1,3})?$/
export function normalizeMeterDecimal(value: string): string | null {
  if (typeof value !== 'string' || !decimalPattern.test(value)) return null
  const [whole = '0', fraction = ''] = value.split('.')
  return `${BigInt(whole)}.${fraction.padEnd(3, '0')}`
}
export function meterConsumption(previous: string, current: string): string | null {
  const first = normalizeMeterDecimal(previous), last = normalizeMeterDecimal(current)
  if (first === null || last === null) return null
  const difference = BigInt(last.replace('.', '')) - BigInt(first.replace('.', ''))
  if (difference < 0n) return null
  return `${difference / 1000n}.${String(difference % 1000n).padStart(3, '0')}`
}
export function validMeterMonth(value: string): boolean { return /^[1-9]\d{3}-(?:0[1-9]|1[0-2])$/.test(value) }
function validTime(value: unknown): value is string {
  if (typeof value !== 'string' || !/^[1-9]\d{3}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value)) return false
  const parts = value.split(/[- :]/).map(Number), [year, month, day, hour, minute, second] = parts as [number, number, number, number, number, number]
  const date = new Date(Date.UTC(year, month - 1, day, hour, minute, second))
  return date.getUTCFullYear() === year && date.getUTCMonth() === month - 1 && date.getUTCDate() === day && date.getUTCHours() === hour && date.getUTCMinutes() === minute && date.getUTCSeconds() === second
}
export function bulkMeterStoragePrefix(ownerId: number): string { return `rental.bulk-meter.pending.v1:${ownerId}:` }
export function readPendingBulkMeter(raw: string, ownerId: number): PendingBulkMeter | null {
  let item: unknown
  try { item = JSON.parse(raw) } catch { return null }
  if (!item || typeof item !== 'object' || Array.isArray(item)) return null
  const entry = item as Record<string, unknown>, payload = entry.payload as Record<string, unknown> | undefined
  if (entry.owner_id !== ownerId || !Number.isSafeInteger(ownerId) || ownerId < 1 || !['queued', 'unknown'].includes(String(entry.state))
    || typeof entry.label !== 'string' || entry.label.length < 1 || entry.label.length > 400
    || typeof entry.unit !== 'string' || entry.unit.length > 30 || !payload || typeof payload !== 'object' || Array.isArray(payload)
    || typeof payload.billing_month !== 'string' || !validMeterMonth(payload.billing_month)
    || !Number.isSafeInteger(payload.occupancy_id) || Number(payload.occupancy_id) < 1
    || !Number.isSafeInteger(payload.occupancy_service_id) || Number(payload.occupancy_service_id) < 1
    || !validTime(payload.period_start) || !validTime(payload.period_end) || payload.period_start >= payload.period_end
    || !payload.period_start.startsWith(payload.billing_month)
    || typeof payload.previous_reading !== 'string' || typeof payload.current_reading !== 'string'
    || meterConsumption(payload.previous_reading, payload.current_reading) === null
    || typeof payload.context_token !== 'string' || !/^[a-f0-9]{64}$/.test(payload.context_token)
    || typeof payload.idempotency_key !== 'string' || !/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/.test(payload.idempotency_key)) return null
  const restored: BulkMeterPayload = { billing_month: payload.billing_month, occupancy_id: Number(payload.occupancy_id), occupancy_service_id: Number(payload.occupancy_service_id),
    period_start: payload.period_start, period_end: payload.period_end, previous_reading: payload.previous_reading, current_reading: payload.current_reading,
    context_token: payload.context_token, idempotency_key: payload.idempotency_key }
  return { owner_id: ownerId, label: entry.label, unit: entry.unit, state: entry.state as PendingBulkMeter['state'], payload: restored }
}
export function bulkMeterPayloadErrors(payload: Omit<BulkMeterPayload, 'idempotency_key'>): Record<string, string[]> {
  const errors: Record<string, string[]> = {}
  if (normalizeMeterDecimal(payload.previous_reading) === null) errors.previous_reading = ['Nhập chỉ số đầu không âm, tối đa 12 chữ số nguyên và 3 chữ số lẻ.']
  if (normalizeMeterDecimal(payload.current_reading) === null) errors.current_reading = ['Nhập chỉ số cuối không âm, tối đa 12 chữ số nguyên và 3 chữ số lẻ.']
  else if (!errors.previous_reading && meterConsumption(payload.previous_reading, payload.current_reading) === null) errors.current_reading = ['Chỉ số cuối phải lớn hơn hoặc bằng chỉ số đầu.']
  if (!validTime(payload.period_start) || !payload.period_start.startsWith(payload.billing_month)) errors.period_start = ['Đầu kỳ phải đúng định dạng và nằm trong tháng đang nhập.']
  if (!validTime(payload.period_end) || payload.period_end <= payload.period_start) errors.period_end = ['Cuối kỳ phải sau đầu kỳ và có thời gian hợp lệ.']
  else if (validMeterMonth(payload.billing_month)) {
    const [year, month] = payload.billing_month.split('-').map(Number)
    const nextMonth = Date.UTC(year!, month!, 1)
    if (Date.parse(`${payload.period_end.replace(' ', 'T')}Z`) > nextMonth) errors.period_end = ['Cuối kỳ không được vượt đầu tháng kế tiếp.']
  }
  return errors
}

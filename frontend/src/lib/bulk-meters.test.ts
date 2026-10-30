import { describe, expect, it } from 'vitest'
import { bulkMeterPayloadErrors, meterConsumption, normalizeMeterDecimal, readPendingBulkMeter } from './bulk-meters'

const payload = { billing_month: '2026-01', occupancy_id: 7, occupancy_service_id: 12, period_start: '2026-01-01 00:00:00', period_end: '2026-02-01 00:00:00', previous_reading: '100.125', current_reading: '120.375', context_token: 'a'.repeat(64), idempotency_key: 'bulk-reading-2077' }
describe('bulk meter decimals and request recovery', () => {
  it('keeps full meter precision without floating point subtraction', () => {
    expect(meterConsumption('999999999998.999', '999999999999.001')).toBe('0.002')
    expect(meterConsumption('0.1', '0.3')).toBe('0.200')
    expect(normalizeMeterDecimal('0001.2')).toBe('1.200')
    expect(meterConsumption('2', '1.999')).toBeNull()
    for (const value of ['-1', '1e2', '1,000', '1.0001', '1000000000000', '']) expect(normalizeMeterDecimal(value)).toBeNull()
  })
  it('restores exact reviewed strings only for the same owner and strips injected properties', () => {
    const entry = { owner_id: 4, label: 'Phòng A101 · Điện', unit: 'kWh', state: 'unknown', payload }
    expect(readPendingBulkMeter(JSON.stringify({ ...entry, admin: true, payload: { ...payload, recorded_by: 99 } }), 4)).toEqual(entry)
    expect(readPendingBulkMeter(JSON.stringify(entry), 5)).toBeNull()
  })
  it('rejects malformed recovery data without silently issuing a new key', () => {
    const entry = { owner_id: 4, label: 'Phòng A101', unit: 'kWh', state: 'queued', payload }
    for (const change of [{ billing_month: '2026-13' }, { occupancy_id: 0 }, { period_start: '2026-01-32 00:00:00' }, { current_reading: '1' }, { context_token: '' }, { idempotency_key: 'bad' }]) {
      expect(readPendingBulkMeter(JSON.stringify({ ...entry, payload: { ...payload, ...change } }), 4)).toBeNull()
    }
    expect(readPendingBulkMeter('{bad', 4)).toBeNull()
  })
  it('validates edited first baselines and intervals before the review step', () => {
    expect(bulkMeterPayloadErrors(payload)).toEqual({})
    expect(bulkMeterPayloadErrors({ ...payload, current_reading: '99' })).toHaveProperty('current_reading')
    expect(bulkMeterPayloadErrors({ ...payload, previous_reading: '', period_end: '2026-01-01 00:00:00' })).toHaveProperty('previous_reading')
    expect(bulkMeterPayloadErrors({ ...payload, period_start: '2025-12-31 23:59:59' })).toHaveProperty('period_start')
    expect(bulkMeterPayloadErrors({ ...payload, period_end: '2026-02-01 00:00:01' })).toHaveProperty('period_end')
    expect(bulkMeterPayloadErrors({ ...payload, billing_month: '2026-12', period_start: '2026-12-01 00:00:00', period_end: '2027-01-01 00:00:00' })).toEqual({})
  })
})

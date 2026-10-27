import { describe, expect, it } from 'vitest'
import { formatUnitPrice } from './unit-price'
import { eventPayload } from '@/composables/useOwnerApi'
describe('owner prices and local event input', () => {
  it('preserves every digit of a four-decimal service price including the largest supported amount', () => {
    expect(formatUnitPrice('123456.1234')).toBe('123.456,1234 ₫')
    expect(formatUnitPrice('99999999999999.9999')).toBe('99.999.999.999.999,9999 ₫')
    expect(formatUnitPrice('100000.0000')).toBe('100.000 ₫')
  })
  it.each(['1,000', '-1', '1.12345', 'NaN', '100000000000000'])('rejects malformed unit price %s', price => expect(formatUnitPrice(price)).toBe('—'))
  it('sends Vietnam wall time directly without a browser timezone conversion', () => {
    expect(eventPayload('2026-10-22T09:30')).toBe('2026-10-22 09:30:00')
    expect(eventPayload('2026-10-22T09:30:45')).toBe('2026-10-22 09:30:45')
  })
})

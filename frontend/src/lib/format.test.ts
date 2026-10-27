import { describe, expect, it } from 'vitest'
import { formatDate, formatMoney } from './format'

describe('Vietnamese display formatting', () => {
  it('shows decimal strings as VND without calculating business totals', () => {
    expect(formatMoney('2500000.00').replace(/\s/g, '')).toBe('2.500.000₫')
    expect(formatMoney('12.50').replace(/\s/g, '')).toBe('12,5₫')
  })
  it.each(['2.500.000', '1e6', '', 'NaN', '10000000000000000.00'])('does not misrepresent an invalid or unsupported amount %s', value => {
    expect(formatMoney(value)).toBe('—')
  })
  it('preserves valid large decimal strings and negative cash summaries exactly', () => {
    expect(formatMoney('9007199254740993.99').replace(/\s/g, '')).toBe('9.007.199.254.740.993,99₫')
    expect(formatMoney('-0.50').replace(/\s/g, '')).toBe('-0,5₫')
    expect(formatMoney('-1500000.01').replace(/\s/g, '')).toBe('-1.500.000,01₫')
  })
  it('preserves date-only values and converts zoned timestamps to Vietnam time', () => {
    expect(formatDate('2026-10-22')).toBe('22/10/2026')
    expect(formatDate('2026-10-22T20:00:00Z')).toBe('23/10/2026')
  })
  it.each(['2026-02-30', '2026-10-22T20:00:00', '', null])('rejects impossible or ambiguous dates %s', value => {
    expect(formatDate(value)).toBe('—')
  })
})

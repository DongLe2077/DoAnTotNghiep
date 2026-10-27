import { describe, expect, it } from 'vitest'
import { chartAxis, chartMonth, chartPercent, chartScale, minorUnits } from './charts'
describe('chart geometry preserves authoritative decimal amounts', () => {
  it('parses exact cents beyond the safe integer limit, including negative fractions', () => {
    expect(minorUnits('9007199254740993.99')).toBe(900719925474099399n)
    expect(minorUnits('-0.50')).toBe(-50n)
    expect(minorUnits('0.01')).toBe(1n)
  })
  it.each(['1e9', '1.000.000', '', 'NaN', '0.001'])('does not plot invalid money %s', value => { expect(minorUnits(value)).toBe(0n) })
  it('bounds geometry without dividing by zero or inventing positive zero bars', () => {
    expect(chartPercent(1n, 3n)).toBe(33.33)
    expect(chartPercent(0n, 100n)).toBe(0)
    expect(chartPercent(10n, 0n)).toBe(0)
    expect(chartPercent(-5n, 100n)).toBe(0)
    expect(chartPercent(200n, 100n)).toBe(100)
    expect(chartPercent(900719925474099399n, 900719925474099399n)).toBe(100)
  })
  it('keeps bars in range with a common readable scale', () => {
    expect(chartScale(['2800000.00', '150000.50'])).toBe(500000000n)
    expect(chartScale(['0.00'])).toBe(100n)
    expect(chartScale(['0.01'])).toBe(1n)
    expect(chartAxis(1n).ticks.map(tick => tick.label)).toEqual(['0,01', '0,0075', '0,005', '0,0025', '0'])
    expect(chartAxis(500000000n)).toEqual({ unit: 'triệu đồng', ticks: [
      { position: 100, label: '5' }, { position: 75, label: '3,75' }, { position: 50, label: '2,5' },
      { position: 25, label: '1,25' }, { position: 0, label: '0' },
    ] })
  })
  it('labels months independently of browser timezone', () => {
    expect(chartMonth('2026-01')).toBe('01/2026')
    expect(chartMonth('2026-13')).toBe('—')
  })
})

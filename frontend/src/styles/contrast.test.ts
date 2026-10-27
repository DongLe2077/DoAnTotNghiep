import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

const css = readFileSync(new URL('./main.css', import.meta.url), 'utf8')
const colors = Object.fromEntries([...css.matchAll(/--(color-[\w-]+):\s*(#[a-f\d]{6})/gi)].map(match => [match[1], match[2]!]))
function luminance(hex: string) {
  const values = hex.slice(1).match(/../g)!.map(part => {
    const value = Number.parseInt(part, 16) / 255
    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4
  })
  return values[0]! * .2126 + values[1]! * .7152 + values[2]! * .0722
}
function contrast(a: string, b: string) {
  const first = luminance(colors[a]!)
  const second = luminance(colors[b]!)
  return (Math.max(first, second) + .05) / (Math.min(first, second) + .05)
}

describe('shared palette readability', () => {
  it.each([
    ['color-text', 'color-surface'], ['color-text', 'color-bg'],
    ['color-text-secondary', 'color-surface'], ['color-text-secondary', 'color-bg'],
    ['color-text-muted', 'color-surface'], ['color-text-muted', 'color-bg'],
    ['color-primary', 'color-surface'], ['color-primary', 'color-primary-soft'],
    ['color-success', 'color-success-soft'], ['color-warning', 'color-warning-soft'],
    ['color-danger', 'color-danger-soft'], ['color-info', 'color-info-soft'],
  ])('%s on %s has at least 4.5:1 text contrast', (foreground, background) => {
    expect(contrast(foreground, background)).toBeGreaterThanOrEqual(4.5)
  })
  it.each(['color-input-border', 'color-focus'])('%s is distinguishable against white controls at 3:1', foreground => {
    expect(contrast(foreground, 'color-surface')).toBeGreaterThanOrEqual(3)
  })
})

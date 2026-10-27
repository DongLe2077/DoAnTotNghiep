// Only geometry uses rounded numbers. Money stays in exact decimal strings / integer cents.
export function minorUnits(value: string): bigint {
  if (!/^-?\d{1,24}(?:\.\d{1,2})?$/.test(value)) return 0n
  const negative = value.startsWith('-')
  const [whole = '0', fraction = ''] = value.replace(/^-/, '').split('.')
  const result = BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'))
  return negative ? -result : result
}
export function chartPercent(value: bigint, total: bigint): number {
  if (value <= 0n || total <= 0n) return 0
  return Number((value > total ? total : value) * 10000n / total) / 100
}
export function chartScale(values: string[]): bigint {
  const maximum = values.reduce((max, value) => { const amount = minorUnits(value); return amount > max ? amount : max }, 0n)
  if (maximum === 0n) return 100n
  let unit = 1n
  while (unit * 10n < maximum) unit *= 10n
  for (const multiple of [1n, 2n, 5n, 10n]) if (maximum <= unit * multiple) return unit * multiple
  return maximum
}
export function chartAxis(scale: bigint) {
  const unit = scale >= 100000000000n ? { value: 100000000000n, label: 'tỷ đồng' }
    : scale >= 100000000n ? { value: 100000000n, label: 'triệu đồng' }
      : scale >= 100000n ? { value: 100000n, label: 'nghìn đồng' } : { value: 100n, label: 'đồng' }
  return { unit: unit.label, ticks: [4n, 3n, 2n, 1n, 0n].map(index => {
    const scaled = scale * index * 10000n / (4n * unit.value)
    const fraction = String(scaled % 10000n).padStart(4, '0').replace(/0+$/, '')
    return { position: Number(index) * 25, label: `${scaled / 10000n}${fraction ? ',' + fraction : ''}` }
  }) }
}
export function chartMonth(month: string): string {
  return /^\d{4}-(0[1-9]|1[0-2])$/.test(month) ? `${month.slice(5)}/${month.slice(0, 4)}` : '—'
}

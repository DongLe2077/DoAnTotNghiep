const wholePrice = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 })
export function formatUnitPrice(value: string): string {
  if (!/^\d{1,14}(?:\.\d{1,4})?$/.test(value)) return '—'
  const [whole = '0', decimal = ''] = value.split('.')
  const fraction = decimal.replace(/0+$/, '')
  return `${wholePrice.format(BigInt(whole))}${fraction ? `,${fraction}` : ''} ₫`
}

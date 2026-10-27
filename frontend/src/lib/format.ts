const moneyWholeFormatter = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 })
const dateFormatter = new Intl.DateTimeFormat('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh', day: '2-digit', month: '2-digit', year: 'numeric' })

// Formatting is for display only. Authoritative amounts remain decimal strings from Laravel.
export function formatMoney(value: string): string {
  if (!/^-?\d{1,16}(?:\.\d{1,2})?$/.test(value)) return '—'
  const negative = value.startsWith('-')
  const [whole = '0', fraction = ''] = (negative ? value.slice(1) : value).split('.')
  const decimal = fraction.replace(/0+$/, '')
  return `${negative && (BigInt(whole) !== 0n || decimal) ? '-' : ''}${moneyWholeFormatter.format(BigInt(whole))}${decimal ? `,${decimal}` : ''}\u00a0₫`
}

export function formatDate(value: string | null): string {
  if (!value) return '—'
  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    const [year, month, day] = value.split('-')
    const parsed = new Date(`${value}T12:00:00+07:00`)
    if (!Number.isFinite(parsed.getTime()) || parsed.toISOString().slice(0, 10) !== value) return '—'
    return `${day}/${month}/${year}`
  }
  if (!/^\d{4}-\d{2}-\d{2}T.+(?:Z|[+-]\d{2}:\d{2})$/.test(value)) return '—'
  const parsed = new Date(value)
  return Number.isFinite(parsed.getTime()) ? dateFormatter.format(parsed) : '—'
}

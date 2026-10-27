export type CashType = 'other_income' | 'expense'
export type CashEntryType = CashType | 'invoice_payment'
export interface CashTransaction {
  id: number; transaction_type: CashType; amount: string; occurred_at: string; description: string
  recorded_by: number; recorded_at: string; revision: number; is_deleted: boolean; deleted_at: string | null; version_token: string
}
export interface CashEntry {
  id: string; source: 'payment' | 'cash_transaction'; source_id: number; entry_type: CashEntryType
  direction: 'income' | 'expense'; amount: string; signed_amount: string; occurred_at: string; description: string
  invoice_id: number | null; invoice_number: string | null; payment_method: string | null
  recorded_by: number; recorded_at: string; can_edit: boolean; can_delete: boolean
}
export interface CashTotals {
  invoice_payment_total: string; other_income_total: string; expense_total: string; total_income: string; net_cash_flow: string
  invoice_payment_count: number; other_income_count: number; expense_count: number; entry_count: number
}
export interface CashSummary extends CashTotals {
  from: string | null; until: string | null; group_by: 'day' | 'month'; periods: (CashTotals & { period: string })[]
}
export interface CashSnapshot { transaction_type: CashType; amount: string; occurred_at: string; description: string; deleted_at: string | null; revision: number }
export interface CashHistory {
  id: number; revision: number; action: 'created' | 'updated' | 'deleted' | 'reverted'
  before: CashSnapshot | null; after: CashSnapshot; actor: { id: number; username: string } | null
  reason: string | null; created_at: string; reverted_history_id: number | null
}
export interface CashCreation {
  idempotency_key: string; transaction_type: CashType; amount: string; occurred_at: string; description: string; reason: string | null
}
export function cashTypeLabel(type: CashEntryType): string { return ({ invoice_payment: 'Thu hóa đơn', other_income: 'Thu khác', expense: 'Chi phí' })[type] }
export function cashActionLabel(action: CashHistory['action']): string { return ({ created: 'Tạo khoản', updated: 'Chỉnh sửa', deleted: 'Xóa khoản', reverted: 'Hoàn tác' })[action] }
// Group the original decimal string without converting financial amounts to floating point.
export function cashMoney(value: string): string {
  if (!/^-?\d+(?:\.\d{1,2})?$/.test(value)) return '—'
  const negative = value.startsWith('-'), [whole = '', decimal = ''] = value.replace(/^-/, '').split('.')
  const digits = whole.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  const fraction = decimal.padEnd(2, '0')
  return `${negative ? '−' : ''}${digits}${fraction === '00' ? '' : `,${fraction}`} ₫`
}

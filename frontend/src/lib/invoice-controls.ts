import type { Invoice, InvoiceAction } from '@/types/billing'
export const invoiceActionLabel: Record<InvoiceAction, string> = { adjustment: 'Điều chỉnh số tiền', due_date: 'Đặt / đổi hạn thanh toán', cancel: 'Hủy hóa đơn', restore: 'Khôi phục hóa đơn' }
export function dueDateText(value?: string | null) { return value && /^\d{4}-\d{2}-\d{2}$/.test(value) ? value.split('-').reverse().join('/') : 'Chưa đặt hạn' }
export function dueStatusLabel(invoice: Pick<Invoice, 'due_status' | 'overdue_days'>) {
  return invoice.due_status === 'overdue' ? `Quá hạn ${invoice.overdue_days ?? 0} ngày` : invoice.due_status === 'upcoming' ? 'Chưa quá hạn' : invoice.due_status === 'not_set' ? 'Chưa đặt hạn' : ''
}
export interface InvoiceChangePayload { action: InvoiceAction; reason: string; version_token: string; idempotency_key: string; amount?: string; due_date?: string }
export function readPendingInvoiceChange(key: string): InvoiceChangePayload | null {
  try {
    const raw: unknown = JSON.parse(sessionStorage.getItem(key) ?? 'null')
    if (!raw || typeof raw !== 'object') return null
    const p = raw as InvoiceChangePayload
    if (!Object.hasOwn(invoiceActionLabel, p.action) || typeof p.reason !== 'string' || !p.reason.trim() || p.reason.length > 1000
      || typeof p.version_token !== 'string' || !/^[a-f0-9]{64}$/.test(p.version_token)
      || typeof p.idempotency_key !== 'string' || !/^[a-zA-Z0-9][a-zA-Z0-9._:-]{7,63}$/.test(p.idempotency_key)) return null
    if (p.action === 'adjustment' && (typeof p.amount !== 'string' || !/^-?\d{1,16}(?:\.\d{1,2})?$/.test(p.amount))) return null
    if (p.action === 'due_date' && (typeof p.due_date !== 'string' || !/^[1-9]\d{3}-\d{2}-\d{2}$/.test(p.due_date))) return null
    return { action: p.action, reason: p.reason, version_token: p.version_token, idempotency_key: p.idempotency_key,
      ...(p.action === 'adjustment' ? { amount: p.amount } : {}), ...(p.action === 'due_date' ? { due_date: p.due_date } : {}) }
  } catch { return null }
}

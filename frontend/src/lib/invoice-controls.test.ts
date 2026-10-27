import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { dueDateText, dueStatusLabel, readPendingInvoiceChange } from './invoice-controls'
describe('invoice controls', () => {
  const values = new Map<string, string>()
  const request = { action: 'adjustment', amount: '-50000.25', reason: 'Đối chiếu', version_token: 'a'.repeat(64), idempotency_key: 'same-request-2077' }
  beforeEach(() => { values.clear(); vi.stubGlobal('sessionStorage', { getItem: (key: string) => values.get(key) ?? null }) })
  afterEach(() => vi.unstubAllGlobals())
  it('keeps the exact reviewed request available only under its account/invoice key', () => {
    values.set('owner:1:invoice:2', JSON.stringify(request))
    expect(readPendingInvoiceChange('owner:1:invoice:2')).toEqual(request)
    expect(readPendingInvoiceChange('owner:9:invoice:2')).toBeNull()
    expect(readPendingInvoiceChange('owner:1:invoice:3')).toBeNull()
  })
  it('rejects corrupt requests and inherited action names', () => {
    for (const raw of ['{bad', 'null', '[]', JSON.stringify({ ...request, action: 'constructor' }), JSON.stringify({ ...request, version_token: '' }), JSON.stringify({ ...request, amount: '0.001' })]) {
      values.set('pending', raw); expect(readPendingInvoiceChange('pending')).toBeNull()
    }
  })
  it('does not replay unrecognized fields from browser storage', () => {
    values.set('pending', JSON.stringify({ ...request, due_date: '2030-01-01', performed_by: 999, invoice_id: 999 }))
    expect(readPendingInvoiceChange('pending')).toEqual(request)
  })
  it('preserves a previous date for a safe manual retry across midnight', () => {
    const due = { ...request, action: 'due_date', due_date: '2026-03-05' }
    values.set('pending', JSON.stringify(due))
    expect(readPendingInvoiceChange('pending')).toEqual({ action: 'due_date', due_date: due.due_date, reason: due.reason, version_token: due.version_token, idempotency_key: due.idempotency_key })
  })
  it('uses the backend deadline state without marking legacy invoices overdue', () => {
    expect(dueDateText(null)).toBe('Chưa đặt hạn')
    expect(dueDateText('2030-12-31')).toBe('31/12/2030')
    expect(dueStatusLabel({ due_status: 'not_set' })).toBe('Chưa đặt hạn')
    expect(dueStatusLabel({ due_status: 'overdue', overdue_days: 2 })).toBe('Quá hạn 2 ngày')
    expect(dueStatusLabel({ due_status: 'paid', overdue_days: 0 })).toBe('')
  })
})

import { describe, expect, it } from 'vitest'
import { readPendingMaintenanceExpense } from './maintenance-expenses'

describe('maintenance expense pending operation', () => {
  const operation = { action: 'link', request_id: 7, cash_transaction_id: 12, version_token: 'a'.repeat(64), reason: 'Thay vòi nước', idempotency_key: 'expense-link-2077' }
  it('preserves the exact reviewed operation across reload without accepting a different report', () => {
    expect(readPendingMaintenanceExpense(JSON.stringify(operation), 7)).toEqual(operation)
    expect(readPendingMaintenanceExpense(JSON.stringify(operation), 8)).toBeNull()
  })
  it('restores unlink independently without replaying injected server-owned fields', () => {
    expect(readPendingMaintenanceExpense(JSON.stringify({ ...operation, action: 'unlink', performed_by: 99, amount: '999' }), 7)).toEqual({ ...operation, action: 'unlink' })
  })
  it('rejects corrupt targets, tokens, reasons and action names', () => {
    for (const value of ['{bad', 'null', '[]', ...[
      { action: 'constructor' }, { cash_transaction_id: -1 }, { cash_transaction_id: 1.5 },
      { version_token: '' }, { reason: ' ' }, { reason: 'x'.repeat(1001) }, { idempotency_key: 'short' },
    ].map(overrides => JSON.stringify({ ...operation, ...overrides }))]) expect(readPendingMaintenanceExpense(value, 7)).toBeNull()
  })
})

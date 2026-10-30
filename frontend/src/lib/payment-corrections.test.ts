import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { paymentCorrectionKey, readPendingPaymentCorrection } from './payment-corrections'

describe('pending payment corrections', () => {
  const values = new Map<string, string>()
  const request = { action: 'replace', reason: 'Ghi nhầm số tiền', version_token: 'a'.repeat(64), idempotency_key: 'correction-request-2077', replacement: { amount: '1000000.50', paid_at: '2026-10-27 09:01:02', payment_method: 'cash' } }
  beforeEach(() => { values.clear(); vi.stubGlobal('sessionStorage', { getItem: (key: string) => values.get(key) ?? null }) })
  afterEach(() => vi.unstubAllGlobals())

  it('restores the exact reviewed request only for the original owner and receipt', () => {
    values.set(paymentCorrectionKey(1, 2), JSON.stringify(request))
    expect(readPendingPaymentCorrection(paymentCorrectionKey(1, 2))).toEqual(request)
    expect(readPendingPaymentCorrection(paymentCorrectionKey(9, 2))).toBeNull()
    expect(readPendingPaymentCorrection(paymentCorrectionKey(1, 3))).toBeNull()
  })
  it('does not accept corrupt payloads, zero or excessive precision, unsupported actions or missing versions', () => {
    for (const raw of ['{bad', '[]', 'null', JSON.stringify({ ...request, action: 'constructor' }), JSON.stringify({ ...request, version_token: '' }), JSON.stringify({ ...request, reason: ' ' }), JSON.stringify({ ...request, reason: 'a'.repeat(2001) }), JSON.stringify({ ...request, replacement: { ...request.replacement, amount: '0.00' } }), JSON.stringify({ ...request, replacement: { ...request.replacement, amount: '10.001' } }), JSON.stringify({ ...request, replacement: { ...request.replacement, payment_method: 'other' } })]) {
      values.set('pending', raw)
      expect(readPendingPaymentCorrection('pending')).toBeNull()
    }
  })
  it('whitelists the stored request instead of replaying actor or foreign invoice fields', () => {
    values.set('pending', JSON.stringify({ ...request, performed_by: 99, invoice_id: 99, replacement: { ...request.replacement, recorded_by: 99 } }))
    expect(readPendingPaymentCorrection('pending')).toEqual(request)
  })
  it('never carries replacement fields into a void-only retry', () => {
    values.set('pending', JSON.stringify({ ...request, action: 'void' }))
    expect(readPendingPaymentCorrection('pending')).toEqual({ action: 'void', reason: request.reason, version_token: request.version_token, idempotency_key: request.idempotency_key })
  })
  it('returns no private request when browser storage cannot be read', () => {
    vi.stubGlobal('sessionStorage', { getItem() { throw new Error('Storage unavailable') } })
    expect(readPendingPaymentCorrection('pending')).toBeNull()
  })
})

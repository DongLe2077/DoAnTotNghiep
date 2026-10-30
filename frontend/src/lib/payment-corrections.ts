import type { PaymentCorrectionPayload } from '@/types/billing'

export function paymentCorrectionKey(ownerId: number | undefined, paymentId: number) {
  return `rental:pending-payment-correction:${ownerId}:${paymentId}`
}

export function readPendingPaymentCorrection(key: string): PaymentCorrectionPayload | null {
  try {
    const raw: unknown = JSON.parse(sessionStorage.getItem(key) ?? 'null')
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null
    const value = raw as PaymentCorrectionPayload
    if (!['void', 'replace'].includes(value.action) || typeof value.reason !== 'string' || !value.reason.trim() || value.reason.length > 2000
      || typeof value.version_token !== 'string' || !/^[a-f0-9]{64}$/.test(value.version_token)
      || typeof value.idempotency_key !== 'string' || !/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/.test(value.idempotency_key)) return null
    const payload: PaymentCorrectionPayload = { action: value.action, reason: value.reason, version_token: value.version_token, idempotency_key: value.idempotency_key }
    if (value.action === 'replace') {
      const replacement = value.replacement
      if (!replacement || typeof replacement !== 'object' || typeof replacement.amount !== 'string'
        || !/^\d{1,16}(?:\.\d{1,2})?$/.test(replacement.amount) || !/[1-9]/.test(replacement.amount)
        || typeof replacement.paid_at !== 'string' || !/^[1-9]\d{3}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(replacement.paid_at)
        || !['cash', 'bank_transfer'].includes(replacement.payment_method)) return null
      payload.replacement = { amount: replacement.amount, paid_at: replacement.paid_at, payment_method: replacement.payment_method }
    }
    return payload
  } catch { return null }
}

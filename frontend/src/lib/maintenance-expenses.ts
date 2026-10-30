export interface PendingMaintenanceExpense {
  action: 'link' | 'unlink'
  request_id: number
  cash_transaction_id: number
  version_token: string
  link_version_token?: string | null
  reason: string
  idempotency_key: string
}

// Restore only the reviewed operation for this report; never accept a different target from storage.
export function readPendingMaintenanceExpense(raw: string, requestId: number): PendingMaintenanceExpense | null {
  let value: unknown
  try { value = JSON.parse(raw) } catch { return null }
  if (!value || typeof value !== 'object' || Array.isArray(value)) return null
  const item = value as Record<string, unknown>
  if ((item.action !== 'link' && item.action !== 'unlink') || item.request_id !== requestId
    || !Number.isSafeInteger(item.cash_transaction_id) || Number(item.cash_transaction_id) < 1
    || typeof item.version_token !== 'string' || !/^[a-f0-9]{64}$/.test(item.version_token)
    || (item.link_version_token !== undefined && item.link_version_token !== null && (typeof item.link_version_token !== 'string' || !/^[a-f0-9]{64}$/.test(item.link_version_token)))
    || typeof item.reason !== 'string' || item.reason.trim().length < 3 || item.reason.length > 1000
    || typeof item.idempotency_key !== 'string' || !/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/.test(item.idempotency_key)) return null
  return { action: item.action, request_id: requestId, cash_transaction_id: Number(item.cash_transaction_id),
    version_token: item.version_token, ...(item.link_version_token !== undefined ? { link_version_token: item.link_version_token as string | null } : {}), reason: item.reason, idempotency_key: item.idempotency_key }
}

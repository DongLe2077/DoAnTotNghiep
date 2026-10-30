export interface MaintenanceExpense {
  id: number; maintenance_request_id: number; cash_transaction_id: number; is_active: boolean; revision: number
  version_token: string; created_at: string; updated_at: string
  cash_transaction: { id: number; transaction_type: 'expense' | 'other_income'; amount: string; description: string; occurred_at: string; is_deleted: boolean; version_token: string }
  maintenance_request: { id: number; title: string }
  included_in_total: boolean
}
export interface MaintenanceExpenseSummary { total_amount: string; active_count: number; deleted_count: number }
export interface MaintenanceExpenseHistory {
  id: number; maintenance_request_id: number; cash_transaction_id: number; revision: number; action: 'linked' | 'unlinked'
  before: { maintenance_request_id: number; cash_transaction_id: number; is_active: boolean; revision: number } | null
  after: { maintenance_request_id: number; cash_transaction_id: number; is_active: boolean; revision: number; cash_snapshot?: { transaction_type: string; amount: string; occurred_at: string; description: string; revision: number; is_deleted: boolean } }
  reason: string; actor: { id: number; username: string; role: string }; created_at: string
}

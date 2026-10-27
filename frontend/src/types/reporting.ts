export interface InvoiceSummary {
  invoice_count: number; valid_invoice_count: number; open_invoice_count: number
  cancelled_count?: number; overdue_count?: number; overdue_amount?: string
  unpaid_count: number; partially_paid_count: number; paid_count: number; inconsistent_count: number
  outstanding_room_count: number; outstanding_group_count: number
  total_amount: string; paid_amount: string; outstanding_amount: string
}
export interface CashSummary {
  invoice_payment_total: string; other_income_total: string; expense_total: string
  total_income: string; net_cash_flow: string; entry_count: number
}
export interface Dashboard {
  month: string; as_of: string
  rooms: { total_count: number; occupied_count: number; vacant_count: number; maintenance_count: number; available_for_check_in_count: number; active_occupancy_count: number; resident_count: number; total_capacity: number }
  tenants: { total_count: number; residing_count: number }
  maintenance: { total_count: number; submitted_count: number; in_progress_count: number; resolved_count: number; open_count: number }
  invoices: InvoiceSummary; receivables: InvoiceSummary; cash_flow: CashSummary
  monthly_series: MonthlyStatistics[]
}
export interface MonthlyStatistics {
  month: string; invoice_count: number; inconsistent_count: number
  invoice_total: string; invoice_paid: string; invoice_outstanding: string
  cash_income: string; cash_expense: string; cash_net: string
}
export interface OutstandingInvoice {
  id: number; invoice_number: string; occupancy_id: number; billing_month: string; due_date?: string | null; due_status?: string; overdue_days?: number
  room: { id: number; room_number: string }; group: { id: number; group_code: string }
  total_amount: string; paid_amount: string; outstanding_amount: string; payment_status: string
}
export interface ReceivableGroup {
  group_by: 'room' | 'rental_group'; group_id: number; group_label: string; invoice_count: number
  total_amount: string; paid_amount: string; outstanding_amount: string
}

import type { PaymentStatus } from '@/types/billing'
export function paymentStatusLabel(value: PaymentStatus) { return { unpaid: 'Chưa thu', partially_paid: 'Thu một phần', paid: 'Đã thu đủ', inconsistent: 'Cần kiểm tra', cancelled: 'Đã hủy' }[value] ?? 'Cần kiểm tra' }
export function positiveMoney(value: string) { return /^\d+(?:\.\d+)?$/.test(value) && /[1-9]/.test(value) }
export function decimalText(value: string) { const [integer = '0', fraction] = String(value).split('.'); return `${integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.')}${fraction ? `,${fraction}` : ''}` }
export function inputTime(value: string) { return value.replace(' ', 'T') }
export function previousMonth() { const date = new Date(); const parts = new Intl.DateTimeFormat('sv-SE', { timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit' }).format(date); const [year, month] = parts.split('-').map(Number); return month === 1 ? `${year! - 1}-12` : `${year}-${String(month! - 1).padStart(2, '0')}` }

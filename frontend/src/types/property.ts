export const propertyFields = ['name', 'address', 'contact_name', 'phone', 'email', 'bank_name', 'bank_account_number', 'bank_account_name', 'notes'] as const
export type PropertyField = typeof propertyFields[number]
export type PropertyValues = Record<PropertyField, string | null>
export interface RentalProperty extends PropertyValues { configured: boolean; revision: number; version_token: string; updated_at: string | null }

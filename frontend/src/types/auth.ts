export type Role = 'owner' | 'tenant'

export interface User {
  id: number
  username: string
  role: Role
  tenant_id: number | null
  is_active: boolean
}

export interface PasswordChange {
  current_password: string
  password: string
  password_confirmation: string
}

export function isUser(value: unknown): value is User {
  if (!value || typeof value !== 'object') return false
  const user = value as Record<string, unknown>
  return Number.isInteger(user.id) && typeof user.username === 'string'
    && (user.role === 'owner' || user.role === 'tenant') && user.is_active === true
    && (user.tenant_id === null || Number.isInteger(user.tenant_id))
}

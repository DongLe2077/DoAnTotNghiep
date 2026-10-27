import type { Role } from '@/types/auth'

export function getRoleHome(role: Role): string {
  return role === 'owner' ? '/owner' : '/tenant'
}

export function safeRedirect(value: unknown, role: Role): string {
  const home = getRoleHome(role)
  if (typeof value !== 'string' || /[\\\u0000-\u0020]/.test(value)) return home
  if (['/account', '/notifications'].some(path => value === path || value.startsWith(`${path}?`))) return value
  return value === home || value.startsWith(`${home}/`) || value.startsWith(`${home}?`) ? value : home
}

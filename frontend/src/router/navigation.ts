import type { Role } from '@/types/auth'

export function getRoleHome(role: Role): string {
  return role === 'owner' ? '/owner' : '/tenant'
}

export function marketRedirect(value: unknown): string | null {
  if (typeof value !== 'string') return null
  if (value === '/my-room-requests' || value === '/find-rooms') return value
  return /^\/find-rooms\/[1-9][0-9]*(?:\?request=(?:viewing|rent))?$/.test(value) && !/[\r\n]/.test(value) ? value : null
}

export function safeRedirect(value: unknown, role: Role): string {
  const home = getRoleHome(role)
  if (typeof value !== 'string' || /[\\\u0000-\u0020]/.test(value)) return home
  const market = marketRedirect(value)
  if (market) return market
  if (['/account', '/notifications'].some(path => value === path || value.startsWith(`${path}?`))) return value
  return value === home || value.startsWith(`${home}/`) || value.startsWith(`${home}?`) ? value : home
}

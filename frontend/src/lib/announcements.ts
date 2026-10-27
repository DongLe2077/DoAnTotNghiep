import type { AnnouncementDraft, AnnouncementPayload } from '@/types/announcements'
export function announcementDraft(title: string, body: string, audience: 'all' | 'rooms', rooms: number[]): AnnouncementDraft {
  return { title: title.trim(), body: body.replace(/\r\n?/g, '\n').trim(), audience, ...(audience === 'rooms' ? { room_ids: [...new Set(rooms)].sort((a, b) => a - b) } : {}) }
}
export function pendingAnnouncement(key: string): AnnouncementPayload | null {
  try {
    const item: unknown = JSON.parse(sessionStorage.getItem(key) ?? 'null')
    if (!item || typeof item !== 'object') return null
    const p = item as AnnouncementPayload
    if (typeof p.title !== 'string' || !p.title.trim() || p.title.length > 160 || typeof p.body !== 'string' || !p.body.trim() || p.body.length > 8000
      || !['all', 'rooms'].includes(p.audience) || typeof p.preview_token !== 'string' || !/^[a-f0-9]{64}$/.test(p.preview_token)
      || typeof p.idempotency_key !== 'string' || !/^[a-zA-Z0-9][a-zA-Z0-9._:-]{7,63}$/.test(p.idempotency_key)) return null
    if (p.audience === 'rooms' && (!Array.isArray(p.room_ids) || !p.room_ids.length || p.room_ids.length > 100 || !p.room_ids.every(id => Number.isSafeInteger(id) && id > 0))) return null
    return { ...announcementDraft(p.title, p.body, p.audience, p.room_ids ?? []), preview_token: p.preview_token, idempotency_key: p.idempotency_key }
  } catch { return null }
}

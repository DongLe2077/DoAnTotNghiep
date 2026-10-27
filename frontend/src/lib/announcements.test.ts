import { afterEach, describe, expect, it, vi } from 'vitest'
import { announcementDraft, pendingAnnouncement } from './announcements'
import { notificationTarget } from './notifications'
import { isNotification } from '@/types/notifications'
describe('announcement delivery requests', () => {
  afterEach(() => vi.unstubAllGlobals())
  it('normalizes text and sorts unique room identifiers before review and retry', () => {
    expect(announcementDraft(' Lịch nước ', ' Dòng 1\r\nDòng 2\rDòng 3 ', 'rooms', [9, 2, 9])).toEqual({ title: 'Lịch nước', body: 'Dòng 1\nDòng 2\nDòng 3', audience: 'rooms', room_ids: [2, 9] })
    expect(announcementDraft('Tiêu đề', 'Nội dung', 'all', [1, 2])).not.toHaveProperty('room_ids')
  })
  it('recovers exactly the stored send request only for its owner account', () => {
    const request = { title: 'Lịch nước', body: 'Nội dung', audience: 'rooms', room_ids: [2, 9], preview_token: 'a'.repeat(64), idempotency_key: 'announcement-2077' }
    vi.stubGlobal('sessionStorage', { getItem: (key: string) => key === 'owner:1' ? JSON.stringify(request) : null })
    expect(pendingAnnouncement('owner:1')).toEqual(request)
    expect(pendingAnnouncement('owner:2')).toBeNull()
  })
  it('rejects malformed pending content and never replays injected recipient identifiers', () => {
    const valid = { title: 'Lịch nước', body: 'Nội dung', audience: 'all', preview_token: 'a'.repeat(64), idempotency_key: 'announcement-2077' }
    for (const raw of ['bad json', JSON.stringify({ ...valid, title: '' }), JSON.stringify({ ...valid, audience: 'rooms', room_ids: [-1] }), JSON.stringify({ ...valid, preview_token: '' })]) {
      vi.stubGlobal('sessionStorage', { getItem: () => raw }); expect(pendingAnnouncement('key')).toBeNull()
    }
    vi.stubGlobal('sessionStorage', { getItem: () => JSON.stringify({ ...valid, recipient_user_ids: [999], created_by: 999 }) })
    expect(pendingAnnouncement('key')).toEqual(valid)
  })
  it('only routes a valid personal announcement to the tenant detail page', () => {
    const notification = { id: 1, type: 'owner_announcement' as const, data: { title: 'Tiêu đề', body: 'Nội dung' }, invoice_id: null, maintenance_request_id: null, announcement_id: 8, is_read: false, read_at: null, created_at: null }
    expect(isNotification(notification)).toBe(true)
    expect(isNotification({ ...notification, announcement_id: null })).toBe(false)
    expect(notificationTarget(notification, 'tenant')).toEqual({ kind: 'announcement', path: '/tenant/announcements/8' })
    expect(notificationTarget(notification, 'owner')).toBeNull()
  })
})

export interface AnnouncementDraft { title: string; body: string; audience: 'all' | 'rooms'; room_ids?: number[] }
export interface AnnouncementPayload extends AnnouncementDraft { preview_token: string; idempotency_key: string }
export interface AnnouncementRoom { id: number; room_number: string }
export interface AnnouncementPreview { title: string; body: string; audience: 'all' | 'rooms'; rooms: AnnouncementRoom[]; recipient_count: number; can_send: boolean; preview_token: string; recipients: { user_id: number; username: string; full_name: string; room_numbers: string[] }[] }
export interface Announcement { id: number; title: string; body: string; audience: 'all' | 'rooms'; rooms: AnnouncementRoom[]; created_by: string; created_at: string; recipient_count: number; read_count: number; unread_count: number }
export interface AnnouncementRecipient { notification_id: number; user_id: number; username: string; full_name: string; room_numbers: string[]; is_read: boolean; read_at: string | null; is_active: boolean }
export interface TenantAnnouncement { id: number; title: string; body: string; created_at: string; notification_id: number; is_read: boolean; read_at: string | null }

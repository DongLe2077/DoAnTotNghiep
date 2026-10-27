<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AppNotification;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class Announcements
{
    public function query(array $filters = []): Builder
    {
        $query = Announcement::query()->with('createdBy')->withCount(['notifications as recipient_count',
            'notifications as read_count' => fn ($read) => $read->whereNotNull('read_at')]);
        if (isset($filters['audience'])) {
            $query->where('audience', $filters['audience']);
        }
        if (! empty($filters['q'])) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($search) => $search->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("body LIKE ? ESCAPE '!'", [$pattern]));
        }

        return $query;
    }

    public function preview(array $data): array
    {
        return DB::transaction(fn () => $this->calculate($this->values($data)), 3);
    }

    public function publish(array $data, int $actorId): array
    {
        $values = $this->values($data);
        $fingerprint = hash('sha256', json_encode([$actorId, $values, $data['preview_token']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        try {
            return DB::transaction(function () use ($data, $values, $actorId, $fingerprint) {
                $prior = Announcement::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
                if ($prior !== null) {
                    return $this->replay($prior, $fingerprint);
                }
                $preview = $this->calculate($values);
                abort_unless(hash_equals($preview['preview_token'], $data['preview_token']), 409, 'Nội dung hoặc danh sách người nhận đã thay đổi. Hãy xem trước lại trước khi gửi.');
                abort_unless($preview['can_send'], 409, 'Chưa có người thuê đang ở có tài khoản hoạt động trong phạm vi đã chọn.');
                $announcement = Announcement::create($values + ['room_snapshot' => $preview['rooms'], 'created_by' => $actorId,
                    'idempotency_key' => $data['idempotency_key'], 'request_fingerprint' => $fingerprint]);
                $now = now()->startOfSecond();
                foreach (array_chunk($preview['recipients'], 200) as $chunk) {
                    $rows = [];
                    foreach ($chunk as $recipient) {
                        $rows[] = ['user_id' => $recipient['user_id'], 'type' => 'owner_announcement', 'announcement_id' => $announcement->id,
                            'dedupe_key' => hash('sha256', 'announcement:'.$announcement->id.':user:'.$recipient['user_id']),
                            'data' => json_encode(['title' => $values['title'], 'body' => mb_substr($values['body'], 0, 500),
                                'room_numbers' => $recipient['room_numbers']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                            'created_at' => $now];
                    }
                    AppNotification::query()->insert($rows);
                }

                return ['announcement' => $announcement, 'replayed' => false];
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $prior = Announcement::query()->where('idempotency_key', $data['idempotency_key'])->first();
            abort_if($prior === null, 409, 'Không thể ghi nhận lượt gửi. Hãy kiểm tra danh sách thông báo trước khi thử lại.');

            return $this->replay($prior, $fingerprint);
        }
    }

    private function replay(Announcement $announcement, string $fingerprint): array
    {
        abort_unless(hash_equals($announcement->request_fingerprint, $fingerprint), 409, 'Mã yêu cầu đã dùng cho nội dung hoặc người gửi khác.');

        return ['announcement' => $announcement, 'replayed' => true];
    }

    private function values(array $data): array
    {
        $ids = $data['audience'] === 'rooms' ? array_map(intval(...), $data['room_ids']) : [];
        sort($ids, SORT_NUMERIC);

        return ['title' => trim($data['title']), 'body' => trim(str_replace(["\r\n", "\r"], "\n", $data['body'])),
            'audience' => $data['audience'], 'room_ids' => $ids];
    }

    private function calculate(array $values): array
    {
        // Tenancy changes also lock room rows. The locking read then fixes active users/stays.
        $rooms = Room::query()->when($values['audience'] === 'rooms', fn ($query) => $query->whereIn('id', $values['room_ids']))
            ->orderBy('id')->lockForUpdate()->get(['id', 'room_number']);
        $rows = DB::table('users as u')->join('tenants as t', 't.id', '=', 'u.tenant_id')
            ->join('tenant_stays as s', 's.tenant_id', '=', 't.id')->join('occupancies as o', 'o.id', '=', 's.occupancy_id')
            ->where('u.role', 'tenant')->where('u.is_active', true)->whereNull('s.moved_out_at')->whereNull('o.ended_at')
            ->where('s.moved_in_at', '<=', now())->where('o.started_at', '<=', now())->whereIn('o.room_id', $rooms->pluck('id'))
            ->orderBy('u.id')->orderBy('o.room_id')->lockForUpdate()->get(['u.id as user_id', 'u.username', 't.full_name', 'o.room_id']);
        $byId = $rooms->keyBy('id');
        $recipients = [];
        foreach ($rows as $row) {
            $recipients[$row->user_id] ??= ['user_id' => (int) $row->user_id, 'username' => $row->username,
                'full_name' => $row->full_name, 'room_ids' => [], 'room_numbers' => []];
            if (! in_array((int) $row->room_id, $recipients[$row->user_id]['room_ids'], true)) {
                $recipients[$row->user_id]['room_ids'][] = (int) $row->room_id;
                $recipients[$row->user_id]['room_numbers'][] = $byId[$row->room_id]->room_number;
            }
        }
        $recipients = array_values($recipients);
        $snapshot = $rooms->map(fn ($room) => ['id' => $room->id, 'room_number' => $room->room_number])->all();
        $result = ['title' => $values['title'], 'body' => $values['body'], 'audience' => $values['audience'], 'rooms' => $snapshot,
            'recipients' => $recipients, 'recipient_count' => count($recipients), 'can_send' => count($recipients) > 0];
        $result['preview_token'] = hash_hmac('sha256', json_encode([$values, $snapshot, $recipients], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), config('app.key'));

        return $result;
    }
}

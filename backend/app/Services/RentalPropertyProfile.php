<?php

namespace App\Services;

use App\Models\RentalProperty;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RentalPropertyProfile
{
    public const FIELDS = ['name', 'address', 'contact_name', 'phone', 'email', 'bank_name', 'bank_account_number', 'bank_account_name', 'notes'];

    public function current(): ?RentalProperty
    {
        return RentalProperty::query()->find(1);
    }

    public static function version(?RentalProperty $profile): string
    {
        return hash_hmac('sha256', json_encode(['rental_property', $profile?->id, $profile?->revision,
            $profile?->only(self::FIELDS)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), config('app.key'));
    }

    public function save(array $data, int $actorId): RentalProperty
    {
        try {
            return DB::transaction(function () use ($data, $actorId) {
                $actor = User::query()->findOrFail($actorId);
                abort_unless($actor->is_active && $actor->hasRole(User::ROLE_OWNER), 403);
                $profile = RentalProperty::query()->lockForUpdate()->find(1);
                abort_unless(hash_equals(self::version($profile), $data['version_token']), 409,
                    'Thông tin nhà trọ đã thay đổi. Hãy tải lại và đối chiếu trước khi lưu.');
                if ($profile === null) {
                    $profile = new RentalProperty;
                    $profile->id = 1;
                }
                $profile->fill(array_intersect_key($data, array_flip(self::FIELDS)));
                if (! $profile->exists || $profile->isDirty()) {
                    $profile->updated_by = $actorId;
                    $profile->revision = $profile->exists ? $profile->revision + 1 : 1;
                    $profile->save();
                }
                return $profile->refresh();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Thông tin nhà trọ vừa được cấu hình ở nơi khác. Hãy tải lại trước khi lưu.');
        }
    }

    public function data(): array
    {
        $profile = $this->current();
        $values = array_fill_keys(self::FIELDS, null);
        return array_merge($values, $profile?->only(self::FIELDS) ?? [], ['configured' => $profile !== null,
            'revision' => $profile?->revision ?? 0, 'version_token' => self::version($profile),
            'updated_at' => $profile?->updated_at?->format('Y-m-d H:i:s')]);
    }
}

<?php

namespace App\Services;

use App\Services\Tenancy\PropertyContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class LiveUpdates
{
    private const TABLES = [
        'rooms', 'occupancies', 'tenant_stays', 'room_listings', 'room_requests', 'scheduled_deletions',
        'property_memberships', 'property_join_requests', 'invoices', 'invoice_items', 'payments',
        'payment_corrections', 'invoice_access_grants', 'maintenance_requests', 'maintenance_photos',
        'announcements', 'app_notifications', 'cash_transactions', 'cash_transaction_histories', 'checkout_requests',
    ];

    private array $pending = [];

    private array $autocommitted = [];

    public function observe(QueryExecuted $query): void
    {
        if (! preg_match('/^\s*(?:insert(?: or ignore| ignore)? into|update|delete from)\s+["\x60]?([a-z_]+)/i', $query->sql, $match)) {
            return;
        }
        $table = strtolower($match[1]);
        $context = app(PropertyContext::class);
        if (! $context->enabled() || ! in_array($table, self::TABLES, true) || ! Schema::hasTable('live_revisions')) {
            return;
        }
        $property = $context->id();
        $channels = [];
        if ($property !== null) {
            $channels[] = 'property-'.$property;
            if ($table === 'property_memberships') {
                $channels[] = 'membership-'.$property;
            }
        }
        if (in_array($table, ['rooms', 'occupancies', 'tenant_stays', 'room_listings', 'scheduled_deletions'], true)) {
            $channels[] = 'market';
        }
        foreach ($channels as $channel) {
            $this->bump($channel, $query->connection);
        }
    }

    public function user(int $id): void
    {
        if (Schema::hasTable('live_revisions')) {
            $this->bump('user-'.$id, DB::connection());
        }
    }

    private function bump(string $channel, $connection): void
    {
        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit(fn () => $this->record($channel, $connection));
        } else {
            // The driver must finish reading the preceding INSERT's generated ID first.
            $this->autocommitted[$channel] = $connection;
        }
    }

    private function record(string $channel, $connection): void
    {
        try {
            $connection->table('live_revisions')->insertOrIgnore(['channel' => $channel, 'revision' => 0]);
            $connection->table('live_revisions')->where('channel', $channel)->increment('revision');
            $this->pending[$channel] = true;
        } catch (Throwable $error) {
            report($error);
        }
    }

    public function revision(string $channel): string
    {
        return Schema::hasTable('live_revisions')
            ? (string) (DB::table('live_revisions')->where('channel', $channel)->value('revision') ?? 0)
            : '0';
    }

    public function physicalChannel(string $logical): string
    {
        if ($logical === 'market') {
            return 'rentbase-market';
        }
        $epoch = str_starts_with($logical, 'property-') ? $this->revision('membership-'.substr($logical, 9)) : '0';

        return 'private-rentbase-'.hash_hmac('sha256', $logical.':'.$epoch, (string) config('app.key'));
    }

    public function client(): ?array
    {
        $options = config('tenancy.pusher');
        if (! app(PropertyContext::class)->enabled() || empty($options['key']) || empty($options['secret'])
            || ! preg_match('/^[1-9][0-9]*$/D', $options['id'] ?? '')
            || ! preg_match('/^[a-z0-9]+$/D', $options['cluster'] ?? '')) {
            return null;
        }

        return ['key' => $options['key'], 'cluster' => $options['cluster']];
    }

    public function signature(string $socket, string $channel): string
    {
        return config('tenancy.pusher.key').':'.hash_hmac('sha256', $socket.':'.$channel, config('tenancy.pusher.secret'));
    }

    public function flush(): void
    {
        foreach ($this->autocommitted as $channel => $connection) {
            $this->record($channel, $connection);
        }
        $this->autocommitted = [];
        $changes = array_keys($this->pending);
        $this->pending = [];
        if (! $changes || ! $this->client()) {
            return;
        }
        $config = config('tenancy.pusher');
        $channels = array_values(array_unique(array_map($this->physicalChannel(...), array_filter(
            $changes, fn ($channel) => ! str_starts_with($channel, 'membership-'),
        ))));
        $path = '/apps/'.$config['id'].'/events';
        foreach (array_chunk($channels, 100) as $batch) {
            $body = json_encode(['name' => 'changed', 'channels' => $batch, 'data' => '{}'], JSON_THROW_ON_ERROR);
            $query = ['auth_key' => $config['key'], 'auth_timestamp' => (string) time(), 'auth_version' => '1.0', 'body_md5' => md5($body)];
            ksort($query);
            $query['auth_signature'] = hash_hmac('sha256', "POST\n".$path."\n".http_build_query($query, '', '&', PHP_QUERY_RFC3986), $config['secret']);
            try {
                $response = Http::connectTimeout(1)->timeout(2)->withBody($body, 'application/json')
                    ->post('https://api-'.$config['cluster'].'.pusher.com'.$path.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
                if (! $response->successful()) {
                    logger()->warning('Không gửi được tín hiệu đồng bộ.', ['status' => $response->status()]);
                }
            } catch (Throwable) {
                logger()->warning('Không kết nối được dịch vụ đồng bộ.');
            }
        }
    }
}

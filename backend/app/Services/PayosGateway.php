<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use PayOS\Core\HTTPClient;
use PayOS\Crypto\CryptoProvider;
use PayOS\PayOS;
use Psr\Log\NullLogger;

class PayosGateway
{
    private function setting(string $key): mixed
    {
        $context = app(\App\Services\Tenancy\PropertyContext::class);
        if (!$context->enabled()) return config('payos.'.$key);
        $id = $context->requireId();
        $row = \Illuminate\Support\Facades\DB::table('property_payment_settings')->where('property_id', $id)->first();
        if ($row) {
            if ($key === 'enabled') return (bool) $row->enabled;
            $data = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($row->credentials), true, 16, JSON_THROW_ON_ERROR);
            return $data[$key] ?? null;
        }
        $migrated = $id === (int) config('tenancy.legacy_payos_property_id')
            && \Illuminate\Support\Facades\DB::table('property_migration_runs as runs')
                ->join('rental_properties as property', 'property.id', '=', 'runs.property_id')
                ->where('runs.migration_key', 'legacy-property-1')->where('runs.property_id', $id)
                ->whereColumn('runs.owner_user_id', 'property.owner_user_id')->exists();
        return $migrated ? config('payos.'.$key) : null;
    }

    public function configured(): bool
    {
        return filled($this->setting('client_id')) && filled($this->setting('api_key')) && filled($this->setting('checksum_key'));
    }

    public function enabled(): bool
    {
        return $this->setting('enabled') && $this->configured() && $this->origin() !== null;
    }

    public function origin(): ?string
    {
        $url = rtrim((string) config('app.url'), '/');

        return preg_match('~^https://[a-z0-9.-]+(?::[0-9]+)?$~iD', $url) ? $url : null;
    }

    public function create(array $request): array
    {
        return $this->client()->paymentRequests->create($request, ['asArray' => true]);
    }

    public function get(int $orderCode): array
    {
        return $this->client()->paymentRequests->get($orderCode, ['asArray' => true]);
    }

    public function verify(array $data, string $signature): bool
    {
        $expected = (new CryptoProvider)->createSignatureFromObj($data, (string) $this->setting('checksum_key'));

        return $expected !== null && hash_equals($expected, $signature);
    }

    private function client(): PayOS
    {
        $factory = new HttpFactory;

        return new PayOS(
            clientId: $this->setting('client_id'),
            apiKey: $this->setting('api_key'),
            checksumKey: $this->setting('checksum_key'),
            logger: new NullLogger,
            maxRetries: 0,
            httpClient: new HTTPClient(new Client(['timeout' => 7, 'connect_timeout' => 3]), $factory, $factory),
        );
    }
}

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
    public function configured(): bool
    {
        return filled(config('payos.client_id')) && filled(config('payos.api_key')) && filled(config('payos.checksum_key'));
    }

    public function enabled(): bool
    {
        return config('payos.enabled') && $this->configured() && $this->origin() !== null;
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
        $expected = (new CryptoProvider)->createSignatureFromObj($data, (string) config('payos.checksum_key'));

        return $expected !== null && hash_equals($expected, $signature);
    }

    private function client(): PayOS
    {
        $factory = new HttpFactory;

        return new PayOS(
            clientId: config('payos.client_id'),
            apiKey: config('payos.api_key'),
            checksumKey: config('payos.checksum_key'),
            logger: new NullLogger,
            maxRetries: 0,
            httpClient: new HTTPClient(new Client(['timeout' => 7, 'connect_timeout' => 3]), $factory, $factory),
        );
    }
}

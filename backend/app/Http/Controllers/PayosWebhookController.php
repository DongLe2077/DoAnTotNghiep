<?php

namespace App\Http\Controllers;

use App\Services\InvoicePayos;
use App\Services\PayosGateway;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PayosWebhookController extends Controller
{
    public function __invoke(Request $request, PayosGateway $gateway, InvoicePayos $payments): Response
    {
        abort_unless($gateway->configured(), 404);
        $body = $request->getContent();
        abort_if(strlen($body) > 32768, 413);
        // Verify the signed payload exactly as received, before Laravel trims or normalizes request fields.
        $payload = json_decode($body, true, 32);
        abort_unless(is_array($payload) && is_array($payload['data'] ?? null)
            && is_string($payload['signature'] ?? null) && $gateway->verify($payload['data'], $payload['signature']), 400, 'Chữ ký payOS không hợp lệ.');

        $payments->receive($payload['data']);

        return response()->noContent();
    }
}

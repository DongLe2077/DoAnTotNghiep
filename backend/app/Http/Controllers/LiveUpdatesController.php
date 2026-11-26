<?php

namespace App\Http\Controllers;

use App\Services\LiveUpdates;
use App\Services\Tenancy\PropertyContext;
use Illuminate\Http\Request;

class LiveUpdatesController extends Controller
{
    public function __construct(private readonly LiveUpdates $live) {}

    public function market(Request $request)
    {
        return $this->respond($request, 'market');
    }

    public function property(Request $request)
    {
        $context = app(PropertyContext::class);
        abort_unless($context->enabled(), 404);

        return $this->respond($request, 'property-'.$context->requireId());
    }

    public function user(Request $request)
    {
        return $this->respond($request, 'user-'.$request->user('web')->id);
    }

    private function respond(Request $request, string $logical)
    {
        abort_unless(app(PropertyContext::class)->enabled(), 404);
        $fields = $request->validate(['socket' => ['sometimes', 'string', 'regex:/^[0-9]{1,20}\.[0-9]{1,20}$/D']]);
        $client = $this->live->client();
        $channel = $this->live->physicalChannel($logical);
        $auth = $client && isset($fields['socket']) && $logical !== 'market'
            ? $this->live->signature($fields['socket'], $channel) : null;

        return response()->json([
            'revision' => $this->live->revision($logical),
            'client' => $client, 'channel' => $channel, 'auth' => $auth,
        ])->header('Cache-Control', 'private, no-store');
    }
}

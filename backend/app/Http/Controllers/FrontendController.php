<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FrontendController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        abort_unless(config('frontend.enabled'), 404);
        $entry = public_path('index.html');
        abort_unless(is_file($entry), 503, 'Giao diện đang được cập nhật. Vui lòng thử lại sau.');

        return response()->file($entry, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate();
    }
}

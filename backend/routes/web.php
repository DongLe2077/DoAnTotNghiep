<?php

use App\Http\Controllers\FrontendController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (config('frontend.enabled')) {
        return app(FrontendController::class)();
    }

    return view('welcome');
});

Route::get('/{page}', FrontendController::class)
    ->where('page', '(?:login|register|find-rooms(?:/.*)?|my-room-requests|properties|account|notifications|forbidden|connection-error|design|owner(?:/.*)?|tenant(?:/.*)?)');

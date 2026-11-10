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
    ->where('page', '(?:login|account|notifications|forbidden|connection-error|design|owner(?:/.*)?|tenant(?:/.*)?)');

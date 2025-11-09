<?php

use Emon\LarabotAi\Http\Controllers\BotController;
use Illuminate\Support\Facades\Route;

// Build middleware array dynamically based on config
$middleware = config('gemini.route_middleware', ['api', 'bot.rate-limit']);

// Add authentication if required
if (config('gemini.require_auth', false)) {
    $authGuard = config('gemini.auth_guard', 'sanctum');
    array_splice($middleware, 1, 0, ["auth:{$authGuard}"]); // Insert after 'api'
}

// Remove null/empty middleware
$middleware = array_filter($middleware);

Route::prefix('api/bot')
    ->middleware($middleware)
    ->group(function () {
        Route::post('ask', [BotController::class, 'ask'])->name('larabot.ask');
        Route::get('history', [BotController::class, 'history'])->name('larabot.history');
        Route::get('stats', [BotController::class, 'stats'])->name('larabot.stats');
    });


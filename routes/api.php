<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PromoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/promo/claim', [PromoController::class, 'claim'])->middleware('throttle:promo-claim');
    Route::get('/promo/history', [PromoController::class, 'history']);
    Route::patch('/promo/{claimId}/revoke', [PromoController::class, 'revoke'])
        ->whereNumber('claimId')
        ->middleware('throttle:promo-mutation');
});

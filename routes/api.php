<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\TicketPurchaseController;
use App\Http\Middleware\IdempotencyCheck;
use App\Http\Middleware\ReservationRateLimiter;
use Illuminate\Support\Facades\Route;



Route::apiResource('events', EventController::class);
Route::get('/reservations', [ReservationController::class, 'index']);
Route::get('/reservations/{reservation}', [ReservationController::class, 'show']);

Route::controller(TicketPurchaseController::class)->group(function () {
    Route::post('/v1/events/{eventId}/reserve', 'storeNative');

    Route::middleware([IdempotencyCheck::class, ReservationRateLimiter::class])
        ->post('/v2/events/{eventId}/reserve', 'store');
});

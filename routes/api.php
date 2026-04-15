<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\TicketPurchaseController;
use App\Http\Middleware\IdempotencyCheck;
use App\Http\Middleware\ReservationRateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::apiResource('events', EventController::class);
Route::get('/reservations', [ReservationController::class, 'index']);
Route::get('/reservations/{reservation}', [ReservationController::class, 'show']);

Route::middleware([IdempotencyCheck::class, ReservationRateLimiter::class])
    ->post('/events/{eventId}/reserve', [TicketPurchaseController::class, 'store']);

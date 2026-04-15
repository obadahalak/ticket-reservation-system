<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class PurchaseRateLimiter
{

    private const WINDOW_SECONDS = 10;
    public function handle(Request $request, Closure $next): Response
    {
        $userId = auth()->id();
        $ticketId = request()->ticket_id;
        $key = "ticket:{$ticketId}:user:{$userId}";

        $acquired = Redis::set($key, 1, 'EX', self::WINDOW_SECONDS, 'NX');

        if (!$acquired) {
            return response()->json(['error' => 'You have already purchased this ticket'], 422);
        }

        return $next($request);


    }
}

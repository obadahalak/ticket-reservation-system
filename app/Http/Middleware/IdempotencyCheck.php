<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyCheck
{

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header("Idempotency-Key");
        if (!$key) {
            return response()->json(['message' => 'Idempotency-Key header required.'], 422);
        }

        $lockKey = "idempotency:{$key}";

        if (Redis::get($lockKey)) {
            return response()->json(['message' => "your request already handled."], 200);
        }

        $response = $next($request);
        if ($response->status() === 200) {
            Redis::setex($lockKey, 86400, $response->content());
        }
        return $response;
    }
}

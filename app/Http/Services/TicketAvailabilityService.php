<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Redis;

class TicketAvailabilityService
{
    public function seedRedisTickets($eventId, $quantity): void
    {
        ///set if not exists
        Redis::setnx("tickets:event:{$eventId}", $quantity);
    }
    public function attemptReservation($eventId): bool|int
    {
        $remaining = Redis::decr("tickets:event:{$eventId}");

        if ($remaining < 0) {
            Redis::incr("tickets:event:{$eventId}");
            return false;
        }

        return $remaining;
    }
    public function releaseReservation(int $eventId): void
    {
        Redis::incr("tickets:event:{$eventId}");
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Services\TicketAvailabilityService;
use App\Jobs\ProcessReservation;
use Illuminate\Http\Request;


class TicketPurchaseController extends Controller
{
    public function __construct(private TicketAvailabilityService $ticketAvailabilityService) {}

    public function store(int $eventId)
    {
        $remaining = $this->ticketAvailabilityService->attemptReservation($eventId);

        if ($remaining === false) {
            return response()->json([
                'message' => 'Sorry — this event is sold out.',
            ], 422);
        }

        ProcessReservation::dispatch($eventId, request()->user_id);
        return response()->json([
            'message' => 'Your purchase is being processed.',
        ], 200);
    }
}

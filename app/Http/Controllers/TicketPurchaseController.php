<?php

namespace App\Http\Controllers;

use App\Http\Services\TicketAvailabilityService;
use App\Jobs\ProcessReservation;
use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function storeNaive(int $eventId)
    {
        return DB::transaction(function () use ($eventId) {

            $event = Event::lockForUpdate()->find($eventId);


            if ($event->available_tickets <= 0) {
                return response()->json(['message' => 'Sorry — this event is sold out.'], 422);
            }

            $event->decrement('available_tickets');

            Reservation::create([
                'event_id' => $eventId,
                'user_id' => request()->user_id,
            ]);

            return response()->json(['message' => 'Reservation successful!'], 200);
        });
    }
}

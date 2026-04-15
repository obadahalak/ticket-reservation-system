<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Models\Event;
use DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessReservation implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 2;
    public int $backoff = 6;
    public $eventId;
    public $userId;
    /**
     * Create a new job instance.
     */
    public function __construct($eventId, $userId)
    {
        $this->eventId = $eventId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DB::transaction(function () {

            $event = Event::lockForUpdate()->find($this->eventId);

            if ($event->available_tickets <= 0) {
                throw new \Exception("Event is sold out");
            }
            Reservation::create([
                'user_id' => $this->userId,
                'event_id' => $this->eventId,
            ]);
            $event->decrement('available_tickets', 1);


        });
    }
    public function failed($exception)
    {

        Redis::incrby("tickets:event:{$this->eventId}", 1);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Redis;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $events = [
            ['name' => 'Coldplay - Music of the Spheres Tour', 'price' => 120.00, 'available_tickets' => 500],
            ['name' => 'Taylor Swift - Eras Tour', 'price' => 250.00, 'available_tickets' => 1000],
            ['name' => 'FIFA World Cup Final 2026', 'price' => 450.00, 'available_tickets' => 200],
            ['name' => 'Champions League Final - Real Madrid vs Man City', 'price' => 350.00, 'available_tickets' => 300],
            ['name' => 'Ed Sheeran - Mathematics Tour', 'price' => 95.00, 'available_tickets' => 800],
            ['name' => 'Coachella 2026 - Weekend 1', 'price' => 499.00, 'available_tickets' => 1500],
            ['name' => 'Adele - Weekends with Adele', 'price' => 180.00, 'available_tickets' => 400],
            ['name' => 'NBA Finals Game 7 - Lakers vs Celtics', 'price' => 600.00, 'available_tickets' => 150],
            ['name' => 'The Weeknd - After Hours World Tour', 'price' => 110.00, 'available_tickets' => 700],
            ['name' => 'Tomorrowland 2026 - Main Stage', 'price' => 375.00, 'available_tickets' => 2000],
        ];

        foreach ($events as $event) {
            $createdEvent = Event::create($event);
            Redis::set("tickets:event:$createdEvent->id", $createdEvent->available_tickets);
        }
    }
}

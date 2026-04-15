<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessReservation;
use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ReservationController extends Controller
{

    public function index()
    {
        return Reservation::all();
    }
    public function show(Reservation $reservation)
    {
        return $reservation;
    }
}

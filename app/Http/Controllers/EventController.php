<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Http\Requests\StoreEventRequest;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Event::all();
    }

    public function store(StoreEventRequest $request)
    {
        return Event::create($request->validated());
    }

    public function show(Event $event)
    {
        return $event;
    }

    public function update(StoreEventRequest $request, Event $event)
    {
        $event->update($request->validated());
        return $event;
    }

    public function destroy(Event $event)
    {
        $event->delete();
        return response()->noContent();
    }
}

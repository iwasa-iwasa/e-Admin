<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    /**
     * Display the calendar page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $user = Auth::user();

        // Fetch all events the user is a participant in
        $events = Event::with(['creator', 'participants'])->whereHas('participants', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->orderBy('start_date')
        ->get();

        return Inertia::render('Calendar', [
            'events' => $events,
        ]);
    }
}
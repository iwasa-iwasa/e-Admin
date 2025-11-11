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

    /**
     * Store a newly created event in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'is_all_day' => 'required|boolean',
            'date_range' => 'required|array',
            'date_range.start' => 'required|date',
            'date_range.end' => 'required|date|after_or_equal:date_range.start',
            'start_time' => 'nullable|string',
            'end_time' => 'nullable|string',
            'participants' => 'nullable|array',
            'participants.*.id' => 'required|exists:users,id',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string',
            'importance' => 'required|string',
        ]);

        $event = Event::create([
            'title' => $validated['title'],
            'start_date' => $validated['date_range']['start'],
            'end_date' => $validated['date_range']['end'],
            'start_time' => $validated['is_all_day'] ? null : $validated['start_time'],
            'end_time' => $validated['is_all_day'] ? null : $validated['end_time'],
            'is_all_day' => $validated['is_all_day'],
            'location' => $validated['location'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'importance' => $validated['importance'],
            'created_by' => Auth::id(),
        ]);

        if (!empty($validated['participants'])) {
            $participantIds = array_column($validated['participants'], 'id');
            $event->participants()->attach($participantIds);
        }
        
        // Creator should also be a participant
        $event->participants()->attach(Auth::id());

        return redirect()->route('calendar')->with('success', 'Event created successfully.');
    }
}
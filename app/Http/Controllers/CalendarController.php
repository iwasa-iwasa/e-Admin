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

        // Fetch all events from the shared calendar
        $events = Event::with(['creator', 'participants'])
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
            'date_range' => 'required|array|size:2',
            'date_range.*' => 'required|date',
            'is_all_day' => 'required|boolean',
            'start_time' => 'nullable|required_if:is_all_day,false|date_format:H:i',
            'end_time' => 'nullable|required_if:is_all_day,false|date_format:H:i|after:start_time',
            'participants' => 'nullable|array',
            'participants.*' => 'exists:users,id',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|in:会議,MTG,期限,重要,有給,その他',
            'importance' => 'required|string|in:高,中,低',
        ]);

        $event = Event::create([
            'calendar_id' => \App\Models\Calendar::first()->calendar_id,
            'title' => $validated['title'],
            'start_date' => $validated['date_range'][0],
            'end_date' => $validated['date_range'][1],
            'is_all_day' => $validated['is_all_day'],
            'start_time' => $validated['is_all_day'] ? null : $validated['start_time'],
            'end_time' => $validated['is_all_day'] ? null : $validated['end_time'],
            'location' => $validated['location'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'importance' => $validated['importance'],
            'created_by' => auth()->id(),
        ]);

        if (isset($validated['participants'])) {
            $event->participants()->attach($validated['participants']);
        }
        
        // Creator should also be a participant
        $event->participants()->attach(Auth::id());

        return redirect()->back()->with('success', 'Event created successfully.');
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use Inertia\Inertia;
use App\Models\Event;
use App\Models\Calendar;
use App\Models\SharedNote;
use App\Models\TrashItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Enums\EventCategory;
use App\Enums\EventColor;
use App\Services\EventService;
use Carbon\Carbon;

class CalendarController extends Controller
{
    protected $eventService;

    public function __construct(EventService $eventService)
    {
        $this->eventService = $eventService;
    }
    /**
     * Display the calendar page.
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $memberId = $request->query('member_id');
        // Initial load does not fetch events, they are fetched via API
        $events = []; 
        $teamMembers = \App\Models\User::where('is_active', true)->get();

        return Inertia::render('Calendar', [
            'events' => $events,
            'filteredMemberId' => $memberId ? (int)$memberId : null,
            'teamMembers' => $teamMembers,
        ]);
    }

    /**
     * Get events via API with filtering.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEventsApi(Request $request)
    {
        $start = $request->query('start');
        $end = $request->query('end');
        $memberId = $request->query('member_id');
        $searchQuery = $request->query('search_query');
        $genreFilter = $request->query('genre_filter');

        $query = Event::with(['creator', 'participants', 'attachments', 'recurrence']);

        // Date Range Filtering
        if ($start && $end) {
            $startDate = Carbon::parse($start)->format('Y-m-d');
            $endDate = Carbon::parse($end)->format('Y-m-d');
            
            // Events that overlap with the requested range
            $query->where(function($q) use ($startDate, $endDate) {
                $q->where('start_date', '<', $endDate)
                  ->where('end_date', '>=', $startDate);
            });
        }

        // Member Filtering
        if ($memberId) {
            $query->whereHas('participants', function ($q) use ($memberId) {
                $q->where('users.id', $memberId);
            });
        }

        // Genre Filtering
        if ($genreFilter && $genreFilter !== 'all') {
            if ($genreFilter === 'other') {
                // 'other' excludes standard known categories that have specific colors
                $excludedCategories = [
                    EventCategory::MEETING,
                    EventCategory::WORK,
                    EventCategory::VISITOR,
                    EventCategory::BUSINESS_TRIP,
                    EventCategory::VACATION,
                ];
                
                $query->whereNotIn('category', array_map(fn($c) => $c->value, $excludedCategories));
            } else {
                 // specific color filter
                 // find the category that has this color
                 $targetCategory = collect(EventCategory::cases())
                    ->first(fn($c) => $c->color()->value === $genreFilter);
                 
                 if ($targetCategory) {
                     $query->where('category', $targetCategory->value);
                 }
            }
        }

        // Search Query
        if ($searchQuery) {
            $search = strtolower($searchQuery);
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhereHas('creator', function($subQ) use ($search) {
                      $subQ->where('name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('participants', function($subQ) use ($search) {
                      $subQ->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $events = $query->orderBy('start_date')->get();

        return response()->json($events);
    }

    /**
     * Get a single event for API.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $event = Event::with(['creator', 'participants', 'attachments', 'recurrence'])->findOrFail($id);
        return response()->json($event);
    }

    /**
     * Store a newly created event in storage.
     *
     * @param  \App\Http\Requests\StoreEventRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(StoreEventRequest $request)
    {
        $this->eventService->createEvent($request->validated());
        return redirect()->back();
    }

    /**
     * Update the specified event in storage.
     *
     * @param  \App\Http\Requests\UpdateEventRequest  $request
     * @param  \App\Models\Event  $event
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateEventRequest $request, Event $event)
    {
        $this->eventService->updateEvent($event, $request->validated());
        return redirect()->back();
    }

    /**
     * Delete the specified event.
     *
     * @param  \App\Models\Event  $event
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Event $event)
    {
        $this->eventService->deleteEvent($event);
        return redirect()->back();
    }

    /**
     * Get year busy summary API.
     */
    public function getYearSummary(Request $request)
    {
        $year = (int) $request->query('year', date('Y'));
        $calendarId = (int) $request->query('calendar_id', 1);
        $memberId = $request->query('member_id') ? (int) $request->query('member_id') : null;
        
        $summary = $this->eventService->getYearBusySummary($year, $calendarId, $memberId);
        
        return response()->json($summary);
    }

    /**
     * Restore the specified event.
     *
     * @param  int  $eventId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore($eventId)
    {
        $this->eventService->restoreEvent($eventId);
        return redirect()->back();
    }
}

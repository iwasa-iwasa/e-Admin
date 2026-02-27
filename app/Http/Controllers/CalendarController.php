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
use App\Models\CalendarCategoryLabel;

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
        $events = []; 
        $teamMembers = \App\Models\User::where('is_active', true)->get();
        $user = auth()->user();

        return Inertia::render('Calendar', [
            'events' => $events,
            'filteredMemberId' => $memberId ? (int)$memberId : null,
            'teamMembers' => $teamMembers,
            'defaultView' => $user->calendar_default_view ?? 'dayGridMonth',
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

        \Log::info('[CalendarAPI] Request received', [
            'start' => $start,
            'end' => $end,
            'memberId' => $memberId,
            'searchQuery' => $searchQuery,
            'genreFilter' => $genreFilter,
        ]);

        // Get expanded events (including recurring event occurrences)
        $events = $this->eventService->getExpandedEvents(
            $start ?: Carbon::now()->subMonths(1)->format('Y-m-d'),
            $end ?: Carbon::now()->addMonths(1)->format('Y-m-d'),
            $memberId
        );

        \Log::info('[CalendarAPI] Events fetched', [
            'total_events' => count($events),
            'date_range' => [
                'start' => $start,
                'end' => $end,
            ],
            'sample_dates' => array_slice(array_map(fn($e) => $e['start_date'], $events), 0, 5),
        ]);

        // Apply additional filters
        if ($searchQuery) {
            $search = strtolower($searchQuery);
            $events = array_filter($events, function($event) use ($search) {
                return str_contains(strtolower($event['title']), $search) ||
                       str_contains(strtolower($event['description'] ?? ''), $search) ||
                       ($event['creator'] && str_contains(strtolower($event['creator']['name']), $search)) ||
                       ($event['participants'] && collect($event['participants'])->some(fn($p) => str_contains(strtolower($p['name']), $search)));
            });
        }

        if ($genreFilter && $genreFilter !== 'all') {
            if ($genreFilter === 'other') {
                $excludedCategories = [
                    EventCategory::MEETING->value,
                    EventCategory::WORK->value,
                    EventCategory::VISITOR->value,
                    EventCategory::BUSINESS_TRIP->value,
                    EventCategory::VACATION->value,
                ];
                
                $events = array_filter($events, fn($event) => !in_array($event['category'], $excludedCategories));
            } else {
                $targetCategory = collect(EventCategory::cases())
                   ->first(fn($c) => $c->color()->value === $genreFilter);
                
                if ($targetCategory) {
                    $events = array_filter($events, fn($event) => $event['category'] === $targetCategory->value);
                }
            }
        }

        $finalEvents = array_values($events);
        
        \Log::info('[CalendarAPI] Response prepared', [
            'final_count' => count($finalEvents),
        ]);

        return response()->json($finalEvents);
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
        $data = $request->validated();
        $editScope = $request->input('edit_scope');
        $originalDate = $request->input('original_date');
        
        \Log::info('[CalendarController] Event update request', [
            'event_id' => $event->event_id,
            'edit_scope' => $editScope,
            'original_date' => $originalDate
        ]);
        
        // 繰り返し編集の範囲に応じて処理を分岐
        if ($editScope === 'this-only' && $originalDate) {
            // この予定のみ：例外イベントを作成
            $this->eventService->handleRecurrenceException($event->event_id, $originalDate, $data);
        } elseif ($editScope === 'this-and-future' && $originalDate) {
            // この予定以降：繰り返しを分割
            $this->eventService->handleRecurrenceSplit($event->event_id, $originalDate, $data);
        } elseif ($editScope === 'all') {
            // すべての予定：親イベントと子イベントをすべて更新
            $this->eventService->updateAllRecurrenceEvents($event, $data);
        } else {
            // 通常の更新
            $this->eventService->updateEvent($event, $data);
        }
        
        return redirect()->back();
    }

    /**
     * Delete the specified event.
     *
     * @param  \App\Models\Event  $event
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, Event $event)
    {
        $deleteScope = $request->input('delete_scope');
        $originalDate = $request->input('original_date');
        
        if ($deleteScope && $event->recurrence) {
            $this->eventService->handleRecurrenceDelete($event->event_id, $deleteScope, $originalDate);
        } else {
            $this->eventService->deleteEvent($event);
        }
        
        return redirect()->back();
    }

    /**
     * Get calendar category labels API.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategoryLabels()
    {
        $labels = CalendarCategoryLabel::all()->pluck('label', 'category_key');
        return response()->json($labels);
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
        return redirect()->back()->with(['success' => '予定を復元しました。', 'notification_updated' => true]);
    }
    
    /**
     * Show calendar settings page.
     *
     * @return \Inertia\Response
     */
    public function settings()
    {
        $user = auth()->user();
        $categoryLabels = CalendarCategoryLabel::all()->pluck('label', 'category_key');
        
        $settings = [
            'default_view' => $user->calendar_default_view ?? 'dayGridMonth',
            'category_labels' => $categoryLabels,
        ];
        
        return inertia('Calendar/Settings', [
            'settings' => $settings,
        ]);
    }
    
    /**
     * Update calendar settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'default_view' => 'required|in:yearView,dayGridMonth,timeGridWeek,timeGridDay',
            'category_labels' => 'sometimes|array',
            'category_labels.*' => 'string|max:100',
        ]);
        
        $user = auth()->user();
        $user->calendar_default_view = $validated['default_view'];
        $user->save();
        
        // 管理者のみカテゴリーラベルを更新可能
        if ($user->role === 'admin' && isset($validated['category_labels'])) {
            foreach ($validated['category_labels'] as $key => $label) {
                CalendarCategoryLabel::where('category_key', $key)->update(['label' => $label]);
            }
        }
        
        return redirect()->route('dashboard')->with('success', '設定を保存しました');
    }
}

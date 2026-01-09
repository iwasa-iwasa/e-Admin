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
use Carbon\Carbon;

class CalendarController extends Controller
{
    /**
     * Display the calendar page.
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $memberId = $request->query('member_id');

        // Build query for events
        $eventsQuery = Event::with(['creator', 'participants', 'attachments'])
            ->orderBy('start_date');

        // If a member_id is provided in the URL, filter events to those
        // where the member is a participant.
        if ($memberId) {
            $eventsQuery->whereHas('participants', function ($q) use ($memberId) {
                $q->where('users.id', $memberId);
            });
        }

        $events = $eventsQuery->get();
        $teamMembers = \App\Models\User::where('is_active', true)->get();

        return Inertia::render('Calendar', [
            'events' => $events,
            'filteredMemberId' => $memberId ? (int)$memberId : null,
            'teamMembers' => $teamMembers,
        ]);
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
        $validated = $request->validated();

        $event = Event::create([
            'calendar_id' => Calendar::first()->calendar_id,
            'title' => $validated['title'],
            'start_date' => Carbon::parse($validated['date_range'][0])->format('Y-m-d'),
            'end_date' => Carbon::parse($validated['date_range'][1])->format('Y-m-d'),
            'is_all_day' => $validated['is_all_day'],
            'start_time' => $validated['is_all_day'] ? null : $validated['start_time'],
            'end_time' => $validated['is_all_day'] ? null : $validated['end_time'],
            'location' => $validated['location'],
            'description' => $validated['description'],
            'url' => $validated['url'] ?? null,
            'category' => $validated['category'],
            'importance' => $validated['importance'],
            'progress' => $validated['progress'] ?? 0,
            'created_by' => auth()->id(),
        ]);

        $this->handleRecurrence($event, $validated);
        $this->handleAttachments($event, $validated);

        if (isset($validated['participants'])) {
            $event->participants()->attach($validated['participants']);
        }

        $this->syncSharedNote($event, $validated);

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
        $validated = $request->validated();

        $event->update([
            'title' => $validated['title'],
            'start_date' => Carbon::parse($validated['date_range'][0])->format('Y-m-d'),
            'end_date' => Carbon::parse($validated['date_range'][1])->format('Y-m-d'),
            'is_all_day' => $validated['is_all_day'],
            'start_time' => $validated['is_all_day'] ? null : $validated['start_time'],
            'end_time' => $validated['is_all_day'] ? null : $validated['end_time'],
            'location' => $validated['location'],
            'description' => $validated['description'],
            'url' => $validated['url'] ?? null,
            'category' => $validated['category'],
            'importance' => $validated['importance'],
            'progress' => $validated['progress'] ?? 0,
        ]);

        if (isset($validated['participants'])) {
            $event->participants()->sync($validated['participants']);
        }

        $this->handleAttachments($event, $validated);
        $this->handleDeletedAttachments($event, $validated);
        
        // Recurrence update logic is omitted as per original code structure, 
        // assuming it's not supported in update or handled elsewhere if needed.

        $this->syncSharedNote($event, $validated);

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
        // Create trash item
        TrashItem::create([
            'user_id' => auth()->id(),
            'item_type' => 'event',
            'is_shared' => true,
            'item_id' => $event->event_id,
            'original_title' => $event->title,
            'deleted_at' => now(),
            'permanent_delete_at' => now()->addDays(30),
        ]);

        $event->delete();

        return redirect()->back();
    }

    /**
     * Restore the specified event.
     *
     * @param  int  $eventId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore($eventId)
    {
        $event = Event::withTrashed()->findOrFail($eventId);
        $event->restore();

        // Remove from trash
        TrashItem::where('item_type', 'event')
            ->where('item_id', $eventId)
            ->where('user_id', auth()->id())
            ->delete();

        return redirect()->back();
    }

    /**
     * Handle recurrence creation.
     */
    private function handleRecurrence(Event $event, array $validated): void
    {
        if (isset($validated['recurrence']) && $validated['recurrence']['is_recurring']) {
            $event->recurrence()->create([
                'recurrence_type' => $validated['recurrence']['recurrence_type'],
                'recurrence_interval' => $validated['recurrence']['recurrence_interval'],
                'by_day' => $validated['recurrence']['by_day'] ?? null,
                'by_set_pos' => $validated['recurrence']['by_set_pos'] ?? null,
                'end_date' => $validated['recurrence']['end_date'] ?? null,
            ]);
        }
    }

    /**
     * Handle new attachments.
     */
    private function handleAttachments(Event $event, array $validated): void
    {
        if (isset($validated['attachments']['new_files'])) {
            foreach ($validated['attachments']['new_files'] as $file) {
                $path = $file->store('attachments', 'public');
                $event->attachments()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }
    }

    /**
     * Handle deleted attachments.
     */
    private function handleDeletedAttachments(Event $event, array $validated): void
    {
        if (isset($validated['attachments']['removed_ids'])) {
            $attachmentsToDelete = $event->attachments()
                ->whereIn('attachment_id', $validated['attachments']['removed_ids'])
                ->get();
                
            foreach ($attachmentsToDelete as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
                $attachment->delete();
            }
        }
    }

    /**
     * Sync shared note with event.
     */
    private function syncSharedNote(Event $event, array $validated): void
    {
        // Skip if description is empty and no linked note exists (for create) or update
        // But for update we need to check if note exists to update it.
        
        $sharedNote = SharedNote::where('linked_event_id', $event->event_id)->first();

        // Common data preparation
        $deadlineDate = Carbon::parse($validated['date_range'][1])->format('Y-m-d');
        $deadlineTime = $validated['is_all_day'] ? '23:59:00' : $validated['end_time'];
        
        // Priority mapping
        $priority = match ($validated['importance']) {
            Event::IMPORTANCE_HIGH => 'high',
            Event::IMPORTANCE_MEDIUM => 'medium',
            default => 'low',
        };

        // Color mapping using Event constants
        $color = Event::CATEGORY_COLORS[$validated['category']] ?? Event::COLOR_BLUE;

        if ($sharedNote) {
            // Update existing note
            $sharedNote->update([
                'title' => $validated['title'],
                'content' => $validated['description'],
                'priority' => $priority,
                'color' => $color,
                'deadline_date' => $deadlineDate,
                'deadline_time' => $deadlineTime,
            ]);
            
            if (isset($validated['participants'])) {
                $sharedNote->participants()->sync($validated['participants']);
            }
        } elseif (!empty($validated['description'])) {
            // Create new note
            $sharedNote = SharedNote::create([
                'title' => $validated['title'],
                'content' => $validated['description'],
                'priority' => $priority,
                'color' => $color,
                'author_id' => $event->created_by, // Use creator of event
                'linked_event_id' => $event->event_id,
                'deadline_date' => $deadlineDate,
                'deadline_time' => $deadlineTime,
            ]);
            
            if (isset($validated['participants'])) {
                $sharedNote->participants()->attach($validated['participants']);
            }
        }
    }
}
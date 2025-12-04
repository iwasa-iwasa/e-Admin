<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Event;
use App\Models\EventAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
        $user = Auth::user();

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

        return Inertia::render('Calendar', [
            'events' => $events,
            'filteredMemberId' => $memberId ? (int)$memberId : null,
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
            'url' => 'nullable|url|max:500',
            'category' => 'required|string|in:会議,休暇,業務,重要,来客,出張',
            'importance' => 'required|string|in:重要,中,低',
            'progress' => 'nullable|integer|min:0|max:100',
            'recurrence' => 'nullable|array',
            'recurrence.is_recurring' => 'boolean',
            'recurrence.recurrence_type' => 'required_if:recurrence.is_recurring,true|string|in:daily,weekly,monthly,yearly',
            'recurrence.recurrence_interval' => 'required_if:recurrence.is_recurring,true|integer|min:1',
            'recurrence.by_day' => 'nullable|array',
            'recurrence.by_day.*' => [Rule::in(['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'])],
            'recurrence.by_set_pos' => 'nullable|integer',
            'recurrence.end_date' => 'nullable|date|after_or_equal:end_date',
            'attachments' => 'nullable|array',
            'attachments.new_files' => 'nullable|array',
            'attachments.new_files.*' => 'file|max:10240', // 10MB max
        ]);

        $event = Event::create([
            'calendar_id' => \App\Models\Calendar::first()->calendar_id,
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

        if (isset($validated['recurrence']) && $validated['recurrence']['is_recurring']) {
            $event->recurrence()->create([
                'recurrence_type' => $validated['recurrence']['recurrence_type'],
                'recurrence_interval' => $validated['recurrence']['recurrence_interval'],
                'by_day' => $validated['recurrence']['by_day'] ?? null,
                'by_set_pos' => $validated['recurrence']['by_set_pos'] ?? null,
                'end_date' => $validated['recurrence']['end_date'] ?? null,
            ]);
        }
//...

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

        if (isset($validated['participants'])) {
            $event->participants()->attach($validated['participants']);
        }

        // Save to shared notes if description exists
        if (!empty($validated['description'])) {
            $categoryColorMap = [
                '会議' => 'blue',
                '業務' => 'green',
                '来客' => 'yellow',
                '出張' => 'purple',
                '休暇' => 'pink',
            ];
            
            $sharedNote = \App\Models\SharedNote::create([
                'title' => $validated['title'],
                'content' => $validated['description'],
                'priority' => $validated['importance'] === '重要' ? 'high' : ($validated['importance'] === '中' ? 'medium' : 'low'),
                'color' => $categoryColorMap[$validated['category']] ?? 'blue',
                'author_id' => auth()->id(),
                'linked_event_id' => $event->event_id,
                'deadline_date' => Carbon::parse($validated['date_range'][1])->format('Y-m-d'),
                'deadline_time' => $validated['is_all_day'] ? '23:59:00' : $validated['end_time'],
            ]);
            
            // Add participants to shared note
            if (isset($validated['participants'])) {
                $sharedNote->participants()->attach($validated['participants']);
            }
        }

        return redirect()->back();
        
    }

    /**
     * Update the specified event in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Event  $event
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Event $event)
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
            'url' => 'nullable|url|max:500',
            'category' => 'required|string|in:会議,休暇,業務,重要,来客,出張',
            'importance' => 'required|string|in:重要,中,低',
            'progress' => 'nullable|integer|min:0|max:100',
            'attachments' => 'nullable|array',
            'attachments.new_files' => 'nullable|array',
            'attachments.new_files.*' => 'file|max:10240', // 10MB max
            'attachments.removed_ids' => 'nullable|array',
            'attachments.removed_ids.*' => 'integer',
        ]);

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

        // Update participants
        if (isset($validated['participants'])) {
            $event->participants()->sync($validated['participants']);
        }

        // Handle new attachments
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

        // Handle removed attachments
        if (isset($validated['attachments']['removed_ids'])) {
            $attachmentsToDelete = $event->attachments()->whereIn('attachment_id', $validated['attachments']['removed_ids'])->get();
            foreach ($attachmentsToDelete as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
                $attachment->delete();
            }
        }

        // Update linked shared note
        $sharedNote = \App\Models\SharedNote::where('linked_event_id', $event->event_id)->first();
        
        if ($sharedNote) {
            $categoryColorMap = [
                '会議' => 'blue',
                '業務' => 'green',
                '来客' => 'yellow',
                '出張' => 'purple',
                '休暇' => 'pink',
            ];
            
            $sharedNote->update([
                'title' => $validated['title'],
                'content' => $validated['description'],
                'priority' => $validated['importance'] === '重要' ? 'high' : ($validated['importance'] === '中' ? 'medium' : 'low'),
                'color' => $categoryColorMap[$validated['category']] ?? 'blue',
                'deadline_date' => Carbon::parse($validated['date_range'][1])->format('Y-m-d'),
                'deadline_time' => $validated['is_all_day'] ? '23:59:00' : $validated['end_time'],
            ]);
            
            if (isset($validated['participants'])) {
                $sharedNote->participants()->sync($validated['participants']);
            }
        } elseif (!empty($validated['description'])) {
            // Create new linked note if description was added
            $categoryColorMap = [
                '会議' => 'blue',
                '業務' => 'green',
                '来客' => 'yellow',
                '出張' => 'purple',
                '休暇' => 'pink',
            ];
            
            $sharedNote = \App\Models\SharedNote::create([
                'title' => $validated['title'],
                'content' => $validated['description'],
                'priority' => $validated['importance'] === '重要' ? 'high' : ($validated['importance'] === '中' ? 'medium' : 'low'),
                'color' => $categoryColorMap[$validated['category']] ?? 'blue',
                'author_id' => $event->created_by,
                'linked_event_id' => $event->event_id,
                'deadline_date' => Carbon::parse($validated['date_range'][1])->format('Y-m-d'),
                'deadline_time' => $validated['is_all_day'] ? '23:59:00' : $validated['end_time'],
            ]);
            
            if (isset($validated['participants'])) {
                $sharedNote->participants()->attach($validated['participants']);
            }
        }

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
        \App\Models\TrashItem::create([
            'user_id' => auth()->id(),
            'item_type' => 'event',
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
        \App\Models\TrashItem::where('item_type', 'event')
            ->where('item_id', $eventId)
            ->where('user_id', auth()->id())
            ->delete();

        return redirect()->back();
    }
}
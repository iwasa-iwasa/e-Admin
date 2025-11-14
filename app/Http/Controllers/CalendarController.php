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
    public function index()
    {
        $user = Auth::user();

        // Fetch all events from the shared calendar
        $events = Event::with(['creator', 'participants', 'recurrence', 'attachments'])
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
            'url' => 'nullable|url|max:500',
            'category' => 'required|string|in:会議,MTG,期限,重要,有給,業務,その他',
            'importance' => 'required|string|in:高,中,低',
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
        
        // Creator should also be a participant
        $event->participants()->attach(Auth::id());

        return redirect()->back()->with('success', 'Event created successfully.');
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
            'category' => 'required|string|in:会議,MTG,期限,重要,有給,業務,その他',
            'importance' => 'required|string|in:高,中,低',
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
            'attachments.removed_ids' => 'nullable|array',
            'attachments.removed_ids.*' => 'integer|exists:event_attachments,attachment_id',
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
        ]);

        if (isset($validated['recurrence']) && $validated['recurrence']['is_recurring']) {
            $event->recurrence()->updateOrCreate(
                ['event_id' => $event->event_id],
                [
                    'recurrence_type' => $validated['recurrence']['recurrence_type'],
                    'recurrence_interval' => $validated['recurrence']['recurrence_interval'],
                    'by_day' => $validated['recurrence']['by_day'] ?? null,
                    'by_set_pos' => $validated['recurrence']['by_set_pos'] ?? null,
                    'end_date' => $validated['recurrence']['end_date'] ?? null,
                ]
            );
        } else {
            $event->recurrence()->delete();
        }

        // Handle attachments
        if (isset($validated['attachments'])) {
            // Handle removed attachments
            if (!empty($validated['attachments']['removed_ids'])) {
                $attachmentsToDelete = EventAttachment::whereIn('attachment_id', $validated['attachments']['removed_ids'])->get();
                foreach ($attachmentsToDelete as $attachment) {
                    // Optional: Add authorization check here
                    Storage::disk('public')->delete($attachment->file_path);
                    $attachment->delete();
                }
            }

            // Handle new attachments
            if (!empty($validated['attachments']['new_files'])) {
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

        $participantIds = $validated['participants'] ?? [];
        // Ensure the creator is always a participant
        if (!in_array($event->created_by, $participantIds)) {
            $participantIds[] = $event->created_by;
        }

        $event->participants()->sync($participantIds);

        return redirect()->back()->with('success', 'Event updated successfully.');
    }
}
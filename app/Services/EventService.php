<?php

namespace App\Services;

use App\Enums\EventCategory;
use App\Enums\EventColor;
use App\Enums\EventImportance;
use App\Models\Event;
use App\Models\Calendar;
use App\Models\SharedNote;
use App\Models\TrashItem;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class EventService
{
    /**
     * Get year busy summary for heatmap visualization.
     */
    public function getYearBusySummary(int $year, int $calendarId, ?int $memberId = null): array
    {
        $startDate = Carbon::create($year, 1, 1)->format('Y-m-d');
        $endDate = Carbon::create($year, 12, 31)->format('Y-m-d');
        
        $query = Event::with(['participants'])
            ->where('calendar_id', $calendarId)
            ->where(function($q) use ($startDate, $endDate) {
                $q->where('start_date', '<=', $endDate)
                  ->where('end_date', '>=', $startDate);
            });
            
        if ($memberId) {
            $query->whereHas('participants', function($q) use ($memberId) {
                $q->where('users.id', $memberId);
            });
        }
        
        $events = $query->get();
        $days = [];
        
        foreach ($events as $event) {
            $eventDays = $this->expandEventToDays($event, $startDate, $endDate);
            
            foreach ($eventDays as $dayData) {
                $date = $dayData['date'];
                $duration = $dayData['duration'];
                
                if (!isset($days[$date])) {
                    $days[$date] = [
                        'busyScore' => 0,
                        'totalHours' => 0,
                        'eventCount' => 0,
                        'alldayCount' => 0,
                        'importantCount' => 0,
                    ];
                }
                
                $category = $event->category;
                $importance = $event->importance;
                
                if ($category && $importance) {
                    $eventScore = $duration * $category->busyWeight() * $importance->busyWeight();
                    $days[$date]['busyScore'] += $eventScore;
                }
                
                // 休暇以外の予定のみ拘束時間に含める
                if ($category !== EventCategory::VACATION) {
                    $days[$date]['totalHours'] += $duration;
                }
                
                // 休暇以外の予定のみカウント
                if ($category !== EventCategory::VACATION) {
                    $days[$date]['eventCount']++;
                    // 終日予定のカウント
                    if ($event->is_all_day) {
                        $days[$date]['alldayCount']++;
                    }
                }
                
                if ($importance === EventImportance::HIGH) {
                    $days[$date]['importantCount']++;
                }
            }
        }
        
        return [
            'year' => $year,
            'calendar_id' => $calendarId,
            'days' => $days,
        ];
    }
    
    /**
     * Expand multi-day event to daily breakdown.
     */
    private function expandEventToDays(Event $event, string $yearStart, string $yearEnd): array
    {
        $eventStart = max($event->start_date, $yearStart);
        $eventEnd = min($event->end_date, $yearEnd);
        
        $start = Carbon::parse($eventStart);
        $end = Carbon::parse($eventEnd);
        $days = [];
        
        while ($start <= $end) {
            $date = $start->format('Y-m-d');
            
            if ($event->is_all_day) {
                $duration = 8; // 終日 = 8時間
            } else {
                $duration = $this->calculateDayDuration($event, $date);
            }
            
            $days[] = [
                'date' => $date,
                'duration' => $duration,
            ];
            
            $start->addDay();
        }
        
        return $days;
    }
    
    /**
     * Calculate duration for specific date.
     */
    private function calculateDayDuration(Event $event, string $date): float
    {
        $eventStartDate = $event->start_date;
        $eventEndDate = $event->end_date;
        
        // 単日イベント
        if ($eventStartDate === $eventEndDate && $eventStartDate === $date) {
            if (!$event->start_time || !$event->end_time) return 0;
            
            $start = Carbon::parse($date . ' ' . $event->start_time);
            $end = Carbon::parse($date . ' ' . $event->end_time);
            return $start->diffInHours($end, true);
        }
        
        // 複数日イベントの初日
        if ($eventStartDate === $date && $event->start_time) {
            $start = Carbon::parse($date . ' ' . $event->start_time);
            $endOfDay = Carbon::parse($date . ' 23:59:59');
            return $start->diffInHours($endOfDay, true);
        }
        
        // 複数日イベントの最終日
        if ($eventEndDate === $date && $event->end_time) {
            $startOfDay = Carbon::parse($date . ' 00:00:00');
            $end = Carbon::parse($date . ' ' . $event->end_time);
            return $startOfDay->diffInHours($end, true);
        }
        
        // 複数日イベントの中日
        return 24;
    }
    /**
     * Create a new event.
     */
    public function createEvent(array $data)
    {
        $event = Event::create([
            'calendar_id' => Calendar::first()->calendar_id,
            'title' => $data['title'],
            'start_date' => Carbon::parse($data['date_range'][0])->format('Y-m-d'),
            'end_date' => Carbon::parse($data['date_range'][1])->format('Y-m-d'),
            'is_all_day' => $data['is_all_day'],
            'start_time' => $data['is_all_day'] ? null : $data['start_time'],
            'end_time' => $data['is_all_day'] ? null : $data['end_time'],
            'location' => $data['location'],
            'description' => $data['description'],
            'url' => $data['url'] ?? null,
            'category' => $data['category'],
            'importance' => $data['importance'],
            'progress' => $data['progress'] ?? 0,
            'created_by' => auth()->id(),
        ]);

        $this->handleRecurrence($event, $data);
        $this->handleAttachments($event, $data);

        if (isset($data['participants'])) {
            $event->participants()->attach($data['participants']);
        }

        $this->syncSharedNote($event, $data);

        return $event;
    }

    /**
     * Update an existing event.
     */
    public function updateEvent(Event $event, array $data)
    {
        $event->update([
            'title' => $data['title'],
            'start_date' => Carbon::parse($data['date_range'][0])->format('Y-m-d'),
            'end_date' => Carbon::parse($data['date_range'][1])->format('Y-m-d'),
            'is_all_day' => $data['is_all_day'],
            'start_time' => $data['is_all_day'] ? null : $data['start_time'],
            'end_time' => $data['is_all_day'] ? null : $data['end_time'],
            'location' => $data['location'],
            'description' => $data['description'],
            'url' => $data['url'] ?? null,
            'category' => $data['category'],
            'importance' => $data['importance'],
            'progress' => $data['progress'] ?? 0,
        ]);

        if (isset($data['participants'])) {
            $event->participants()->sync($data['participants']);
        }

        $this->handleAttachments($event, $data);
        $this->handleDeletedAttachments($event, $data);
        
        // Assuming recurrence update logic isn't fully implemented yet as per original code

        $this->syncSharedNote($event, $data);

        return $event;
    }

    /**
     * Delete an event (move to trash).
     */
    public function deleteEvent(Event $event)
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
    }

    /**
     * Restore a deleted event.
     */
    public function restoreEvent($eventId)
    {
        $event = Event::withTrashed()->findOrFail($eventId);
        $event->restore();

        // Remove from trash
        TrashItem::where('item_type', 'event')
            ->where('item_id', $eventId)
            ->where('user_id', auth()->id())
            ->delete();

        return $event;
    }

    /**
     * Handle recurrence creation.
     */
    protected function handleRecurrence(Event $event, array $data): void
    {
        if (isset($data['recurrence']) && $data['recurrence']['is_recurring']) {
            $event->recurrence()->create([
                'recurrence_type' => $data['recurrence']['recurrence_type'],
                'recurrence_interval' => $data['recurrence']['recurrence_interval'],
                'by_day' => $data['recurrence']['by_day'] ?? null,
                'by_set_pos' => $data['recurrence']['by_set_pos'] ?? null,
                'end_date' => $data['recurrence']['end_date'] ?? null,
            ]);
        }
    }

    /**
     * Handle new attachments.
     */
    protected function handleAttachments(Event $event, array $data): void
    {
        if (isset($data['attachments']['new_files'])) {
            foreach ($data['attachments']['new_files'] as $file) {
                // $file is expected to be an UploadedFile instance
                // But in Service, we might receive clean data. 
                // However, Laravel Controller passes Request data which includes UploadedFile.
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
    protected function handleDeletedAttachments(Event $event, array $data): void
    {
        if (isset($data['attachments']['removed_ids'])) {
            $attachmentsToDelete = $event->attachments()
                ->whereIn('attachment_id', $data['attachments']['removed_ids'])
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
    protected function syncSharedNote(Event $event, array $data): void
    {
        $sharedNote = SharedNote::where('linked_event_id', $event->event_id)->first();

        // Common data preparation
        $deadlineDate = Carbon::parse($data['date_range'][1])->format('Y-m-d');
        $deadlineTime = $data['is_all_day'] ? '23:59:00' : $data['end_time'];
        
        // Priority mapping
        $priority = match (EventImportance::tryFrom($data['importance'])) {
            EventImportance::HIGH => 'high',
            EventImportance::MEDIUM => 'medium',
            default => 'low',
        };

        // Color mapping using Event constants
        $category = EventCategory::tryFrom($data['category']);
        $color = $category ? $category->color()->value : EventColor::BLUE->value;

        if ($sharedNote) {
            // Update existing note
            $sharedNote->update([
                'title' => $data['title'],
                'content' => $data['description'],
                'priority' => $priority,
                'color' => $color,
                'deadline_date' => $deadlineDate,
                'deadline_time' => $deadlineTime,
            ]);
            
            if (isset($data['participants'])) {
                $sharedNote->participants()->sync($data['participants']);
            }
        } elseif (!empty($data['description'])) {
            // Create new note
            $sharedNote = SharedNote::create([
                'title' => $data['title'],
                'content' => $data['description'],
                'priority' => $priority,
                'color' => $color,
                'author_id' => $event->created_by,
                'linked_event_id' => $event->event_id,
                'deadline_date' => $deadlineDate,
                'deadline_time' => $deadlineTime,
            ]);
            
            if (isset($data['participants'])) {
                $sharedNote->participants()->attach($data['participants']);
            }
        }
    }
}

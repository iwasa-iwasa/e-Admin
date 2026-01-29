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
     * Get expanded events with exceptions for specified date range.
     */
    public function getExpandedEvents(string $startDate, string $endDate, ?int $memberId = null): array
    {
        // ベースクエリを構築
        $query = Event::with(['creator', 'participants', 'attachments', 'recurrence'])
            ->where('is_exception', false); // 例外イベントは除外
            
        if ($memberId) {
            $query->where(function($q) use ($memberId) {
                $q->whereHas('participants', function ($subQ) use ($memberId) {
                    $subQ->where('users.id', $memberId);
                })->orWhere('created_by', $memberId);
            });
        }
        
        // 全イベントを取得してからフィルタリング
        $events = $query->get();
        $expandedEvents = [];
        
        foreach ($events as $event) {
            if ($event->recurrence) {
                // 繰り返しイベントを展開
                $expandedEvents = array_merge($expandedEvents, $this->expandRecurringEvent($event, $startDate, $endDate));
            } else {
                // 通常イベントの期間チェック
                if ($event->start_date <= $endDate && $event->end_date >= $startDate) {
                    $expandedEvents[] = $this->formatExpandedEvent($event, $event->event_id);
                }
            }
        }
        
        // 例外イベントを追加
        $exceptionEvents = $this->getExceptionEvents($startDate, $endDate, $memberId);
        $expandedEvents = array_merge($expandedEvents, $exceptionEvents);
        
        return $expandedEvents;
    }
    
    /**
     * Expand recurring event into individual occurrences.
     */
    private function expandRecurringEvent(Event $event, string $startDate, string $endDate): array
    {
        $occurrences = [];
        $recurrence = $event->recurrence;
        
        $rangeStart = Carbon::parse($startDate);
        $rangeEnd = Carbon::parse($endDate);
        
        $current = Carbon::parse($event->start_date);
        $interval = $recurrence->recurrence_interval;
        $maxOccurrences = 1000; // 無限ループ防止
        $count = 0;
        
        // 例外日付を取得
        $exceptionDates = $this->getExceptionDates($event->event_id);
        
        while ($current <= $rangeEnd && $count < $maxOccurrences) {
            // 終了日が設定されている場合はチェック
            if ($recurrence->end_date && $current > Carbon::parse($recurrence->end_date)) {
                break;
            }
            
            // 期間内かつ例外日でない場合のみ追加
            if ($current >= $rangeStart && !in_array($current->toDateString(), $exceptionDates)) {
                $occurrenceId = $event->event_id * 10000 + $current->format('md');
                $occurrence = $this->formatExpandedEvent($event, $occurrenceId, $current->toDateString());
                $occurrence['originalEventId'] = $event->event_id;
                $occurrence['isRecurring'] = true;
                $occurrences[] = $occurrence;
            }
            
            switch ($recurrence->recurrence_type) {
                case 'daily':
                    $current->addDays($interval);
                    break;
                case 'weekly':
                    if (!empty($recurrence->by_day)) {
                        $current = $this->getNextWeeklyOccurrence($current, $recurrence->by_day, $interval);
                    } else {
                        $current->addWeeks($interval);
                    }
                    break;
                case 'monthly':
                    $current->addMonths($interval);
                    break;
                case 'yearly':
                    $current->addYears($interval);
                    break;
                default:
                    break 2;
            }
            
            $count++;
        }
        
        return $occurrences;
    }
    
    /**
     * Get next weekly occurrence based on specified days.
     */
    private function getNextWeeklyOccurrence(Carbon $current, array $byDay, int $interval): Carbon
    {
        $dayMap = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];
        $targetDays = array_map(fn($day) => $dayMap[$day] ?? 1, $byDay);
        sort($targetDays);
        
        $currentDayOfWeek = $current->dayOfWeek;
        $nextDay = null;
        
        foreach ($targetDays as $day) {
            if ($day > $currentDayOfWeek) {
                $nextDay = $day;
                break;
            }
        }
        
        if ($nextDay === null) {
            $current->addWeeks($interval);
            $nextDay = $targetDays[0];
        }
        
        $current->startOfWeek()->addDays($nextDay);
        return $current;
    }
    
    /**
     * Get exception dates for a recurring event.
     */
    private function getExceptionDates(int $eventId): array
    {
        return Event::where('parent_event_id', $eventId)
            ->where('is_exception', true)
            ->pluck('original_date')
            ->map(fn($date) => Carbon::parse($date)->toDateString())
            ->toArray();
    }
    
    /**
     * Get exception events for specified date range.
     */
    private function getExceptionEvents(string $startDate, string $endDate, ?int $memberId = null): array
    {
        $query = Event::with(['creator', 'participants', 'attachments'])
            ->where('is_exception', true)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);
            
        if ($memberId) {
            $query->where(function($q) use ($memberId) {
                $q->whereHas('participants', function ($subQ) use ($memberId) {
                    $subQ->where('users.id', $memberId);
                })->orWhere('created_by', $memberId);
            });
        }
        
        $exceptionEvents = $query->get();
        $expandedEvents = [];
        
        foreach ($exceptionEvents as $event) {
            $expandedEvent = $this->formatExpandedEvent($event, $event->event_id);
            $expandedEvent['originalEventId'] = $event->parent_event_id;
            $expandedEvent['isRecurring'] = true;
            $expandedEvent['isException'] = true;
            $expandedEvents[] = $expandedEvent;
        }
        
        return $expandedEvents;
    }
    private function formatExpandedEvent(Event $event, int $id, ?string $overrideDate = null): array
    {
        $startDate = $overrideDate ?? $event->start_date;
        $endDate = $overrideDate ?? $event->end_date;
        
        return [
            'id' => $id,
            'event_id' => $event->event_id,
            'originalEventId' => null,
            'isRecurring' => false,
            'title' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'url' => $event->url,
            'category' => $event->category->value ?? $event->category,
            'importance' => $event->importance->value ?? $event->importance,
            'start_date' => is_string($startDate) ? $startDate : $startDate->format('Y-m-d'),
            'start_time' => $event->start_time ? (is_string($event->start_time) ? $event->start_time : $event->start_time->format('H:i:s')) : null,
            'end_date' => is_string($endDate) ? $endDate : $endDate->format('Y-m-d'),
            'end_time' => $event->end_time ? (is_string($event->end_time) ? $event->end_time : $event->end_time->format('H:i:s')) : null,
            'is_all_day' => $event->is_all_day,
            'created_by' => $event->created_by,
            'progress' => $event->progress,
            'creator' => $event->creator,
            'participants' => $event->participants,
            'attachments' => $event->attachments,
        ];
    }
    /**
     * Get year busy summary for heatmap visualization.
     */
    public function getYearBusySummary(int $year, int $calendarId, ?int $memberId = null): array
    {
        $startDate = Carbon::create($year, 1, 1)->format('Y-m-d');
        $endDate = Carbon::create($year, 12, 31)->format('Y-m-d');
        
        // 展開済みイベントを取得
        $expandedEvents = $this->getExpandedEvents($startDate, $endDate, $memberId);
        $days = [];
        
        foreach ($expandedEvents as $event) {
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
                
                $category = EventCategory::tryFrom($event['category']);
                $importance = EventImportance::tryFrom($event['importance']);
                
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
                    if ($event['is_all_day']) {
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
     * Expand event to daily breakdown (for expanded events).
     */
    private function expandEventToDays(array $event, string $yearStart, string $yearEnd): array
    {
        $eventStart = max($event['start_date'], $yearStart);
        $eventEnd = min($event['end_date'], $yearEnd);
        
        $start = Carbon::parse($eventStart);
        $end = Carbon::parse($eventEnd);
        $days = [];
        
        while ($start <= $end) {
            $date = $start->format('Y-m-d');
            
            if ($event['is_all_day']) {
                $duration = 8; // 終日 = 8時間
            } else {
                $duration = $this->calculateDayDurationFromArray($event, $date);
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
     * Calculate duration for specific date (for array-based event data).
     */
    private function calculateDayDurationFromArray(array $event, string $date): float
    {
        $eventStartDate = $event['start_date'];
        $eventEndDate = $event['end_date'];
        
        // 単日イベント
        if ($eventStartDate === $eventEndDate && $eventStartDate === $date) {
            if (!$event['start_time'] || !$event['end_time']) return 0;
            
            $start = Carbon::parse($date . ' ' . $event['start_time']);
            $end = Carbon::parse($date . ' ' . $event['end_time']);
            return $start->diffInHours($end, true);
        }
        
        // 複数日イベントの初日
        if ($eventStartDate === $date && $event['start_time']) {
            $start = Carbon::parse($date . ' ' . $event['start_time']);
            $endOfDay = Carbon::parse($date . ' 23:59:59');
            return $start->diffInHours($endOfDay, true);
        }
        
        // 複数日イベントの最終日
        if ($eventEndDate === $date && $event['end_time']) {
            $startOfDay = Carbon::parse($date . ' 00:00:00');
            $end = Carbon::parse($date . ' ' . $event['end_time']);
            return $startOfDay->diffInHours($end, true);
        }
        
        // 複数日イベントの中日
        return 24;
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
        
        // 繰り返し設定の更新
        $this->handleRecurrenceUpdate($event, $data);

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
     * Handle recurrence update.
     */
    protected function handleRecurrenceUpdate(Event $event, array $data): void
    {
        if (isset($data['recurrence'])) {
            if ($data['recurrence']['is_recurring']) {
                $event->recurrence()->updateOrCreate(
                    ['event_id' => $event->event_id],
                    [
                        'recurrence_type' => $data['recurrence']['recurrence_type'],
                        'recurrence_interval' => $data['recurrence']['recurrence_interval'],
                        'by_day' => $data['recurrence']['by_day'] ?? null,
                        'by_set_pos' => $data['recurrence']['by_set_pos'] ?? null,
                        'end_date' => $data['recurrence']['end_date'] ?? null,
                    ]
                );
            } else {
                $event->recurrence()->delete();
            }
        }
    }
    
    /**
     * Handle recurrence exception (this event only).
     */
    public function handleRecurrenceException(int $originalEventId, string $exceptionDate, array $data): Event
    {
        \Log::info('[EventService] Creating exception event', [
            'original_event_id' => $originalEventId,
            'exception_date' => $exceptionDate
        ]);
        
        $exceptionEvent = Event::create([
            'calendar_id' => Calendar::first()->calendar_id,
            'parent_event_id' => $originalEventId,
            'original_date' => $exceptionDate,
            'is_exception' => true,
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
            'created_by' => auth()->id() ?? 1,
        ]);
        
        $this->handleAttachments($exceptionEvent, $data);
        
        if (isset($data['participants'])) {
            $exceptionEvent->participants()->attach($data['participants']);
        }
        
        $this->syncSharedNote($exceptionEvent, $data);
        
        return $exceptionEvent;
    }
    
    /**
     * Handle recurrence split (this event and future).
     */
    public function handleRecurrenceSplit(int $originalEventId, string $splitDate, array $data): Event
    {
        \Log::info('[EventService] Splitting recurring event', [
            'original_event_id' => $originalEventId,
            'split_date' => $splitDate
        ]);
        
        $originalEvent = Event::findOrFail($originalEventId);
        
        // 元の繰り返しの終了日を分割日の前日に設定
        $splitDateCarbon = Carbon::parse($splitDate);
        $endDate = $splitDateCarbon->copy()->subDay();
        
        if ($originalEvent->recurrence) {
            $originalEvent->recurrence->update([
                'end_date' => $endDate->toDateString()
            ]);
        }
        
        // 新しい繰り返しイベントを作成
        $newEvent = Event::create([
            'calendar_id' => Calendar::first()->calendar_id,
            'title' => $data['title'],
            'start_date' => $splitDate,
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
            'created_by' => auth()->id() ?? 1,
        ]);
        
        // 繰り返し設定をコピー
        if ($originalEvent->recurrence && $data['recurrence']['is_recurring']) {
            $newEvent->recurrence()->create([
                'recurrence_type' => $data['recurrence']['recurrence_type'],
                'recurrence_interval' => $data['recurrence']['recurrence_interval'],
                'by_day' => $data['recurrence']['by_day'] ?? null,
                'by_set_pos' => $data['recurrence']['by_set_pos'] ?? null,
                'end_date' => $data['recurrence']['end_date'] ?? null,
            ]);
        }
        
        $this->handleAttachments($newEvent, $data);
        
        if (isset($data['participants'])) {
            $newEvent->participants()->attach($data['participants']);
        }
        
        $this->syncSharedNote($newEvent, $data);
        
        return $newEvent;
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

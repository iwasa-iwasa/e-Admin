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
                })
                ->orWhere('created_by', $memberId)
                // 参加者がいない予定（全員が確認できる）も含める
                ->orWhereDoesntHave('participants');
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
     * 繰り返し予定を個別の発生（occurrence）に展開する
     * 
     * 【2フェーズ設計の絶対原則】
     * Phase 1（スキップ）: rangeStart より前の発生は計算のみで追加しない
     * Phase 2（展開）: rangeStart 以上 rangeEnd 以下の発生を追加する
     * 
     * 【過去の致命的バグ】
     * - rangeEnd を理由にスキップフェーズで break していた
     * - 「次の発生が範囲外」を理由に展開を止めていた
     * - 月次・年次で最初の1件追加後に即終了していた
     * 
     * 【正しい設計】
     * - rangeStart/rangeEnd は「いつから/どこまで追加するか」の境界
     * - ループ終了条件ではない
     * - current が rangeEnd を超えたら終了（次の発生ではなく現在の発生で判定）
     */
    private function expandRecurringEvent(Event $event, string $startDate, string $endDate): array
    {
        $occurrences = [];
        $recurrence = $event->recurrence;
        
        if (!$recurrence) {
            return $occurrences;
        }
        
        // 日付を Carbon インスタンスに変換（時刻は 00:00:00）
        $rangeStart = Carbon::parse($startDate)->startOfDay();
        $rangeEnd = Carbon::parse($endDate)->startOfDay();
        $recurrenceEnd = $recurrence->end_date ? Carbon::parse($recurrence->end_date)->startOfDay() : null;
        
        // 繰り返しの起点
        $current = Carbon::parse($event->start_date)->startOfDay();
        $interval = $recurrence->recurrence_interval;
        $originalDay = $current->day;
        $originalMonth = $current->month;
        
        // 例外日（個別編集された日付）を取得
        $exceptionDates = $this->getExceptionDates($event->event_id);
        
        \Log::info('[expandRecurringEvent] Start', [
            'event_id' => $event->event_id,
            'type' => $recurrence->recurrence_type,
            'interval' => $interval,
            'start_date' => $event->start_date,
            'rangeStart' => $rangeStart->toDateString(),
            'rangeEnd' => $rangeEnd->toDateString(),
            'recurrenceEnd' => $recurrenceEnd?->toDateString(),
            'by_day' => $recurrence->by_day ?? [],
        ]);
        
        // ===== Phase 1: スキップフェーズ =====
        // rangeStart より前の発生は計算のみ（追加しない）
        $skipIterations = 0;
        $maxSkipIterations = 10000;
        
        while ($current->lt($rangeStart) && $skipIterations < $maxSkipIterations) {
            // 繰り返し終了日を超えたら空配列を返す
            if ($recurrenceEnd && $current->gt($recurrenceEnd)) {
                \Log::info('[expandRecurringEvent] Skip phase: exceeded recurrence end', [
                    'current' => $current->toDateString(),
                ]);
                return $occurrences;
            }
            
            $current = $this->advanceRecurrence($current, $recurrence->recurrence_type, $interval, $originalDay, $originalMonth, $recurrence->by_day ?? []);
            $skipIterations++;
        }
        
        if ($skipIterations >= $maxSkipIterations) {
            \Log::error('[expandRecurringEvent] Infinite loop in skip phase', [
                'event_id' => $event->event_id,
                'skipIterations' => $skipIterations,
            ]);
            return $occurrences;
        }
        
        \Log::info('[expandRecurringEvent] Skip phase completed', [
            'skipIterations' => $skipIterations,
            'current' => $current->toDateString(),
        ]);
        
        // ===== Phase 2: 展開フェーズ =====
        // current が rangeEnd 以下の間、発生を収集
        $expandIterations = 0;
        $maxExpandIterations = 1000;
        
        while ($current->lte($rangeEnd) && $expandIterations < $maxExpandIterations) {
            // 繰り返し終了日チェック
            if ($recurrenceEnd && $current->gt($recurrenceEnd)) {
                \Log::info('[expandRecurringEvent] Expand phase: exceeded recurrence end', [
                    'current' => $current->toDateString(),
                ]);
                break;
            }
            
            // 例外日でなければ追加
            $dateStr = $current->toDateString();
            if (!in_array($dateStr, $exceptionDates)) {
                $occurrenceId = $this->generateOccurrenceId($event->event_id, $current);
                $occurrence = $this->formatExpandedEvent($event, $occurrenceId, $dateStr);
                $occurrence['originalEventId'] = $event->event_id;
                $occurrence['isRecurring'] = true;
                $occurrences[] = $occurrence;
            }
            
            // 次の発生へ進める
            $current = $this->advanceRecurrence($current, $recurrence->recurrence_type, $interval, $originalDay, $originalMonth, $recurrence->by_day ?? []);
            $expandIterations++;
        }
        
        \Log::info('[expandRecurringEvent] Completed', [
            'event_id' => $event->event_id,
            'expandIterations' => $expandIterations,
            'occurrences_count' => count($occurrences),
        ]);
        
        if ($expandIterations >= $maxExpandIterations) {
            \Log::warning('[expandRecurringEvent] Reached max iterations', [
                'event_id' => $event->event_id,
            ]);
        }
        
        return $occurrences;
    }
    
    /**
     * 繰り返しを次の発生に進める
     * 
     * 【重要】
     * - 必ず新しい Carbon インスタンスを返す（copy() 使用）
     * - 月次・年次は日付ずれルールを適用（Google Calendar 方式）
     * - 週次は by_day が空の場合も考慮
     */
    private function advanceRecurrence(Carbon $current, string $type, int $interval, int $originalDay, int $originalMonth, array $byDay): Carbon
    {
        $next = $current->copy();
        
        switch ($type) {
            case 'daily':
                $next->addDays($interval);
                break;
                
            case 'weekly':
                if (!empty($byDay)) {
                    // 曜日指定あり：次の対象曜日を探す
                    $next = $this->getNextWeeklyOccurrence($next, $byDay, $interval);
                } else {
                    // 曜日指定なし：単純に interval 週間進める
                    $next->addWeeks($interval);
                }
                break;
                
            case 'monthly':
                // 月を interval 分進める
                $year = $next->year;
                $month = $next->month + $interval;
                while ($month > 12) {
                    $year++;
                    $month -= 12;
                }
                // 日付ずれルール：その月に存在しない日は月末に繰り下げ
                $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
                $day = min($originalDay, $daysInMonth);
                $next->setDate($year, $month, $day);
                break;
                
            case 'yearly':
                // 年を interval 分進める
                $year = $next->year + $interval;
                // 日付ずれルール：うるう年の 2/29 などを考慮
                $daysInMonth = Carbon::create($year, $originalMonth, 1)->daysInMonth;
                $day = min($originalDay, $daysInMonth);
                $next->setDate($year, $originalMonth, $day);
                break;
        }
        
        return $next;
    }
    
    /**
     * Generate unique occurrence ID.
     */
    private function generateOccurrenceId(int $eventId, Carbon $date): int
    {
        $dateStr = $date->format('Ymd');
        return abs(crc32($eventId . '-' . $dateStr));
    }
    
    /**
     * Get next weekly occurrence based on specified days.
     */
    private function getNextWeeklyOccurrence(Carbon $current, array $byDay, int $interval): Carbon
    {
        $next = $current->copy();
        $dayMap = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];
        $targetDays = array_map(fn($day) => $dayMap[$day] ?? 1, $byDay);
        sort($targetDays);
        
        $currentDayOfWeek = $next->dayOfWeek;
        $nextDay = null;
        
        foreach ($targetDays as $day) {
            if ($day > $currentDayOfWeek) {
                $nextDay = $day;
                break;
            }
        }
        
        if ($nextDay !== null) {
            $daysToAdd = $nextDay - $currentDayOfWeek;
            $next->addDays($daysToAdd);
        } else {
            $daysUntilNextWeek = (7 - $currentDayOfWeek) + $targetDays[0];
            $next->addDays($daysUntilNextWeek);
            if ($interval > 1) {
                $next->addWeeks($interval - 1);
            }
        }
        
        return $next;
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
                })
                ->orWhere('created_by', $memberId)
                // 参加者がいない予定（全員が確認できる）も含める
                ->orWhereDoesntHave('participants');
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
     * Handle recurrence delete with scope.
     */
    public function handleRecurrenceDelete(int $eventId, string $deleteScope, ?string $targetDate = null): void
    {
        \Log::info('[EventService] Handling recurrence delete', [
            'event_id' => $eventId,
            'delete_scope' => $deleteScope,
            'target_date' => $targetDate
        ]);
        
        $event = Event::findOrFail($eventId);
        
        switch ($deleteScope) {
            case 'this-only':
                // この予定のみ削除：例外イベントとして削除フラグを立てる
                Event::create([
                    'calendar_id' => $event->calendar_id,
                    'parent_event_id' => $eventId,
                    'original_date' => $targetDate,
                    'is_exception' => true,
                    'title' => $event->title . ' (削除済み)',
                    'start_date' => $targetDate,
                    'end_date' => $targetDate,
                    'is_all_day' => true,
                    'created_by' => auth()->id() ?? 1,
                    'deleted_at' => now(),
                ]);
                break;
                
            case 'this-and-future':
                // この予定以降削除：親イベントの終了日を調整
                if ($event->recurrence && $targetDate) {
                    $endDate = Carbon::parse($targetDate)->subDay();
                    $event->recurrence->update([
                        'end_date' => $endDate->toDateString()
                    ]);
                }
                break;
                
            case 'all':
                // すべて削除：親イベントごと削除
                $this->deleteEvent($event);
                break;
        }
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

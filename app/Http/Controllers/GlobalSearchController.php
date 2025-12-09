<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\SharedNote;
use App\Models\Reminder;
use App\Models\Survey;
use App\Models\TrashItem;
use App\Models\RecentActivity;
use Illuminate\Support\Facades\Auth;

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $types = $request->input('types', []);
        if (is_string($types)) {
            $types = explode(',', $types);
        }
        $searchField = $request->input('search_field', 'all');
        $creatorName = $request->input('creator_name', '');
        $participantName = $request->input('participant_name', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');
        $dateType = $request->input('date_type', 'updated');
        
        $minLength = 2;
        
        if (strlen($query) < $minLength) {
            return response()->json([
                'results' => [],
                'recent' => $this->getRecentItems()
            ]);
        }
        
        $results = collect();
        $user = Auth::user();
        
        // イベント検索（他社作成も含む）
        if (empty($types) || in_array('event', $types)) {
            $events = Event::where(function($q) use ($query, $searchField) {
                if ($searchField === 'title') {
                    $q->where('title', 'like', "%{$query}%");
                } elseif ($searchField === 'description') {
                    $q->where('description', 'like', "%{$query}%");
                } else {
                    $q->where('title', 'like', "%{$query}%")
                      ->orWhere('description', 'like', "%{$query}%")
                      ->orWhere('location', 'like', "%{$query}%");
                }
            })
            ->when($creatorName, function($q) use ($creatorName) {
                $q->whereHas('creator', fn($q) => $q->where('name', 'like', "%{$creatorName}%"));
            })
            ->when($participantName, function($q) use ($participantName) {
                $q->where(function($subQ) use ($participantName) {
                    $subQ->whereHas('creator', fn($q) => $q->where('name', 'like', "%{$participantName}%"))
                         ->orWhereHas('participants', fn($q) => $q->where('users.name', 'like', "%{$participantName}%"));
                });
            })
            ->when($dateFrom, function($q) use ($dateFrom, $dateType) {
                $field = $dateType === 'created' ? 'created_at' : 'updated_at';
                $q->where($field, '>=', $dateFrom);
            })
            ->when($dateTo, function($q) use ($dateTo, $dateType) {
                $field = $dateType === 'created' ? 'created_at' : 'updated_at';
                $q->where($field, '<=', $dateTo . ' 23:59:59');
            })
            ->with(['creator', 'participants'])
            ->limit(50)
            ->get()
            ->map(fn($event) => [
                'id' => $event->event_id,
                'type' => 'event',
                'title' => $event->title,
                'description' => $event->description,
                'creator' => $event->creator->name,
                'creator_id' => $event->created_by,
                'participants' => $event->participants->pluck('name')->join(', '),
                'date' => $event->end_date,
                'created_at' => $event->created_at,
                'updated_at' => $event->updated_at,
            ]);
            
            $results = $results->merge($events);
        }
        
        // 共有メモ検索（他社作成も含む）
        if (empty($types) || in_array('note', $types)) {
            $notes = SharedNote::where('is_deleted', false);
            
            if ($searchField === 'title') {
                $notes->where('title', 'like', "%{$query}%");
            } elseif ($searchField === 'description') {
                $notes->where('content', 'like', "%{$query}%");
            } else {
                $notes->where(function($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                      ->orWhere('content', 'like', "%{$query}%");
                });
            }
            
            $notes = $notes->when($creatorName, function($q) use ($creatorName) {
                    $q->whereHas('author', fn($q) => $q->where('name', 'like', "%{$creatorName}%"));
                })
                ->when($participantName, function($q) use ($participantName) {
                    $q->where(function($subQ) use ($participantName) {
                        $subQ->whereHas('author', fn($q) => $q->where('name', 'like', "%{$participantName}%"))
                             ->orWhereHas('participants', fn($q) => $q->where('users.name', 'like', "%{$participantName}%"));
                    });
                })
                ->when($dateFrom, function($q) use ($dateFrom, $dateType) {
                    $field = $dateType === 'created' ? 'created_at' : 'updated_at';
                    $q->where($field, '>=', $dateFrom);
                })
                ->when($dateTo, function($q) use ($dateTo, $dateType) {
                    $field = $dateType === 'created' ? 'created_at' : 'updated_at';
                    $q->where($field, '<=', $dateTo . ' 23:59:59');
                })
                ->with(['author', 'participants'])
                ->limit(50)
                ->get()
                ->map(fn($note) => [
                    'id' => $note->note_id,
                    'type' => 'note',
                    'title' => $note->title ?? '',
                    'description' => $note->content ? mb_substr($note->content, 0, 100, 'UTF-8') : '',
                    'creator' => $note->author->name ?? '',
                    'creator_id' => $note->author_id,
                    'participants' => $note->participants->pluck('name')->join(', '),
                    'date' => $note->deadline_date,
                    'created_at' => $note->created_at,
                    'updated_at' => $note->updated_at,
                ]);
            
            $results = $results->merge($notes);
        }
        
        // リマインダー検索
        if (empty($types) || in_array('reminder', $types)) {
            $reminders = Reminder::where('user_id', $user->id)
                ->where(function($q) use ($query, $searchField) {
                    if ($searchField === 'title') {
                        $q->where('title', 'like', "%{$query}%");
                    } elseif ($searchField === 'description') {
                        $q->where('description', 'like', "%{$query}%");
                    } else {
                        $q->where('title', 'like', "%{$query}%")
                          ->orWhere('description', 'like', "%{$query}%");
                    }
                })
                ->when($dateFrom, function($q) use ($dateFrom, $dateType) {
                    $field = $dateType === 'created' ? 'created_at' : 'updated_at';
                    $q->where($field, '>=', $dateFrom);
                })
                ->when($dateTo, function($q) use ($dateTo, $dateType) {
                    $field = $dateType === 'created' ? 'created_at' : 'updated_at';
                    $q->where($field, '<=', $dateTo . ' 23:59:59');
                })
                ->limit(50)
                ->get()
                ->map(fn($reminder) => [
                    'id' => $reminder->reminder_id,
                    'type' => 'reminder',
                    'title' => $reminder->title,
                    'description' => $reminder->description,
                    'creator' => $user->name,
                    'creator_id' => $user->id,
                    'participants' => '',
                    'date' => $reminder->deadline_date,
                    'created_at' => $reminder->created_at,
                    'updated_at' => $reminder->updated_at,
                ]);
            
            $results = $results->merge($reminders);
        }
        
        // アンケート検索（他社作成も含む）
        if (empty($types) || in_array('survey', $types)) {
            $surveys = Survey::where('is_deleted', false)
                ->where(function($q) use ($query, $searchField) {
                    if ($searchField === 'title') {
                        $q->where('title', 'like', "%{$query}%");
                    } elseif ($searchField === 'description') {
                        $q->where('description', 'like', "%{$query}%");
                    } else {
                        $q->where('title', 'like', "%{$query}%")
                          ->orWhere('description', 'like', "%{$query}%");
                    }
                })
                ->when($creatorName, function($q) use ($creatorName) {
                    $q->whereHas('creator', fn($q) => $q->where('name', 'like', "%{$creatorName}%"));
                })
                ->when($dateFrom, function($q) use ($dateFrom, $dateType) {
                    $field = $dateType === 'created' ? 'created_at' : 'updated_at';
                    $q->where($field, '>=', $dateFrom);
                })
                ->when($dateTo, function($q) use ($dateTo, $dateType) {
                    $field = $dateType === 'created' ? 'created_at' : 'updated_at';
                    $q->where($field, '<=', $dateTo . ' 23:59:59');
                })
                ->with('creator')
                ->limit(50)
                ->get()
                ->map(fn($survey) => [
                    'id' => $survey->survey_id,
                    'type' => 'survey',
                    'title' => $survey->title,
                    'description' => $survey->description,
                    'creator' => $survey->creator->name,
                    'creator_id' => $survey->created_by,
                    'participants' => '',
                    'date' => $survey->deadline_date,
                    'created_at' => $survey->created_at,
                    'updated_at' => $survey->updated_at,
                ]);
            
            $results = $results->merge($surveys);
        }
        
        // ゴミ箱検索（共有アイテムと個人アイテム）
        if (empty($types) || in_array('trash', $types)) {
            $trashItems = TrashItem::where(function($q) use ($user) {
                    $q->where('is_shared', true)
                      ->orWhere('user_id', $user->id);
                })
                ->with('user')
                ->limit(50)
                ->get()
                ->filter(function($item) use ($query, $searchField) {
                    $originalItem = null;
                    if ($item->item_type === 'event') {
                        $originalItem = Event::withTrashed()->find($item->item_id);
                    } elseif ($item->item_type === 'shared_note') {
                        $originalItem = SharedNote::find($item->item_id);
                    } elseif ($item->item_type === 'survey') {
                        $originalItem = Survey::find($item->item_id);
                    } elseif ($item->item_type === 'reminder') {
                        $originalItem = Reminder::withTrashed()->find($item->item_id);
                    }
                    
                    if (!$originalItem) return false;
                    
                    $title = $item->original_title;
                    $description = $originalItem->description ?? $originalItem->content ?? '';
                    
                    if ($searchField === 'title') {
                        return stripos($title, $query) !== false;
                    } elseif ($searchField === 'description') {
                        return stripos($description, $query) !== false;
                    } else {
                        return stripos($title, $query) !== false || stripos($description, $query) !== false;
                    }
                })
                ->when($dateFrom, function($collection) use ($dateFrom) {
                    return $collection->filter(fn($item) => $item->deleted_at >= $dateFrom);
                })
                ->when($dateTo, function($collection) use ($dateTo) {
                    return $collection->filter(fn($item) => $item->deleted_at <= $dateTo . ' 23:59:59');
                })
                ->map(function($item) {
                    $typeMap = [
                        'event' => '共有カレンダー',
                        'shared_note' => '共有メモ',
                        'survey' => 'アンケート',
                        'reminder' => '個人リマインダー',
                    ];
                    return [
                        'id' => $item->id,
                        'type' => 'trash',
                        'title' => $item->original_title,
                        'description' => $typeMap[$item->item_type] ?? $item->item_type,
                        'creator' => $item->user->name ?? '',
                        'creator_id' => $item->user_id,
                        'participants' => '',
                        'date' => $item->deleted_at,
                        'created_at' => $item->deleted_at,
                        'updated_at' => $item->deleted_at,
                        'deleted_at' => $item->deleted_at,
                    ];
                })
                ->values();
            
            $results = $results->merge($trashItems);
        }
        
        return response()->json([
            'results' => $results->sortByDesc('updated_at')->take(100)->values(),
            'recent' => []
        ]);
    }
    
    private function getRecentItems()
    {
        $user = Auth::user();
        $results = collect();
        
        // イベント（全て）
        $events = Event::with('creator')
            ->get()
            ->map(fn($item) => [
                'id' => $item->event_id,
                'type' => 'event',
                'title' => $item->title,
                'description' => $item->description,
                'creator' => $item->creator->name,
                'date' => $item->end_date,
                'updated_at' => $item->updated_at,
            ]);
        
        // 共有メモ（全て）
        $notes = SharedNote::where('is_deleted', false)
            ->with('author')
            ->get()
            ->map(fn($item) => [
                'id' => $item->note_id,
                'type' => 'note',
                'title' => $item->title ?? '',
                'description' => $item->content ? mb_substr($item->content, 0, 100, 'UTF-8') : '',
                'creator' => $item->author->name ?? '',
                'date' => $item->deadline_date,
                'updated_at' => $item->updated_at,
            ]);
        
        // リマインダー（自分のみ）
        $reminders = Reminder::where('user_id', $user->id)
            ->where('completed', false)
            ->get()
            ->map(fn($item) => [
                'id' => $item->reminder_id,
                'type' => 'reminder',
                'title' => $item->title,
                'description' => $item->description,
                'creator' => $user->name,
                'date' => $item->deadline_date,
                'updated_at' => $item->updated_at,
            ]);
        
        // アンケート（全て）
        $surveys = Survey::where('is_deleted', false)
            ->with('creator')
            ->get()
            ->map(fn($item) => [
                'id' => $item->survey_id,
                'type' => 'survey',
                'title' => $item->title,
                'description' => $item->description,
                'creator' => $item->creator->name,
                'date' => $item->deadline_date,
                'updated_at' => $item->updated_at,
            ]);
        
        // 全てをマージして更新日時でソート、上位10件を取得
        return $events->concat($notes)->concat($reminders)->concat($surveys)
            ->sortByDesc('updated_at')
            ->take(10)
            ->values();
    }
}

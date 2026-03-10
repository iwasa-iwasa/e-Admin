<?php

namespace App\Http\Controllers;

use App\Models\CalendarEventShare;
use App\Models\Event;
use App\Models\Calendar;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventShareController extends Controller
{
    /**
     * 指定の予定をカレンダーに共有する
     */
    public function shareToCalendar(Request $request, Event $event)
    {
        $validated = $request->validate([
            'calendar_id' => 'required|exists:calendars,calendar_id',
        ]);

        $calendarId = $validated['calendar_id'];

        // すでに共有済みかチェック
        $exists = CalendarEventShare::where('calendar_id', $calendarId)
            ->where('event_id', $event->event_id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'すでにこのカレンダーに共有されています。'], 400);
        }

        DB::beginTransaction();
        try {
            // 共有レコード作成
            $share = CalendarEventShare::create([
                'calendar_id' => $calendarId,
                'event_id' => $event->event_id,
                'shared_by' => auth()->id(),
                'shared_at' => now(),
            ]);

            $calendar = Calendar::findOrFail($calendarId);

            // 監査ログに記録
            AuditLog::create([
                'action' => 'event_shared',
                'user_id' => auth()->id(),
                'event_id' => $event->event_id,
                'calendar_id' => $calendarId,
                'details' => [
                    'event_title' => $event->title,
                    'shared_to_calendar_name' => $calendar->calendar_name,
                ],
                'created_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => '予定を共有しました。',
                'data' => $share
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => '共有に失敗しました。', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * カレンダーの共有予定一覧を取得する
     */
    public function getEventsForCalendar(Calendar $calendar)
    {
        // カレンダー自身の予定 + 共有された予定
        $events = Event::with(['creator', 'participants', 'calendar', 'sharedCalendars'])
            ->where('calendar_id', $calendar->calendar_id)
            ->orWhereHas('sharedCalendars', function ($query) use ($calendar) {
                $query->where('calendar_event_shares.calendar_id', $calendar->calendar_id);
            })
            ->get();

        return response()->json(['data' => $events]);
    }

    /**
     * 共有設定を解除する
     */
    public function unshare(Request $request, CalendarEventShare $calendarEventShare)
    {
        DB::beginTransaction();
        try {
            // 監査ログに記録
            AuditLog::create([
                'action' => 'event_unshared',
                'user_id' => auth()->id(),
                'event_id' => $calendarEventShare->event_id,
                'calendar_id' => $calendarEventShare->calendar_id,
                'created_at' => now(),
            ]);

            $calendarEventShare->delete();

            DB::commit();

            return response()->json(['message' => '共有設定を解除しました。']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => '共有解除に失敗しました。', 'error' => $e->getMessage()], 500);
        }
    }
}

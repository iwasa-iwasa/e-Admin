<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VisitorEventController extends Controller
{
    public function checkUpcoming(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['event' => null]);
        }

        $now = Carbon::now();
        $thirtyMinutesBefore = $now->copy()->addMinutes(29);
        $thirtyMinutesAfter = $now->copy()->addMinutes(31);

        $event = Event::where('category', '来客')
            ->where('start_date', $now->toDateString())
            ->where('start_time', '>=', $thirtyMinutesBefore->format('H:i:s'))
            ->where('start_time', '<=', $thirtyMinutesAfter->format('H:i:s'))
            ->whereHas('participants', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['participants', 'calendar'])
            ->first();

        return response()->json([
            'event' => $event ? [
                'event_id' => $event->event_id,
                'title' => $event->title,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'start_date' => $event->start_date,
                'category' => $event->category,
                'participants' => $event->participants
            ] : null
        ]);
    }
}
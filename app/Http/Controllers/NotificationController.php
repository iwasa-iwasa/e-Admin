<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Event;
use App\Models\SharedNote;
use App\Models\Survey;
use App\Models\Reminder;
use Carbon\Carbon;

class NotificationController extends Controller
{
    public function getNotifications(Request $request)
    {
        $user = Auth::user();
        $now = Carbon::now();
        $endDate = $now->copy()->addDays(60);
        
        $eventsFilter = $request->query('events_filter', 'mine');
        $notesFilter = $request->query('notes_filter', 'mine');

        $eventsQuery = Event::with(['creator', 'calendar', 'participants', 'recurrence', 'attachments'])
            ->where(function($query) use ($now, $endDate) {
                $query->whereBetween('end_date', [$now->toDateString(), $endDate->toDateString()])
                      ->orWhere('start_date', '>=', $now->toDateString());
            })
            ->where('importance', '重要')
            ->where('is_deleted', false);
        
        if ($eventsFilter === 'mine') {
            $eventsQuery->where(function($query) use ($user) {
                $query->where('created_by', $user->id)
                      ->orWhereHas('participants', function($q) use ($user) {
                          $q->where('user_id', $user->id);
                      });
            });
        }
        
        $events = $eventsQuery->orderBy('start_date')->get();

        $notesQuery = SharedNote::with(['author', 'tags', 'participants'])
            ->whereNotNull('deadline_date')
            ->whereBetween('deadline_date', [$now->toDateString(), $endDate->toDateString()])
            ->where('priority', 'high')
            ->where('is_deleted', false);
        
        if ($notesFilter === 'mine') {
            $notesQuery->where(function($query) use ($user) {
                $query->where('author_id', $user->id)
                      ->orWhereHas('participants', function($q) use ($user) {
                          $q->where('user_id', $user->id);
                      });
            });
        }
        
        $notes = $notesQuery->orderBy('deadline_date')->orderBy('deadline_time')->get();

        $surveys = Survey::with(['creator'])
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->where(function($query) use ($now) {
                $query->whereNull('deadline_date')
                      ->orWhere('deadline_date', '>=', $now->toDateString());
            })
            ->whereDoesntHave('responses', function($query) use ($user) {
                $query->where('respondent_id', $user->id);
            })
            ->orderByRaw('CASE WHEN deadline_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('deadline_date')
            ->orderBy('deadline_time')
            ->get();

        $reminders = Reminder::where('user_id', $user->id)
            ->where('completed', false)
            ->whereNotNull('deadline_date')
            ->where('deadline_date', '<=', $endDate->toDateString())
            ->whereDoesntHave('trashItems')
            ->orderBy('deadline_date')
            ->orderBy('deadline_time')
            ->get();

        return response()->json([
            'events' => $events,
            'notes' => $notes,
            'surveys' => $surveys,
            'reminders' => $reminders,
        ]);
    }
}

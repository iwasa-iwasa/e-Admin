<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Event;
use App\Models\SharedNote;
use App\Models\Reminder;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $user = Auth::user();

        // Fetch data for the dashboard
        $events = Event::with(['creator', 'participants'])->whereHas('participants', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->orderBy('start_date')
        ->take(5)
        ->get();

        $notes = SharedNote::with('author')->orderBy('pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->take(5)
            ->get();

        $reminders = $user->reminders()
            ->where('completed', false)
            ->orderBy('deadline')
            ->take(5)
            ->get();

        return Inertia::render('Dashboard', [
            'events' => $events,
            'sharedNotes' => $notes,
            'personalReminders' => $reminders,
        ]);
    }
}
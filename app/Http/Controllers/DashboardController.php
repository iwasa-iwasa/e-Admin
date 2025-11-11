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

        // Fetch all events from the shared calendar
        $events = Event::with(['creator', 'participants'])
            ->orderBy('start_date')
            ->get();

        $notes = SharedNote::with('author')->orderBy('pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        $reminders = $user->reminders()
            ->where('completed', false)
            ->orderBy('deadline')
            ->get();

        return Inertia::render('Dashboard', [
            'events' => $events,
            'sharedNotes' => $notes,
            'personalReminders' => $reminders,
        ]);
    }
}
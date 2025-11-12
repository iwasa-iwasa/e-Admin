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

        $notes = SharedNote::with('author')
            ->orderBy('updated_at', 'desc')
            ->get();

        // Get IDs of notes pinned by the current user
        $pinnedNoteIds = $user->pinnedNotes()->pluck('shared_notes.note_id')->all();

        // Add is_pinned attribute to each note
        $notes->each(function ($note) use ($pinnedNoteIds) {
            $note->is_pinned = in_array($note->note_id, $pinnedNoteIds);
        });

        // Sort by is_pinned desc (pinned notes first), then by updated_at (already sorted by DB)
        $sortedNotes = $notes->sortByDesc('is_pinned');

        $reminders = $user->reminders()
            ->where('completed', false)
            ->orderBy('deadline')
            ->get();

        return Inertia::render('Dashboard', [
            'events' => $events,
            'sharedNotes' => $sortedNotes->values(),
            'personalReminders' => $reminders,
        ]);
    }
}
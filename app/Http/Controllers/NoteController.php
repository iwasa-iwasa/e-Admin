<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SharedNote;

class NoteController extends Controller
{
    /**
     * Display the notes page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $notes = SharedNote::with(['author', 'tags'])
            ->orderBy('pinned', 'desc')
            ->orderBy('updated_at', 'desc')
            ->get();

        return Inertia::render('Notes', [
            'notes' => $notes,
        ]);
    }
}
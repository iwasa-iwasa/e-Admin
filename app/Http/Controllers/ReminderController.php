<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class ReminderController extends Controller
{
    /**
     * Display the reminders page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $reminders = Auth::user()->reminders()->orderBy('deadline')->get();

        return Inertia::render('Reminders', [
            'reminders' => $reminders,
        ]);
    }
}
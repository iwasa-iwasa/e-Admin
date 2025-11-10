<?php

use App\Http\Controllers\SurveyController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

// 認証済みユーザーのみがアクセスできるルートのグループ
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');

    Route::get('/notes', [NoteController::class, 'index'])->name('notes');

    Route::get('/surveys', [SurveyController::class, 'index'])->name('surveys');

    // Survey Results
    Route::get('/surveys/{survey}/results', function () {
        // Note: In a real application, you would pass the specific survey data as a prop.
        return Inertia::render('SurveyResults');
    })->name('surveys.results');

    Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders');

    // Trash
    Route::get('/trash', function () {
        return Inertia::render('Trash');
    })->name('trash');

    // Member Calendar
    Route::get('/member-calendar', function () {
        return Inertia::render('MemberCalendar');
    })->name('member.calendar');
});

// Laravel Breeze/Jetstreamのデフォルト認証ルート
require __DIR__.'/auth.php';
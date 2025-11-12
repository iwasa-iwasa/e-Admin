<?php

use App\Http\Controllers\SurveyController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\PersonalReminderController;
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
    Route::post('/surveys', [SurveyController::class, 'store'])->name('surveys.store');
    Route::get('/surveys/{survey}/edit', [SurveyController::class, 'edit'])->name('surveys.edit');
    Route::put('/surveys/{survey}', [SurveyController::class, 'update'])->name('surveys.update');
    Route::delete('/surveys/{survey}', [SurveyController::class, 'destroy'])->name('surveys.destroy');
    Route::get('/surveys/{survey}/answer', [SurveyController::class, 'answer'])->name('surveys.answer');
    Route::post('/surveys/{survey}/submit', [SurveyController::class, 'submitAnswer'])->name('surveys.submit');
    //Survey Results and Export
    Route::get('/surveys/{survey}/results', [SurveyController::class, 'results'])->name('surveys.results');
    Route::get('/surveys/{survey}/export', [SurveyController::class, 'export'])->name('surveys.export');

    Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders');
    Route::post('/reminders', [PersonalReminderController::class, 'store'])->name('reminders.store');
    Route::put('/reminders/{reminder}', [PersonalReminderController::class, 'update'])->name('reminders.update');

    // Trash
    Route::get('/trash', function () {
        return Inertia::render('Trash');
    })->name('trash');

    // Member Calendar
    Route::get('/member-calendar', function () {
        return Inertia::render('MemberCalendar');
    })->name('member.calendar');

    Route::post('/events', [\App\Http\Controllers\CalendarController::class, 'store'])->name('events.store');

    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [\App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Laravel Breeze/Jetstreamのデフォルト認証ルート
require __DIR__ . '/auth.php';

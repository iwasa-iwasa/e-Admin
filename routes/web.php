<?php

use App\Http\Controllers\SurveyController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\PersonalReminderController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificationController;
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
    return redirect()->route('welcome');
});

Route::get('/welcome', function () {
    return Inertia::render('Welcome');
})->name('welcome');

// 認証済みユーザーのみがアクセスできるルートのグループ
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');

    Route::get('/notes', [NoteController::class, 'index'])->name('notes');
    Route::post('/shared-notes', [NoteController::class, 'store'])->name('shared-notes.store');
    Route::put('/shared-notes/{note}', [NoteController::class, 'update'])->name('shared-notes.update');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
    Route::post('/notes/{note}/restore', [NoteController::class, 'restore'])->name('notes.restore');
    Route::post('/notes/{note}/pin', [NoteController::class, 'pin'])->name('notes.pin');
    Route::delete('/notes/{note}/unpin', [NoteController::class, 'unpin'])->name('notes.unpin');

    Route::get('/surveys', [SurveyController::class, 'index'])->name('surveys');
    Route::post('/surveys', [SurveyController::class, 'store'])->name('surveys.store');
    Route::get('/surveys/{survey}/edit', [SurveyController::class, 'edit'])->name('surveys.edit');
    Route::put('/surveys/{survey}', [SurveyController::class, 'update'])->name('surveys.update');
    Route::delete('/surveys/{survey}', [SurveyController::class, 'destroy'])->name('surveys.destroy');
    Route::post('/surveys/{survey}/restore', [SurveyController::class, 'restore'])->name('surveys.restore');
    Route::get('/surveys/{survey}/answer', [SurveyController::class, 'answer'])->name('surveys.answer');
    Route::post('/surveys/{survey}/submit', [SurveyController::class, 'submitAnswer'])->name('surveys.submit');
    //Survey Results and Export
    Route::get('/surveys/{survey}/results', [SurveyController::class, 'results'])->name('surveys.results');
    Route::get('/surveys/{survey}/export', [SurveyController::class, 'export'])->name('surveys.export');

    Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders');
    Route::post('/reminders', [PersonalReminderController::class, 'store'])->name('reminders.store');
    Route::put('/reminders/{reminder}', [PersonalReminderController::class, 'update'])->name('reminders.update');
    Route::patch('/reminders/{reminder}/complete', [PersonalReminderController::class, 'completeReminder'])->name('reminders.complete');
    Route::post('/reminders/restore', [PersonalReminderController::class, 'restoreReminder'])->name('reminders.restore');
    Route::delete('/reminders/{reminder}', [PersonalReminderController::class, 'destroy'])->name('reminders.destroy');

    // Trash
    Route::get('/trash', [\App\Http\Controllers\TrashController::class, 'index'])->name('trash');
    Route::post('/trash/{id}/restore', [\App\Http\Controllers\TrashController::class, 'restore'])->name('trash.restore');
    Route::delete('/trash/{id}', [\App\Http\Controllers\TrashController::class, 'destroy'])->name('trash.destroy');
    Route::delete('/trash', [\App\Http\Controllers\TrashController::class, 'emptyTrash'])->name('trash.empty');

    // Member Calendar
    Route::get('/member-calendar', function () {
        return Inertia::render('MemberCalendar');
    })->name('member.calendar');

    Route::post('/events', [CalendarController::class, 'store'])->name('events.store');
    Route::put('/events/{event}', [CalendarController::class, 'update'])->name('events.update');
    Route::delete('/events/{event}', [CalendarController::class, 'destroy'])->name('events.destroy');
    Route::post('/events/{event}/restore', [CalendarController::class, 'restore'])->name('events.restore');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Notifications API
    Route::get('/api/notifications', [NotificationController::class, 'getNotifications']);
});

// Laravel Breeze/Jetstreamのデフォルト認証ルート
require __DIR__ . '/auth.php';

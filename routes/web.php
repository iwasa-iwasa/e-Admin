<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\UserController as ApiUserController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PersonalReminderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\TrashAutoDeleteController;
use App\Http\Controllers\TrashController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Http\Request;

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

    // Notes
    Route::controller(NoteController::class)->group(function () {
        Route::get('/notes', 'index')->name('notes');
        Route::post('/shared-notes', 'store')->name('shared-notes.store');
        Route::put('/shared-notes/{note}', 'update')->name('shared-notes.update');
        Route::delete('/notes/{note}', 'destroy')->name('notes.destroy');
        Route::post('/notes/{note}/restore', 'restore')->name('notes.restore');
        Route::post('/notes/{note}/pin', 'pin')->name('notes.pin');
        Route::delete('/notes/{note}/unpin', 'unpin')->name('notes.unpin');
    });

    // Surveys
    Route::controller(SurveyController::class)->group(function () {
        Route::get('/surveys', 'index')->name('surveys');
        Route::post('/surveys', 'store')->name('surveys.store');
        Route::get('/surveys/{survey}/edit', 'edit')->name('surveys.edit');
        Route::put('/surveys/{survey}', 'update')->name('surveys.update');
        Route::delete('/surveys/{survey}', 'destroy')->name('surveys.destroy');
        Route::post('/surveys/{survey}/restore', 'restore')->name('surveys.restore');
        Route::get('/surveys/{survey}/answer', 'answer')->name('surveys.answer');
        Route::post('/surveys/{survey}/submit', 'submitAnswer')->name('surveys.submit');
        Route::get('/surveys/{survey}/results', 'results')->name('surveys.results');
        Route::get('/surveys/{survey}/export', 'export')->name('surveys.export');
    });

    // Reminders
    Route::get('/reminders', [ReminderController::class, 'index'])->name('reminders');
    Route::controller(PersonalReminderController::class)->prefix('reminders')->name('reminders.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::put('/{reminder}', 'update')->name('update');
        Route::patch('/{reminder}/complete', 'completeReminder')->name('complete');
        Route::post('/restore', 'restoreReminder')->name('restore');
        Route::post('/bulk-complete', 'bulkComplete')->name('bulkComplete');
        Route::post('/bulk-restore', 'bulkRestore')->name('bulkRestore');
        Route::post('/bulk-delete', 'bulkDelete')->name('bulkDelete');
        Route::delete('/{reminder}', 'destroy')->name('destroy');
    });

    // Trash
    Route::get('/trash', [TrashController::class, 'index'])->name('trash');
    Route::controller(TrashController::class)->prefix('trash')->name('trash.')->group(function () {
        Route::post('/{id}/restore', 'restore')->name('restore');
        Route::post('/restore-multiple', 'restoreMultiple')->name('restoreMultiple');
        Route::delete('/{id}', 'destroy')->name('destroy');
        Route::post('/destroy-multiple', 'destroyMultiple')->name('destroyMultiple');
        Route::delete('/', 'emptyTrash')->name('empty');
    });
    
    // Trash Auto Delete Settings (総務部のみ)
    Route::controller(TrashAutoDeleteController::class)->prefix('trash/auto-delete')->name('trash.auto-delete')->group(function () {
         Route::get('/', 'index')->name('');
         Route::post('/', 'update')->name('.update');
    });

    // Member Calendar Route Removed

    // Events
    Route::controller(CalendarController::class)->prefix('events')->name('events.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::put('/{event}', 'update')->name('update');
        Route::delete('/{event}', 'destroy')->name('destroy');
        Route::post('/{event}/restore', 'restore')->name('restore');
    });

    Route::controller(ProfileController::class)->prefix('profile')->name('profile.')->group(function () {
        Route::get('/', 'edit')->name('edit');
        Route::patch('/', 'update')->name('update');
        Route::delete('/', 'destroy')->name('destroy');
    });

    // API Routes
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'getNotifications']);
        Route::get('/search', [GlobalSearchController::class, 'search']);
        Route::get('/users', [ApiUserController::class, 'index']);
        
        Route::get('/events', [CalendarController::class, 'getEventsApi'])->name('events.index');
        Route::get('/events/{id}', [CalendarController::class, 'show']);
        Route::get('/notes/{id}', [NoteController::class, 'show']);
        Route::get('/reminders/{id}', [PersonalReminderController::class, 'show']);
        Route::get('/surveys/{id}', [SurveyController::class, 'show']);
        Route::post('/track-activity', [ActivityController::class, 'track']);
    });

    Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::controller(AdminUserController::class)->prefix('users')->name('users.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::delete('/{user}', 'destroy')->name('destroy');
            Route::patch('/{user}/restore', 'restore')->name('restore');
            Route::patch('/{user}/role', 'updateRole')->name('update-role');
            Route::get('/{user}/logs', 'logs')->name('logs');
        });
    });
});

// Laravel Breeze/Jetstreamのデフォルト認証ルート
require __DIR__ . '/auth.php';

<?php

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

// ルートURL ('/') にアクセスされたらダッシュボードへリダイレクト
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// 認証済みユーザーのみがアクセスできるルートのグループ
Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Calendar
    Route::get('/calendar', function () {
        return Inertia::render('Calendar');
    })->name('calendar');

    // Notes
    Route::get('/notes', function () {
        return Inertia::render('Notes');
    })->name('notes');

    // Surveys
    Route::get('/surveys', function () {
        return Inertia::render('Surveys');
    })->name('surveys');

    // Survey Results
    Route::get('/surveys/{survey}/results', function () {
        // Note: In a real application, you would pass the specific survey data as a prop.
        return Inertia::render('SurveyResults');
    })->name('surveys.results');

    // Reminders
    Route::get('/reminders', function () {
        return Inertia::render('Reminders');
    })->name('reminders');

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
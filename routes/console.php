<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 部署管理者が不在のアクティブ部署を1日1回検知
Schedule::command('department:check-missing-admins')->dailyAt('09:00');

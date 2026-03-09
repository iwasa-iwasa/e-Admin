<?php

namespace App\Providers;

use App\Models\Department;
use App\Observers\DepartmentObserver;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // 部署オブザーバーを登録（部署作成時にカレンダー自動生成等）
        Department::observe(DepartmentObserver::class);
    }
}

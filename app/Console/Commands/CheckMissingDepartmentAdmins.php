<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Department;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Log;

class CheckMissingDepartmentAdmins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'department:check-missing-admins';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for active departments without a department admin and notify system admins.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for departments missing an admin...');

        $departmentsWithoutAdmin = Department::where('is_active', true)
            ->whereDoesntHave('users', function ($query) {
                $query->where('role_type', 'department_admin')
                      ->where('is_active', true);
            })
            ->get();

        if ($departmentsWithoutAdmin->isEmpty()) {
            $this->info('All active departments have an admin.');
            return Command::SUCCESS;
        }

        $departmentNames = $departmentsWithoutAdmin->pluck('name')->implode(', ');
        $message = "以下の部署で管理者が不在です: {$departmentNames}";
        
        $this->warn($message);
        Log::warning('Missing Department Admins detected.', ['departments' => $departmentsWithoutAdmin->pluck('id')->toArray()]);

        // 全社管理者に通知
        $systemAdmins = User::where('role_type', 'admin')
            ->where('is_active', true)
            ->get();

        foreach ($systemAdmins as $admin) {
            // SystemNotification クラスは既存のものを再利用または新規作成を想定
            // Laravelの Notification ファサードではなく標準のDatabaseNotificationなどが使えればそちらで
            $admin->notifications()->create([
                'id' => \Illuminate\Support\Str::uuid(),
                'type' => 'App\Notifications\SystemNotification',
                'data' => [
                    'message' => $message,
                    'type' => 'warning',
                    'action_url' => '/admin/departments',
                ],
                'read_at' => null,
            ]);
        }

        $this->info('Notifications sent to system admins.');
        return Command::SUCCESS;
    }
}

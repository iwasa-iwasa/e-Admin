<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Department;
use App\Models\User;
use App\Models\Calendar;

class DepartmentSystemSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function() {
            // 1. 部署を作成
            $this->command->info('📁 部署を作成中...');
            
            $departments = [
                ['name' => '総務部'],
                ['name' => '営業部'],
                ['name' => '開発部'],
                ['name' => '経理部'],
            ];
            
            foreach ($departments as $dept) {
                if (!Department::where('name', $dept['name'])->exists()) {
                    Department::create($dept);
                    $this->command->info("   ✓ {$dept['name']}を作成");
                }
            }
            
            // 2. 全社カレンダーを作成
            $this->command->info('📅 全社カレンダーを作成中...');
            
            if (!Calendar::where('owner_type', 'company')->exists()) {
                Calendar::create([
                    'calendar_name' => '全社カレンダー',
                    'calendar_type' => 'shared',
                    'owner_type' => 'company',
                    'owner_id' => null,
                ]);
                $this->command->info('   ✓ 全社カレンダーを作成');
            }
            
            // 3. 全社管理者アカウントを作成
            $this->command->info('👤 管理者アカウントを作成中...');
            
            $somubuDept = Department::where('name', '総務部')->first();
            $eigyobuDept = Department::where('name', '営業部')->first();
            $kaihatsubuDept = Department::where('name', '開発部')->first();
            $keiriDept = Department::where('name', '経理部')->first();
            
            // 全社管理者（部署なし）
            if (!User::where('email', 'admin@company.com')->exists()) {
                User::create([
                    'name' => '全社管理者',
                    'email' => 'admin@company.com',
                    'password' => Hash::make('password'),
                    'department' => null,
                    'department_id' => null,
                    'role' => 'admin',
                    'role_type' => 'company_admin',
                    'is_active' => true,
                ]);
                $this->command->info('   ✓ 全社管理者: admin@company.com / password');
            }
            
            // 総務部管理者
            if (!User::where('email', 'somubu@company.com')->exists()) {
                User::create([
                    'name' => '総務部管理者',
                    'email' => 'somubu@company.com',
                    'password' => Hash::make('password'),
                    'department' => '総務部',
                    'department_id' => $somubuDept->id,
                    'role' => 'user',
                    'role_type' => 'department_admin',
                    'is_active' => true,
                ]);
                $this->command->info('   ✓ 総務部管理者: somubu@company.com / password');
            }
            
            // 営業部管理者
            if (!User::where('email', 'eigyobu@company.com')->exists()) {
                User::create([
                    'name' => '営業部管理者',
                    'email' => 'eigyobu@company.com',
                    'password' => Hash::make('password'),
                    'department' => '営業部',
                    'department_id' => $eigyobuDept->id,
                    'role' => 'user',
                    'role_type' => 'department_admin',
                    'is_active' => true,
                ]);
                $this->command->info('   ✓ 営業部管理者: eigyobu@company.com / password');
            }
            
            // 開発部管理者
            if (!User::where('email', 'kaihatsubu@company.com')->exists()) {
                User::create([
                    'name' => '開発部管理者',
                    'email' => 'kaihatsubu@company.com',
                    'password' => Hash::make('password'),
                    'department' => '開発部',
                    'department_id' => $kaihatsubuDept->id,
                    'role' => 'user',
                    'role_type' => 'department_admin',
                    'is_active' => true,
                ]);
                $this->command->info('   ✓ 開発部管理者: kaihatsubu@company.com / password');
            }
            
            // 経理部管理者
            if (!User::where('email', 'keiri@company.com')->exists()) {
                User::create([
                    'name' => '経理部管理者',
                    'email' => 'keiri@company.com',
                    'password' => Hash::make('password'),
                    'department' => '経理部',
                    'department_id' => $keiriDept->id,
                    'role' => 'user',
                    'role_type' => 'department_admin',
                    'is_active' => true,
                ]);
                $this->command->info('   ✓ 経理部管理者: keiri@company.com / password');
            }
            
            $this->command->info('');
            $this->command->info('✅ 初期データの投入が完了しました！');
        });
    }
}

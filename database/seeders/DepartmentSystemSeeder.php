<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DepartmentSystemSeeder extends Seeder
{
    public function run(): void
    {
        // 全社管理者アカウントを作成
        $companyAdmin = DB::table('users')->insertGetId([
            'name' => '全社管理者',
            'email' => 'admin@company.com',
            'password' => Hash::make('password'),
            'department' => '総務部',
            'department_id' => DB::table('departments')->where('name', '総務部')->value('id'),
            'role' => 'admin',
            'role_type' => 'company_admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 総務部管理者アカウントを作成
        $somubuAdmin = DB::table('users')->insertGetId([
            'name' => '総務部管理者',
            'email' => 'somubu@company.com',
            'password' => Hash::make('password'),
            'department' => '総務部',
            'department_id' => DB::table('departments')->where('name', '総務部')->value('id'),
            'role' => 'user',
            'role_type' => 'department_admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 営業部管理者アカウントを作成
        $eigyobuAdmin = DB::table('users')->insertGetId([
            'name' => '営業部管理者',
            'email' => 'eigyobu@company.com',
            'password' => Hash::make('password'),
            'department' => '営業部',
            'department_id' => DB::table('departments')->where('name', '営業部')->value('id'),
            'role' => 'user',
            'role_type' => 'department_admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 開発部管理者アカウントを作成
        $kaihatsubuAdmin = DB::table('users')->insertGetId([
            'name' => '開発部管理者',
            'email' => 'kaihatsubu@company.com',
            'password' => Hash::make('password'),
            'department' => '開発部',
            'department_id' => DB::table('departments')->where('name', '開発部')->value('id'),
            'role' => 'user',
            'role_type' => 'department_admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('✅ 管理者アカウントを作成しました:');
        $this->command->info('   - 全社管理者: admin@company.com / password');
        $this->command->info('   - 総務部管理者: somubu@company.com / password');
        $this->command->info('   - 営業部管理者: eigyobu@company.com / password');
        $this->command->info('   - 開発部管理者: kaihatsubu@company.com / password');
    }
}

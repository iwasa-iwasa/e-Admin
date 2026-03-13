<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Department;
use \App\Models\Calendar;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // DepartmentSystemSeederで管理者アカウントが作成されるため、
        // ここでは追加の一般ユーザーを各部署に割り当てて作成
        
        $somuDepartment = Department::where('name', '総務部')->first();
        if (!$somuDepartment) {
            $this->command->warn('総務部が見つかりません。DepartmentSystemSeederを先に実行してください。');
            return;
        }
        
        // 3名のユーザーを総務部に作成
        for ($i = 0; $i < 3; $i++) {
            User::factory()->create([
                'department' => $somuDepartment->name,
                'department_id' => $somuDepartment->id,
                'role' => 'user',
                'role_type' => 'member',
            ]);
        }
        
        $this->command->info('✓ 3名のランダムユーザーを総務部に割り当てました');
    }
}

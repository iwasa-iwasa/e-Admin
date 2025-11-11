<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Reminder;

class ReminderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::inRandomOrder()->first();
        if (!$user) {
            $this->command->info('No users found, skipping ReminderSeeder.');
            return;
        }

        $reminders = [
            [
                'title' => '経費精算の提出',
                'deadline' => '2025-10-18',
                'category' => '業務',
                'completed' => false,
                'description' => '9月分の経費精算を提出する',
            ],
            [
                'title' => '年末調整書類の確認',
                'deadline' => '2025-10-25',
                'category' => '人事',
                'completed' => false,
                'description' => '必要書類を確認し、準備する',
            ],
            [
                'title' => '会議室予約（来週分）',
                'deadline' => '2025-10-20',
                'category' => '業務',
                'completed' => false,
                'description' => '来週の会議用に会議室を予約',
            ],
        ];

        foreach ($reminders as $reminderData) {
            Reminder::create(array_merge($reminderData, [
                'user_id' => $user->id,
            ]));
        }
    }
}
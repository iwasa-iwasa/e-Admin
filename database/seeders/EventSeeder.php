<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Calendar;
use App\Models\Event;
use Illuminate\Support\Facades\Date;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user to be the creator/assignee
        $user = User::first();
        if (!$user) {
            // If no user, maybe run UserSeeder first
            $this->command->call('db:seed', ['--class' => 'UserSeeder']);
            $user = User::first();
        }

        // Create a default calendar for the user
        $calendar = Calendar::create([
            'calendar_name' => '部署内共有カレンダー',
            'calendar_type' => 'group',
            'owner_id' => $user->id,
        ]);

        $events = [
            [
                'title' => '経営戦略会議',
                'description' => 'Q4の経営戦略を策定する重要会議',
                'location' => '大会議室',
                'category' => '会議',
                'importance' => '高',
                'start_date' => '2025-10-14',
                'start_time' => '09:00',
                'end_date' => '2025-10-17',
                'end_time' => '17:00',
                'is_all_day' => false,
            ],
            [
                'title' => 'チームMTG',
                'description' => '週次のチームミーティング。進捗確認と今週のタスク割り振り',
                'location' => '会議室A',
                'category' => 'MTG',
                'importance' => '中',
                'start_date' => '2025-10-14',
                'start_time' => '09:00',
                'end_date' => '2025-10-14',
                'end_time' => '10:00',
                'is_all_day' => false,
            ],
            [
                'title' => '備品発注',
                'description' => '月次の備品発注作業',
                'location' => '',
                'category' => '業務',
                'importance' => '中',
                'start_date' => '2025-10-14',
                'start_time' => '14:00',
                'end_date' => '2025-10-14',
                'end_time' => '15:00',
                'is_all_day' => false,
            ],
            [
                'title' => '新システム導入研修',
                'description' => '新勤怠管理システムの全社員向け研修',
                'location' => '研修室',
                'category' => '研修',
                'importance' => '中',
                'start_date' => '2025-10-20',
                'start_time' => '10:00',
                'end_date' => '2025-10-22',
                'end_time' => '17:00',
                'is_all_day' => false,
            ],
        ];

        foreach ($events as $eventData) {
            $event = Event::create(array_merge($eventData, [
                'calendar_id' => $calendar->calendar_id,
                'created_by' => $user->id,
            ]));

            // Attach the creator as a participant
            $event->participants()->attach($user->id, ['response_status' => 'accepted']);
        }
    }
}
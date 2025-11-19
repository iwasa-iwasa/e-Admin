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

        // Get the shared calendar
        $calendar = Calendar::first();
        if (!$calendar) {
            $this->command->error('No calendar found. Run DatabaseSeeder first.');
            return;
        }

        // サンプルイベントデータを100件生成
        $categories = ['会議', 'MTG', '業務', '期限', '有給', '重要'];
        $importances = ['重要', '中', '低'];
        $locations = ['大会議室', '会議室A', '会議室B', '研修室', 'オンライン', ''];
        $faker = \Faker\Factory::create('ja_JP');
           // すべてのユーザーを取得
        $users = User::all();
        if ($users->isEmpty()) {
            // If no user, maybe run UserSeeder first
            $this->command->call('db:seed', ['--class' => 'UserSeeder']);
            $users = User::all();
        }

        $events = [];
        for ($i = 1; $i <= 100; $i++) {
            $category = $categories[array_rand($categories)];
            $importance = $importances[array_rand($importances)];
            $location = $locations[array_rand($locations)];
               // ランダムなユーザーを選択
            $randomUser = $users->random();
               // 2025年9月〜12月の間でランダムな日付
            $month = rand(9, 12);
            $day = rand(1, 28);
            $startDate = sprintf('2025-%02d-%02d', $month, $day);
            $durationDays = rand(0, 2);
            $endDate = date('Y-m-d', strtotime($startDate . " +{$durationDays} day"));
            $startHour = rand(8, 17);
            $endHour = min($startHour + rand(1, 3), 18);
            $events[] = [
                'title' => $faker->realText(10),
                'description' => $faker->realText(40),
                'location' => $location,
                'category' => $category,
                'importance' => $importance,
                'start_date' => $startDate,
                'start_time' => sprintf('%02d:00', $startHour),
                'end_date' => $endDate,
                'end_time' => sprintf('%02d:00', $endHour),
                'is_all_day' => false,
                'progress' => rand(0, 100),
            ];
        }

        foreach ($events as $eventData) {
            // ランダムなユーザーを選択
            $randomUser = $users->random();
            $event = Event::create(array_merge($eventData, [
                'calendar_id' => $calendar->calendar_id,
                'created_by' => $randomUser->id,
            ]));

            // Attach the creator as a participant
            $event->participants()->attach($randomUser->id, ['response_status' => 'accepted']);
        }
    }
}
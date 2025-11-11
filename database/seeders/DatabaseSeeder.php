<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 共有カレンダーを1つだけ作成
        \App\Models\Calendar::firstOrCreate(
            ['calendar_name' => '共有カレンダー'],
            ['calendar_type' => 'shared']
        );

        $this->call([
            UserSeeder::class,
            EventSeeder::class,
            NoteSeeder::class,
            ReminderSeeder::class,
            SurveySeeder::class,
        ]);
    }
}

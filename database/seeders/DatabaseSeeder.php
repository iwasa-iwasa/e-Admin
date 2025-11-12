<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
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
            NoteTagSeeder::class, // NoteSeederより先に実行
            NoteSeeder::class,
            // 他に必要なシーダーがあればここに追加
            // EventSeeder::class,
            // ReminderSeeder::class,
            // SurveySeeder::class,
        ]);
    }
}

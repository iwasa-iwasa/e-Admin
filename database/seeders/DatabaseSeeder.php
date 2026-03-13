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
        // 部署システムを初期化
        $this->call([
            DepartmentSystemSeeder::class,
        ]);

        // 共有カレンダーを作成（既存の場合はスキップ）
        \App\Models\Calendar::firstOrCreate(
            ['calendar_name' => '共有カレンダー'],
            ['calendar_type' => 'shared']
        );

        $this->call([
            UserSeeder::class,
            NoteTagSeeder::class,
            NoteSeeder::class,
            EventSeeder::class,
            ReminderSeeder::class,
            SurveySeeder::class,
            TestDataSeeder::class, // テストデータを追加
        ]);
    }
}

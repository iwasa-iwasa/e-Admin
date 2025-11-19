<?php

namespace Database\Seeders;

use App\Models\NoteTag;
use App\Models\SharedNote;
use App\Models\User;
use Illuminate\Database\Seeder;

class NoteSeeder extends Seeder
{
    public function run(): void
    {
        // ユーザーとタグが存在することを確認
        if (User::count() == 0 || NoteTag::count() == 0) {
            $this->command->warn('ユーザーまたはタグが存在しないため、ノートのシーディングをスキップしました。');
            return;
        }

        $users = User::all();
        $tags = NoteTag::pluck('tag_id');
        $faker = \Faker\Factory::create('ja_JP');
        $priorities = ['high', 'medium', 'low'];
        $colors = ['yellow', 'blue', 'green', 'pink', 'purple'];

        // 50個の共有ノートを作成
        for ($i = 0; $i < 50; $i++) {
            $hasDeadline = rand(0, 1); // 50%の確率で期限あり
            $deadlineDate = null;
            $deadlineTime = null;
            
            if ($hasDeadline) {
                $deadlineDate = $faker->dateTimeBetween('now', '+3 months')->format('Y-m-d');
                $deadlineTime = sprintf('%02d:%02d:00', rand(9, 18), rand(0, 59));
            }

            $note = SharedNote::create([
                'title' => $faker->realText(30),
                'content' => $faker->realText(200),
                'author_id' => $users->random()->id,
                'priority' => $priorities[array_rand($priorities)],
                'color' => $colors[array_rand($colors)],
                'deadline_date' => $deadlineDate,
                'deadline_time' => $deadlineTime,
                'progress' => rand(0, 100),
                'is_deleted' => false,
            ]);

            // 1〜3個のランダムなタグを添付
            $note->tags()->attach(
                $tags->random(rand(1, 3))
            );

            // 各ユーザーに対して20%の確率でノートをピン留めする
            foreach ($users as $user) {
                if (rand(1, 100) <= 20) {
                    $user->pinnedNotes()->attach($note->note_id);
                }
            }
        }
    }
}

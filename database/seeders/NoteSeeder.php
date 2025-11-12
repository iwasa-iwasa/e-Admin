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

        // 50個の共有ノートを作成
        SharedNote::factory(50)->create()->each(function ($note) use ($users, $tags) {
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
        });
    }
}

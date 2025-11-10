<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\SharedNote;
use App\Models\NoteTag;

class NoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first();
        if (!$user) {
            $this->command->call('db:seed', ['--class' => 'UserSeeder']);
            $user = User::where('email', 'test@example.com')->first();
        }

        $notes = [
            [
                'title' => '備品発注リスト',
                'content' => "・コピー用紙 A4 10箱\n・ボールペン 黒 50本\n・クリアファイル 100枚",
                'pinned' => true,
                'tags' => ['備品', '発注'],
                'color' => 'yellow',
                'priority' => 'high',
                'deadline' => '2025-10-20',
            ],
            [
                'title' => '来客対応メモ',
                'content' => "10/15 14:00 A社 山本様\n会議室Bを予約済み",
                'pinned' => true,
                'tags' => ['来客', '会議'],
                'color' => 'blue',
                'priority' => 'high',
                'deadline' => '2025-10-15',
            ],
            [
                'title' => '月次報告の進捗',
                'content' => "経理：完了（10/10）\n人事：作業中（進捗80%）\n総務：未着手",
                'pinned' => false,
                'tags' => ['報告', '進捗'],
                'color' => 'green',
                'priority' => 'medium',
                'deadline' => '2025-10-18',
            ],
        ];

        foreach ($notes as $noteData) {
            $note = SharedNote::create([
                'title' => $noteData['title'],
                'content' => $noteData['content'],
                'author_id' => $user->id,
                'color' => $noteData['color'],
                'priority' => $noteData['priority'],
                'deadline' => $noteData['deadline'],
                'pinned' => $noteData['pinned'],
            ]);

            $tagIds = [];
            foreach ($noteData['tags'] as $tagName) {
                $tag = NoteTag::firstOrCreate(['tag_name' => $tagName]);
                $tagIds[] = $tag->tag_id;
            }

            // The relationship for tags needs to be added to the SharedNote model
            $note->tags()->sync($tagIds);
        }
    }
}
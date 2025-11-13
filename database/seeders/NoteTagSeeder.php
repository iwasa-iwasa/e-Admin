<?php

    namespace Database\Seeders;

    use App\Models\NoteTag;
    use Illuminate\Database\Seeder;

    class NoteTagSeeder extends Seeder
    {
        public function run(): void
        {
            $tags = ['重要', '会議', '個人用', 'アイデア', '至急', '後で確認'];
            foreach ($tags as $tag) {
                NoteTag::firstOrCreate(['tag_name' => $tag]);
            }
        }
    }
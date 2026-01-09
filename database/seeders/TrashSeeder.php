<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TrashSeeder extends Seeder
{
    public function run()
    {
        $users = DB::table('users')->get();
        
        if ($users->isEmpty()) {
            $this->command->error('No users found. Please run UserSeeder first.');
            return;
        }

        $trashItems = [];
        $itemId = 1;

        // 共有カレンダー（全メンバーが作成）
        foreach ($users as $user) {
            $trashItems[] = [
                'item_type' => 'event',
                'item_id' => $user->id * 1000 + 1,
                'original_title' => $user->name . 'の会議',
                'creator_name' => $user->name,
                'user_id' => $user->id,
                'is_shared' => true,
                'deleted_at' => Carbon::now()->subDays(rand(1, 30)),
                'permanent_delete_at' => Carbon::now()->addDays(30),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        // 共有メモ（全メンバーが作成）
        foreach ($users as $user) {
            $trashItems[] = [
                'item_type' => 'shared_note',
                'item_id' => $user->id * 1000 + 2,
                'original_title' => $user->name . 'の共有メモ',
                'creator_name' => $user->name,
                'user_id' => $user->id,
                'is_shared' => true,
                'deleted_at' => Carbon::now()->subDays(rand(1, 30)),
                'permanent_delete_at' => Carbon::now()->addDays(30),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        // アンケート（全メンバーが作成）
        foreach ($users as $user) {
            $trashItems[] = [
                'item_type' => 'survey',
                'item_id' => $user->id * 1000 + 3,
                'original_title' => $user->name . 'のアンケート',
                'creator_name' => $user->name,
                'user_id' => $user->id,
                'is_shared' => true,
                'deleted_at' => Carbon::now()->subDays(rand(1, 30)),
                'permanent_delete_at' => Carbon::now()->addDays(30),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        // 個人リマインダー（各ユーザーがランダムに1-3個作成）
        foreach ($users as $user) {
            $reminderCount = rand(1, 3);
            for ($i = 1; $i <= $reminderCount; $i++) {
                $trashItems[] = [
                    'item_type' => 'reminder',
                    'item_id' => $user->id * 1000 + 100 + $i,
                    'original_title' => $user->name . 'のリマインダー ' . $i,
                    'creator_name' => $user->name,
                    'user_id' => $user->id,
                    'is_shared' => false,
                    'deleted_at' => Carbon::now()->subDays(rand(1, 30)),
                    'permanent_delete_at' => Carbon::now()->addDays(30),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ];
            }
        }

        DB::table('trash_items')->insert($trashItems);
        
        $this->command->info('Trash items seeded successfully!');
    }
}
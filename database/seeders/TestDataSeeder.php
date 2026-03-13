<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Department;
use App\Models\User;
use App\Models\Calendar;
use App\Models\Event;
use App\Models\SharedNote;
use App\Models\TrashItem;
use Carbon\Carbon;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function() {
            $this->command->info('🧪 テストデータを作成中...');
            
            // 部署を取得
            $eigyobu = Department::where('name', '営業部')->first();
            $kaihatsubu = Department::where('name', '開発部')->first();
            $keiri = Department::where('name', '経理部')->first();
            
            if (!$eigyobu || !$kaihatsubu || !$keiri) {
                $this->command->error('❌ 部署が見つかりません。先にDepartmentSystemSeederを実行してください。');
                return;
            }
            
            // 1. 各部署にメンバーを追加（管理者1人 + メンバー3人）
            $this->command->info('👥 部署メンバーを作成中...');
            
            // 営業部メンバー
            $eigyobuMembers = [
                ['name' => '営業部 田中', 'email' => 'eigyo.tanaka@company.com'],
                ['name' => '営業部 佐藤', 'email' => 'eigyo.sato@company.com'],
                ['name' => '営業部 鈴木', 'email' => 'eigyo.suzuki@company.com'],
            ];
            
            foreach ($eigyobuMembers as $member) {
                if (!User::where('email', $member['email'])->exists()) {
                    User::create([
                        'name' => $member['name'],
                        'email' => $member['email'],
                        'password' => Hash::make('password'),
                        'department_id' => $eigyobu->id,
                        'role' => 'user',
                        'role_type' => 'member',
                        'is_active' => true,
                    ]);
                    $this->command->info("   ✓ {$member['name']}: {$member['email']} / password");
                }
            }
            
            // 開発部メンバー
            $kaihatsubuMembers = [
                ['name' => '開発部 山田', 'email' => 'dev.yamada@company.com'],
                ['name' => '開発部 伊藤', 'email' => 'dev.ito@company.com'],
                ['name' => '開発部 渡辺', 'email' => 'dev.watanabe@company.com'],
            ];
            
            foreach ($kaihatsubuMembers as $member) {
                if (!User::where('email', $member['email'])->exists()) {
                    User::create([
                        'name' => $member['name'],
                        'email' => $member['email'],
                        'password' => Hash::make('password'),
                        'department_id' => $kaihatsubu->id,
                        'role' => 'user',
                        'role_type' => 'member',
                        'is_active' => true,
                    ]);
                    $this->command->info("   ✓ {$member['name']}: {$member['email']} / password");
                }
            }
            
            // 経理部メンバー
            $keiriMembers = [
                ['name' => '経理部 中村', 'email' => 'keiri.nakamura@company.com'],
                ['name' => '経理部 小林', 'email' => 'keiri.kobayashi@company.com'],
                ['name' => '経理部 加藤', 'email' => 'keiri.kato@company.com'],
            ];
            
            foreach ($keiriMembers as $member) {
                if (!User::where('email', $member['email'])->exists()) {
                    User::create([
                        'name' => $member['name'],
                        'email' => $member['email'],
                        'password' => Hash::make('password'),
                        'department_id' => $keiri->id,
                        'role' => 'user',
                        'role_type' => 'member',
                        'is_active' => true,
                    ]);
                    $this->command->info("   ✓ {$member['name']}: {$member['email']} / password");
                }
            }
            
            // 2. 部署カレンダーを作成
            $this->command->info('📅 部署カレンダーを作成中...');
            
            $departmentCalendars = [
                ['name' => '営業部カレンダー', 'dept' => $eigyobu],
                ['name' => '開発部カレンダー', 'dept' => $kaihatsubu],
                ['name' => '経理部カレンダー', 'dept' => $keiri],
            ];
            
            foreach ($departmentCalendars as $cal) {
                if (!Calendar::where('owner_type', 'department')->where('owner_id', $cal['dept']->id)->exists()) {
                    Calendar::create([
                        'calendar_name' => $cal['name'],
                        'calendar_type' => 'shared',
                        'owner_type' => 'department',
                        'owner_id' => $cal['dept']->id,
                    ]);
                    $this->command->info("   ✓ {$cal['name']}を作成");
                }
            }
            
            // 3. 共有カレンダーの予定を作成
            $this->command->info('📆 カレンダー予定を作成中...');
            
            $companyCalendar = Calendar::where('owner_type', 'company')->first();
            $eigyobuCalendar = Calendar::where('owner_type', 'department')->where('owner_id', $eigyobu->id)->first();
            $kaihatsubuCalendar = Calendar::where('owner_type', 'department')->where('owner_id', $kaihatsubu->id)->first();
            
            $eigyobuAdmin = User::where('email', 'eigyobu@company.com')->first();
            $kaihatsubuAdmin = User::where('email', 'kaihatsubu@company.com')->first();
            
            // 全社カレンダーの予定
            if ($companyCalendar) {
                $companyEvents = [
                    [
                        'title' => '全社会議',
                        'description' => '月次全社会議',
                        'start_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
                        'start_time' => '10:00:00',
                        'end_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
                        'end_time' => '12:00:00',
                        'category' => '会議',
                        'importance' => '重要',
                    ],
                    [
                        'title' => '会社創立記念日',
                        'description' => '創立記念日のため休業',
                        'start_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
                        'end_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
                        'is_all_day' => true,
                        'category' => '休暇',
                        'importance' => '重要',
                    ],
                ];
                
                foreach ($companyEvents as $eventData) {
                    Event::create(array_merge($eventData, [
                        'calendar_id' => $companyCalendar->calendar_id,
                        'created_by' => 1, // 全社管理者
                        'visibility_type' => 'public',
                        'version' => 1,
                    ]));
                }
                $this->command->info('   ✓ 全社カレンダーに2件の予定を作成');
            }
            
            // 営業部カレンダーの予定
            if ($eigyobuCalendar && $eigyobuAdmin) {
                $eigyobuEvents = [
                    [
                        'title' => '営業部ミーティング',
                        'description' => '週次営業報告会',
                        'start_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                        'start_time' => '14:00:00',
                        'end_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                        'end_time' => '15:00:00',
                        'category' => '会議',
                    ],
                    [
                        'title' => '顧客訪問',
                        'description' => 'A社訪問',
                        'start_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
                        'start_time' => '13:00:00',
                        'end_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
                        'end_time' => '17:00:00',
                        'category' => '来客',
                    ],
                ];
                
                foreach ($eigyobuEvents as $eventData) {
                    Event::create(array_merge($eventData, [
                        'calendar_id' => $eigyobuCalendar->calendar_id,
                        'created_by' => $eigyobuAdmin->id,
                        'owner_department_id' => $eigyobu->id,
                        'visibility_type' => 'department',
                        'version' => 1,
                    ]));
                }
                $this->command->info('   ✓ 営業部カレンダーに2件の予定を作成');
            }
            
            // 開発部カレンダーの予定
            if ($kaihatsubuCalendar && $kaihatsubuAdmin) {
                $kaihatsubuEvents = [
                    [
                        'title' => 'スプリントレビュー',
                        'description' => '2週間のスプリント成果発表',
                        'start_date' => Carbon::now()->addDays(4)->format('Y-m-d'),
                        'start_time' => '15:00:00',
                        'end_date' => Carbon::now()->addDays(4)->format('Y-m-d'),
                        'end_time' => '16:00:00',
                        'category' => '会議',
                    ],
                    [
                        'title' => 'システムメンテナンス',
                        'description' => '定期メンテナンス作業',
                        'start_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
                        'start_time' => '20:00:00',
                        'end_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
                        'end_time' => '23:00:00',
                        'category' => '業務',
                    ],
                ];
                
                foreach ($kaihatsubuEvents as $eventData) {
                    Event::create(array_merge($eventData, [
                        'calendar_id' => $kaihatsubuCalendar->calendar_id,
                        'created_by' => $kaihatsubuAdmin->id,
                        'owner_department_id' => $kaihatsubu->id,
                        'visibility_type' => 'department',
                        'version' => 1,
                    ]));
                }
                $this->command->info('   ✓ 開発部カレンダーに2件の予定を作成');
            }
            
            // 4. 共有メモを作成
            $this->command->info('📝 共有メモを作成中...');
            
            // 全社公開メモ
            $publicNotes = [
                [
                    'title' => '社内規定の更新について',
                    'content' => '来月より新しい社内規定が適用されます。詳細は添付資料をご確認ください。',
                    'author_id' => 1,
                    'color' => 'blue',
                    'priority' => 'high',
                    'visibility_type' => 'public',
                ],
                [
                    'title' => '年末年始の営業日程',
                    'content' => '12月29日〜1月3日まで休業いたします。',
                    'author_id' => 1,
                    'color' => 'yellow',
                    'priority' => 'medium',
                    'visibility_type' => 'public',
                ],
            ];
            
            foreach ($publicNotes as $noteData) {
                SharedNote::create($noteData);
            }
            $this->command->info('   ✓ 全社公開メモを2件作成');
            
            // 営業部メモ
            if ($eigyobuAdmin) {
                $eigyobuNotes = [
                    [
                        'title' => '営業目標達成状況',
                        'content' => '今月の営業目標達成率は85%です。残り2週間で目標達成を目指しましょう。',
                        'author_id' => $eigyobuAdmin->id,
                        'color' => 'green',
                        'priority' => 'high',
                        'visibility_type' => 'department',
                        'owner_department_id' => $eigyobu->id,
                    ],
                    [
                        'title' => '顧客リスト更新',
                        'content' => '新規顧客情報を追加しました。CRMシステムをご確認ください。',
                        'author_id' => $eigyobuAdmin->id,
                        'color' => 'blue',
                        'priority' => 'medium',
                        'visibility_type' => 'department',
                        'owner_department_id' => $eigyobu->id,
                    ],
                ];
                
                foreach ($eigyobuNotes as $noteData) {
                    SharedNote::create($noteData);
                }
                $this->command->info('   ✓ 営業部メモを2件作成');
            }
            
            // 開発部メモ
            if ($kaihatsubuAdmin) {
                $kaihatsubuNotes = [
                    [
                        'title' => 'コーディング規約の更新',
                        'content' => 'TypeScript 5.0に対応した新しいコーディング規約を策定しました。',
                        'author_id' => $kaihatsubuAdmin->id,
                        'color' => 'purple',
                        'priority' => 'high',
                        'visibility_type' => 'department',
                        'owner_department_id' => $kaihatsubu->id,
                    ],
                    [
                        'title' => 'テスト環境のURL',
                        'content' => 'テスト環境: https://test.example.com\nID/PW: test/test123',
                        'author_id' => $kaihatsubuAdmin->id,
                        'color' => 'yellow',
                        'priority' => 'medium',
                        'visibility_type' => 'department',
                        'owner_department_id' => $kaihatsubu->id,
                    ],
                ];
                
                foreach ($kaihatsubuNotes as $noteData) {
                    SharedNote::create($noteData);
                }
                $this->command->info('   ✓ 開発部メモを2件作成');
            }
            
            // 5. ゴミ箱データを作成（削除済みの予定とメモ）
            $this->command->info('🗑️  ゴミ箱データを作成中...');
            
            // 削除済み予定を作成
            if ($eigyobuCalendar && $eigyobuAdmin) {
                $deletedEvent = Event::create([
                    'title' => '【削除済み】過去の営業会議',
                    'description' => 'この予定は削除されました',
                    'start_date' => Carbon::now()->subDays(10)->format('Y-m-d'),
                    'start_time' => '10:00:00',
                    'end_date' => Carbon::now()->subDays(10)->format('Y-m-d'),
                    'end_time' => '11:00:00',
                    'calendar_id' => $eigyobuCalendar->calendar_id,
                    'created_by' => $eigyobuAdmin->id,
                    'owner_department_id' => $eigyobu->id,
                    'visibility_type' => 'department',
                    'is_deleted' => true,
                    'deleted_at' => Carbon::now()->subDays(5),
                    'version' => 1,
                ]);
                
                TrashItem::create([
                    'user_id' => $eigyobuAdmin->id,
                    'item_type' => 'event',
                    'is_shared' => true,
                    'item_id' => $deletedEvent->event_id,
                    'original_title' => $deletedEvent->title,
                    'owner_department_id' => $eigyobu->id,
                    'visibility_type' => 'department',
                    'deleted_at' => Carbon::now()->subDays(5),
                    'permanent_delete_at' => Carbon::now()->addDays(25),
                ]);
                $this->command->info('   ✓ 削除済み予定を1件作成');
            }
            
            // 削除済みメモを作成
            if ($kaihatsubuAdmin) {
                $deletedNote = SharedNote::create([
                    'title' => '【削除済み】古い開発メモ',
                    'content' => 'このメモは削除されました',
                    'author_id' => $kaihatsubuAdmin->id,
                    'color' => 'yellow',
                    'priority' => 'low',
                    'visibility_type' => 'department',
                    'owner_department_id' => $kaihatsubu->id,
                    'is_deleted' => true,
                    'deleted_at' => Carbon::now()->subDays(3),
                ]);
                
                TrashItem::create([
                    'user_id' => $kaihatsubuAdmin->id,
                    'item_type' => 'shared_note',
                    'is_shared' => true,
                    'item_id' => $deletedNote->note_id,
                    'original_title' => $deletedNote->title,
                    'owner_department_id' => $kaihatsubu->id,
                    'visibility_type' => 'department',
                    'deleted_at' => Carbon::now()->subDays(3),
                    'permanent_delete_at' => Carbon::now()->addDays(27),
                ]);
                $this->command->info('   ✓ 削除済みメモを1件作成');
            }
            
            $this->command->info('');
            $this->command->info('✅ テストデータの投入が完了しました！');
            $this->command->info('');
            $this->command->info('📋 作成されたアカウント:');
            $this->command->info('   営業部: eigyo.tanaka@company.com / password');
            $this->command->info('   営業部: eigyo.sato@company.com / password');
            $this->command->info('   営業部: eigyo.suzuki@company.com / password');
            $this->command->info('   開発部: dev.yamada@company.com / password');
            $this->command->info('   開発部: dev.ito@company.com / password');
            $this->command->info('   開発部: dev.watanabe@company.com / password');
            $this->command->info('   経理部: keiri.nakamura@company.com / password');
            $this->command->info('   経理部: keiri.kobayashi@company.com / password');
            $this->command->info('   経理部: keiri.kato@company.com / password');
        });
    }
}

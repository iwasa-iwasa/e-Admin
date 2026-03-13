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
            $somubu = Department::where('name', '総務部')->first();
            $eigyobu = Department::where('name', '営業部')->first();
            $kaihatsubu = Department::where('name', '開発部')->first();
            $keiri = Department::where('name', '経理部')->first();
            
            if (!$somubu || !$eigyobu || !$kaihatsubu || !$keiri) {
                $this->command->error('❌ 部署が見つかりません。先にDepartmentSystemSeederを実行してください。');
                return;
            }
            
            // 1. 各部署にメンバーを追加（管理者1人 + メンバー3人）
            $this->command->info('👥 部署メンバーを作成中...');
            
            // 総務部メンバー（aアカウント）
            $somubuMembers = [
                ['name' => 'a', 'email' => 'somu.a@company.com'],
            ];
            
            foreach ($somubuMembers as $member) {
                if (!User::where('email', $member['email'])->exists()) {
                    User::create([
                        'name' => $member['name'],
                        'email' => $member['email'],
                        'password' => Hash::make('password'),
                        'department' => '総務部',
                        'department_id' => $somubu->id,
                        'role' => 'user',
                        'role_type' => 'member',
                        'is_active' => true,
                    ]);
                    $this->command->info("   ✓ {$member['name']}: {$member['email']} / password");
                }
            }
            
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
                        'department' => '営業部',
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
                        'department' => '開発部',
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
                        'department' => '経理部',
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
                ['name' => '総務部カレンダー', 'dept' => $somubu],
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
                $allUsers = User::where('is_active', true)->get();
                $this->command->info("   全ユーザー数: " . $allUsers->count() . "人");
                
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
                        'participants' => $allUsers->pluck('id')->toArray(), // 全員参加
                    ],
                    [
                        'title' => '会社創立記念日',
                        'description' => '創立記念日のため休業',
                        'start_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
                        'end_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
                        'is_all_day' => true,
                        'category' => '休暇',
                        'importance' => '重要',
                        'participants' => [], // 参加者なし（全員が確認できる情報）
                    ],
                ];
                
                foreach ($companyEvents as $eventData) {
                    $participants = $eventData['participants'];
                    unset($eventData['participants']);
                    
                    $event = Event::create(array_merge($eventData, [
                        'calendar_id' => $companyCalendar->calendar_id,
                        'created_by' => 1, // 全社管理者
                        'visibility_type' => 'public',
                        'version' => 1,
                    ]));
                    
                    // 参加者を関連付け（空の場合はスキップ）
                    if (!empty($participants)) {
                        $event->participants()->attach($participants);
                        $this->command->info("     → 参加者 " . count($participants) . "人を関連付け");
                    } else {
                        $this->command->info("     → 参加者なし");
                    }
                }
                $this->command->info('   ✓ 全社カレンダーに2件の予定を作成（参加者付き）');
            }
            
            // 営業部カレンダーの予定
            if ($eigyobuCalendar && $eigyobuAdmin) {
                $eigyobuMembers = User::where('department_id', $eigyobu->id)->get();
                $this->command->info("   営業部メンバー数: " . $eigyobuMembers->count() . "人");
                
                $eigyobuEvents = [
                    [
                        'title' => '営業部ミーティング',
                        'description' => '週次営業報告会',
                        'start_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                        'start_time' => '14:00:00',
                        'end_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                        'end_time' => '15:00:00',
                        'category' => '会議',
                        'participants' => $eigyobuMembers->pluck('id')->toArray(),
                    ],
                    [
                        'title' => '顧客訪問',
                        'description' => 'A社訪問',
                        'start_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
                        'start_time' => '13:00:00',
                        'end_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
                        'end_time' => '17:00:00',
                        'category' => '来客',
                        'participants' => [$eigyobuMembers->first()->id], // 営業部の1人だけ参加
                    ],
                ];
                
                foreach ($eigyobuEvents as $eventData) {
                    $participants = $eventData['participants'];
                    unset($eventData['participants']);
                    
                    $event = Event::create(array_merge($eventData, [
                        'calendar_id' => $eigyobuCalendar->calendar_id,
                        'created_by' => $eigyobuAdmin->id,
                        'owner_department_id' => $eigyobu->id,
                        'visibility_type' => 'department',
                        'version' => 1,
                    ]));
                    
                    // 参加者を関連付け
                    if (!empty($participants)) {
                        $event->participants()->attach($participants);
                        $this->command->info("     → 参加者 " . count($participants) . "人を関連付け");
                    }
                }
                $this->command->info('   ✓ 営業部カレンダーに2件の予定を作成（参加者付き）');
            }
            
            // 開発部カレンダーの予定
            if ($kaihatsubuCalendar && $kaihatsubuAdmin) {
                $kaihatsubuMembers = User::where('department_id', $kaihatsubu->id)->get();
                $this->command->info("   開発部メンバー数: " . $kaihatsubuMembers->count() . "人");
                
                $kaihatsubuEvents = [
                    [
                        'title' => 'スプリントレビュー',
                        'description' => '2週間のスプリント成果発表',
                        'start_date' => Carbon::now()->addDays(4)->format('Y-m-d'),
                        'start_time' => '15:00:00',
                        'end_date' => Carbon::now()->addDays(4)->format('Y-m-d'),
                        'end_time' => '16:00:00',
                        'category' => '会議',
                        'participants' => $kaihatsubuMembers->pluck('id')->toArray(),
                    ],
                    [
                        'title' => 'システムメンテナンス',
                        'description' => '定期メンテナンス作業',
                        'start_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
                        'start_time' => '20:00:00',
                        'end_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
                        'end_time' => '23:00:00',
                        'category' => '業務',
                        'participants' => [$kaihatsubuMembers->first()->id, $kaihatsubuMembers->skip(1)->first()->id], // 2人だけ参加
                    ],
                ];
                
                foreach ($kaihatsubuEvents as $eventData) {
                    $participants = $eventData['participants'];
                    unset($eventData['participants']);
                    
                    $event = Event::create(array_merge($eventData, [
                        'calendar_id' => $kaihatsubuCalendar->calendar_id,
                        'created_by' => $kaihatsubuAdmin->id,
                        'owner_department_id' => $kaihatsubu->id,
                        'visibility_type' => 'department',
                        'version' => 1,
                    ]));
                    
                    // 参加者を関連付け
                    if (!empty($participants)) {
                        $event->participants()->attach($participants);
                        $this->command->info("     → 参加者 " . count($participants) . "人を関連付け");
                    }
                }
                $this->command->info('   ✓ 開発部カレンダーに2件の予定を作成（参加者付き）');
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
            
            // 5. ゴミ箱データを作成（削除済みの予定、メモ、アンケート、リマインダー）
            $this->command->info('🗑️  ゴミ箱データを作成中...');
            
            // 各部署の管理者を取得
            $somuAdmin = User::where('email', 'a@a')->first();
            
            // 削除済み予定を各部署で作成
            $deletedEvents = [
                // 全社カレンダーの削除済み予定
                [
                    'title' => '【削除済み】全社忘年会',
                    'description' => 'この予定は削除されました',
                    'calendar' => $companyCalendar,
                    'user' => User::where('email', 'admin@company.com')->first(),
                    'department' => null,
                ],
                [
                    'title' => '【削除済み】全社研修',
                    'description' => 'この予定は削除されました',
                    'calendar' => $companyCalendar,
                    'user' => User::where('email', 'admin@company.com')->first(),
                    'department' => null,
                ],
                // 営業部の削除済み予定
                [
                    'title' => '【削除済み】営業会議',
                    'description' => 'この予定は削除されました',
                    'calendar' => $eigyobuCalendar,
                    'user' => $eigyobuAdmin,
                    'department' => $eigyobu,
                ],
                [
                    'title' => '【削除済み】顧客プレゼン',
                    'description' => 'この予定は削除されました',
                    'calendar' => $eigyobuCalendar,
                    'user' => $eigyobuAdmin,
                    'department' => $eigyobu,
                ],
                // 開発部の削除済み予定
                [
                    'title' => '【削除済み】コードレビュー',
                    'description' => 'この予定は削除されました',
                    'calendar' => $kaihatsubuCalendar,
                    'user' => $kaihatsubuAdmin,
                    'department' => $kaihatsubu,
                ],
                [
                    'title' => '【削除済み】リリース会議',
                    'description' => 'この予定は削除されました',
                    'calendar' => $kaihatsubuCalendar,
                    'user' => $kaihatsubuAdmin,
                    'department' => $kaihatsubu,
                ],
                // 総務部の削除済み予定
                [
                    'title' => '【削除済み】総務部定例会議',
                    'description' => 'この予定は削除されました',
                    'calendar' => Calendar::where('owner_type', 'department')->where('owner_id', $somubu->id)->first(),
                    'user' => $somuAdmin,
                    'department' => $somubu,
                ],
                [
                    'title' => '【削除済み】人事評価会議',
                    'description' => 'この予定は削除されました',
                    'calendar' => Calendar::where('owner_type', 'department')->where('owner_id', $somubu->id)->first(),
                    'user' => $somuAdmin,
                    'department' => $somubu,
                ],
            ];
            
            foreach ($deletedEvents as $eventData) {
                if ($eventData['calendar'] && $eventData['user']) {
                    $deletedEvent = Event::create([
                        'title' => $eventData['title'],
                        'description' => $eventData['description'],
                        'start_date' => Carbon::now()->subDays(rand(5, 30))->format('Y-m-d'),
                        'start_time' => '10:00:00',
                        'end_date' => Carbon::now()->subDays(rand(5, 30))->format('Y-m-d'),
                        'end_time' => '11:00:00',
                        'calendar_id' => $eventData['calendar']->calendar_id,
                        'created_by' => $eventData['user']->id,
                        'owner_department_id' => $eventData['department']?->id,
                        'visibility_type' => $eventData['department'] ? 'department' : 'public',
                        'is_deleted' => true,
                        'deleted_at' => Carbon::now()->subDays(rand(1, 10)),
                        'version' => 1,
                    ]);
                    
                    TrashItem::create([
                        'user_id' => $eventData['user']->id,
                        'item_type' => 'event',
                        'is_shared' => true,
                        'item_id' => $deletedEvent->event_id,
                        'original_title' => $deletedEvent->title,
                        'owner_department_id' => $eventData['department']?->id,
                        'visibility_type' => $eventData['department'] ? 'department' : 'public',
                        'deleted_at' => $deletedEvent->deleted_at,
                        'permanent_delete_at' => Carbon::now()->addDays(rand(20, 30)),
                    ]);
                }
            }
            $this->command->info('   ✓ 削除済み予定を8件作成');
            
            // 削除済みメモを各部署で作成
            $deletedNotes = [
                // 全社公開の削除済みメモ
                [
                    'title' => '【削除済み】古い社内規定',
                    'content' => 'このメモは削除されました',
                    'user' => User::where('email', 'admin@company.com')->first(),
                    'department' => null,
                    'visibility' => 'public',
                ],
                [
                    'title' => '【削除済み】過去のお知らせ',
                    'content' => 'このメモは削除されました',
                    'user' => User::where('email', 'admin@company.com')->first(),
                    'department' => null,
                    'visibility' => 'public',
                ],
                // 営業部の削除済みメモ
                [
                    'title' => '【削除済み】古い営業資料',
                    'content' => 'このメモは削除されました',
                    'user' => $eigyobuAdmin,
                    'department' => $eigyobu,
                    'visibility' => 'department',
                ],
                [
                    'title' => '【削除済み】過去の売上データ',
                    'content' => 'このメモは削除されました',
                    'user' => $eigyobuAdmin,
                    'department' => $eigyobu,
                    'visibility' => 'department',
                ],
                // 開発部の削除済みメモ
                [
                    'title' => '【削除済み】古い技術メモ',
                    'content' => 'このメモは削除されました',
                    'user' => $kaihatsubuAdmin,
                    'department' => $kaihatsubu,
                    'visibility' => 'department',
                ],
                [
                    'title' => '【削除済み】過去のAPI仕様',
                    'content' => 'このメモは削除されました',
                    'user' => $kaihatsubuAdmin,
                    'department' => $kaihatsubu,
                    'visibility' => 'department',
                ],
                // 総務部の削除済みメモ
                [
                    'title' => '【削除済み】古い人事資料',
                    'content' => 'このメモは削除されました',
                    'user' => $somuAdmin,
                    'department' => $somubu,
                    'visibility' => 'department',
                ],
                [
                    'title' => '【削除済み】過去の勤怠データ',
                    'content' => 'このメモは削除されました',
                    'user' => $somuAdmin,
                    'department' => $somubu,
                    'visibility' => 'department',
                ],
            ];
            
            foreach ($deletedNotes as $noteData) {
                if ($noteData['user']) {
                    $deletedNote = SharedNote::create([
                        'title' => $noteData['title'],
                        'content' => $noteData['content'],
                        'author_id' => $noteData['user']->id,
                        'color' => 'gray',
                        'priority' => 'low',
                        'visibility_type' => $noteData['visibility'],
                        'owner_department_id' => $noteData['department']?->id,
                        'is_deleted' => true,
                        'deleted_at' => Carbon::now()->subDays(rand(1, 15)),
                    ]);
                    
                    TrashItem::create([
                        'user_id' => $noteData['user']->id,
                        'item_type' => 'shared_note',
                        'is_shared' => true,
                        'item_id' => $deletedNote->note_id,
                        'original_title' => $deletedNote->title,
                        'owner_department_id' => $noteData['department']?->id,
                        'visibility_type' => $noteData['visibility'],
                        'deleted_at' => $deletedNote->deleted_at,
                        'permanent_delete_at' => Carbon::now()->addDays(rand(15, 30)),
                    ]);
                }
            }
            $this->command->info('   ✓ 削除済みメモを8件作成');
            
            // 削除済みアンケートを作成
            $deletedSurveys = [
                [
                    'title' => '【削除済み】過去の満足度調査',
                    'user' => User::where('email', 'admin@company.com')->first(),
                    'department' => null,
                    'visibility' => 'public',
                ],
                [
                    'title' => '【削除済み】営業部アンケート',
                    'user' => $eigyobuAdmin,
                    'department' => $eigyobu,
                    'visibility' => 'department',
                ],
                [
                    'title' => '【削除済み】開発環境調査',
                    'user' => $kaihatsubuAdmin,
                    'department' => $kaihatsubu,
                    'visibility' => 'department',
                ],
                [
                    'title' => '【削除済み】総務部業務改善アンケート',
                    'user' => $somuAdmin,
                    'department' => $somubu,
                    'visibility' => 'department',
                ],
            ];
            
            foreach ($deletedSurveys as $surveyData) {
                if ($surveyData['user']) {
                    // 削除済みアンケートのダミーIDを作成（実際のSurveyモデルは作成しない）
                    $dummySurveyId = rand(1000, 9999);
                    
                    TrashItem::create([
                        'user_id' => $surveyData['user']->id,
                        'item_type' => 'survey',
                        'is_shared' => true,
                        'item_id' => $dummySurveyId,
                        'original_title' => $surveyData['title'],
                        'owner_department_id' => $surveyData['department']?->id,
                        'visibility_type' => $surveyData['visibility'],
                        'deleted_at' => Carbon::now()->subDays(rand(1, 20)),
                        'permanent_delete_at' => Carbon::now()->addDays(rand(10, 30)),
                    ]);
                }
            }
            $this->command->info('   ✓ 削除済みアンケートを4件作成');
            
            // 削除済みリマインダーを作成
            $deletedReminders = [
                [
                    'title' => '【削除済み】全社会議の準備',
                    'user' => User::where('email', 'admin@company.com')->first(),
                    'department' => null,
                ],
                [
                    'title' => '【削除済み】営業資料作成',
                    'user' => $eigyobuAdmin,
                    'department' => $eigyobu,
                ],
                [
                    'title' => '【削除済み】コードレビュー準備',
                    'user' => $kaihatsubuAdmin,
                    'department' => $kaihatsubu,
                ],
                [
                    'title' => '【削除済み】人事評価資料準備',
                    'user' => $somuAdmin,
                    'department' => $somubu,
                ],
                [
                    'title' => '【削除済み】月次報告書作成',
                    'user' => User::where('email', 'keiri@company.com')->first(),
                    'department' => $keiri,
                ],
                [
                    'title' => '【削除済み】顧客フォローアップ',
                    'user' => $eigyobuAdmin,
                    'department' => $eigyobu,
                ],
                [
                    'title' => '【削除済み】システムバックアップ',
                    'user' => $kaihatsubuAdmin,
                    'department' => $kaihatsubu,
                ],
                [
                    'title' => '【削除済み】勤怠チェック',
                    'user' => $somuAdmin,
                    'department' => $somubu,
                ],
            ];
            
            foreach ($deletedReminders as $reminderData) {
                if ($reminderData['user']) {
                    // 削除済みリマインダーのダミーIDを作成（実際のReminderモデルは作成しない）
                    $dummyReminderId = rand(1000, 9999);
                    
                    TrashItem::create([
                        'user_id' => $reminderData['user']->id,
                        'item_type' => 'reminder',
                        'is_shared' => false, // リマインダーは個人用
                        'item_id' => $dummyReminderId,
                        'original_title' => $reminderData['title'],
                        'owner_department_id' => $reminderData['department']?->id,
                        'visibility_type' => 'private',
                        'deleted_at' => Carbon::now()->subDays(rand(1, 10)),
                        'permanent_delete_at' => Carbon::now()->addDays(rand(20, 30)),
                    ]);
                }
            }
            $this->command->info('   ✓ 削除済みリマインダーを8件作成');
            
            $this->command->info('');
            $this->command->info('✅ テストデータの投入が完了しました！');
            $this->command->info('');
            $this->command->info('📋 作成されたアカウント:');
            $this->command->info('   総務部: somu.a@company.com / password');
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

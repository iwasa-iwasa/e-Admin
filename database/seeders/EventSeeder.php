<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Calendar;
use App\Models\Event;
use Illuminate\Support\Facades\Date;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user to be the creator/assignee
        $user = User::first();
        if (!$user) {
            // If no user, maybe run UserSeeder first
            $this->command->call('db:seed', ['--class' => 'UserSeeder']);
            $user = User::first();
        }

        // Get the shared calendar
        $calendar = Calendar::first();
        if (!$calendar) {
            $this->command->error('No calendar found. Run DatabaseSeeder first.');
            return;
        }

        // サンプルイベントデータを100件生成
        $categories = ['会議', '休暇', '業務', '来客', '出張'];
        $importances = ['重要', '中', '低'];
        $locations = ['大会議室', '会議室A', '会議室B', '研修室', 'オンライン', ''];
        $faker = \Faker\Factory::create('ja_JP');
           // すべてのユーザーを取得
        $users = User::all();
        if ($users->isEmpty()) {
            // If no user, maybe run UserSeeder first
            $this->command->call('db:seed', ['--class' => 'UserSeeder']);
            $users = User::all();
        }

        $events = [];
        for ($i = 1; $i <= 50; $i++) {
            $category = $categories[array_rand($categories)];
            $importance = $importances[array_rand($importances)];
            $location = $locations[array_rand($locations)];
               // ランダムなユーザーを選択
            $randomUser = $users->random();
               // 2025年9月〜12月の間でランダムな日付
            $month = rand(9, 12);
            $day = rand(1, 28);
            $startDate = sprintf('2025-%02d-%02d', $month, $day);
            $durationDays = rand(0, 2);
            $endDate = date('Y-m-d', strtotime($startDate . " +{$durationDays} day"));
            $startHour = rand(8, 17);
            $endHour = min($startHour + rand(1, 3), 18);
            // カテゴリーに応じたタイトルと説明の候補
            $titles = [
                '会議' => ['定例会議', '進捗報告会', '戦略ミーティング', 'ブレインストーミング', 'キックオフ会議'],
                '休暇' => ['有給休暇', '振替休日', '特別休暇', '半日休暇'],
                '業務' => ['資料作成', 'データ分析', '企画書レビュー', 'コードレビュー', 'タスク消化'],
                '来客' => ['A社来客対応', 'B社訪問', '顧客ミーティング', '営業先訪問', 'パートナー企業面談'],
                '出張' => ['東京出張', '大阪出張', '名古屋出張', '福岡出張'],
            ];

            $descriptions = [
                '会議' => ['先月の進捗確認と来月の目標設定を行います。', '新しいプロジェクトの方向性を議論します。', '市場分析の結果を共有し、戦略を練ります。', '新製品のアイデア出しを行います。', 'プロジェクトの開始にあたり、目的と範囲を共有します。'],
                '休暇' => ['心身のリフレッシュのため、終日休暇をいただきます。', '振替休日として休暇をいただきます。', '特別な事情により特別休暇を取得します。', '午後から私用のため半日休暇をいただきます。'],
                '業務' => ['プロジェクトに関する報告書を作成します。', '月次データの傾向を分析し、レポートを作成します。', '提案書の内容を確認し、最終調整を行います。', '新機能の実装内容についてコードレビューを実施します。', '未完了のタスクを優先順位をつけて処理します。'],
                '来客' => ['A社の営業責任者との定期面談を予定しています。', 'B社の新規プロジェクトに関するミーティングを予定しています。', '既存顧客との満足度確認ミーティングを実施します。', '営業先への定期訪問を予定しています。', 'パートナー企業との関係強化に向けた面談を予定しています。'],
                '出張' => ['東京のクライアント先でのプロジェクト進捗確認会を実施します。', '大阪支社での定期ミーティングに参加します。', '名古屋の顧客先を訪問し、サービス説明を行います。', '福岡での営業活動と既存顧客対応を実施します。'],
            ];

            $title = $titles[$category][array_rand($titles[$category])];
            $description = $descriptions[$category][array_rand($descriptions[$category])];
            $events[] = [
                'title' => $title,
                'description' => $description,
                'location' => $location,
                'category' => $category,
                'importance' => $importance,
                'start_date' => $startDate,
                'start_time' => sprintf('%02d:00', $startHour),
                'end_date' => $endDate,
                'end_time' => sprintf('%02d:00', $endHour),
                'is_all_day' => false,
                'progress' => rand(0, 100),
            ];
        }

        foreach ($events as $eventData) {
            // ランダムなユーザーを選択
            $randomUser = $users->random();
            $event = Event::create(array_merge($eventData, [
                'calendar_id' => $calendar->calendar_id,
                'created_by' => $randomUser->id,
            ]));

            // Attach the creator as a participant
            $event->participants()->attach($randomUser->id, ['response_status' => 'accepted']);
        }
    }
}
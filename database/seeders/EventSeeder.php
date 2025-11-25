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
        $categories = ['会議', 'MTG', '業務', '期限', '有給', '重要'];
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
                'MTG' => ['プロジェクトMTG', '部門間MTG', '顧客との打ち合わせ', 'チームMTG', '個別面談'],
                '業務' => ['資料作成', 'データ分析', '企画書レビュー', 'コードレビュー', 'タスク消化'],
                '期限' => ['報告書提出期限', '企画案締め切り', '請求書発行期限', 'タスク完了期限', '最終チェック'],
                '有給' => ['有給休暇', '半日有給', '特別休暇', '振替休日'],
                '重要' => ['重要顧客訪問', '役員報告', '緊急対応', '新規事業説明会', '予算会議'],
            ];

            $descriptions = [
                '会議' => ['先月の進捗確認と来月の目標設定を行います。', '新しいプロジェクトの方向性を議論します。', '市場分析の結果を共有し、戦略を練ります。', '新製品のアイデア出しを行います。', 'プロジェクトの開始にあたり、目的と範囲を共有します。'],
                'MTG' => ['〇〇プロジェクトの現在の状況と課題について議論します。', '他部門との連携強化について話し合います。', '新サービスに関するお客様のご要望をヒアリングします。', 'チーム内の情報共有と連携を深めます。', '個人の目標達成に向けた進捗確認と課題解決を行います。'],
                '業務' => ['〇〇に関する報告書を作成します。', '販売データの傾向を分析し、次の施策を検討します。', '提案書の最終確認を行います。', '開発中の機能コードをチームで確認します。', '未完了のタスクを優先順位をつけて処理します。'],
                '期限' => ['〇〇に関する報告書を期日までに提出します。', '新企画の提案書を締め切りまでに完成させます。', '〇月分の請求書を発行し、経理部に提出します。', '〇〇タスクを今日中に完了させます。', 'すべての資料の最終チェックを行います。'],
                '有給' => ['心身のリフレッシュのため、終日お休みをいただきます。', '午後半休を取得し、私用を済ませます。', '慶弔休暇を利用します。', '休日出勤の代休を取得します。'],
                '重要' => ['最も重要な顧客である〇〇社を訪問し、商談を行います。', '経営層に対し、当部門の年間業績を報告します。', '予期せぬトラブルに対し、迅速に対応します。', '新規事業の概要と将来性について説明します。', '来年度の部門予算について協議します。'],
            ];

            $title = $titles[$category][array_rand($titles[$category])];
            $description = $descriptions[$category][array_rand($descriptions[$category])] . ' ' . $faker->realText(20);
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
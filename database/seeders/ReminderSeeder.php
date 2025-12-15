<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Reminder;

class ReminderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('email', 'a@a')->first();
        if (!$user) {
            $this->command->info('Test user (a@a) not found, skipping ReminderSeeder.');
            return;
        }

        $faker = \Faker\Factory::create('ja_JP');

        $titles = [
            '経費精算の提出', '年末調整書類の確認', '会議室予約（来週分）',
            'プロジェクト進捗報告書の作成', 'クライアントへの見積書送付',
            'システムメンテナンスの周知', '社内研修の受講',
            '備品発注', '月次レポートの作成', 'コードレビュー',
            '顧客満足度調査の分析', '新機能の要件定義',
            '不具合修正のデプロイ', 'セキュリティチェックシートの記入',
            'データバックアップの確認',
        ];

        $descriptions = [
            '今月分の経費精算をシステムから申請する。領収書の添付を忘れずに。',
            '人事部から送られてきた年末調整の書類を確認し、必要事項を記入して提出する。',
            '来週の定例会議のために、大会議室を10時から12時まで予約する。',
            'プロジェクトの現在の進捗状況をまとめ、マネージャーに報告する。',
            '先日の打ち合わせ内容に基づき、見積書を作成してクライアントにメールで送付する。',
            '来週予定しているシステムメンテナンスについて、全社メールで周知する。',
            '今月中にコンプライアンス研修の動画を視聴し、テストに合格する。',
            'コピー用紙とトナーが切れそうなので、アスクルで発注する。',
            '先月のアクセス解析データをもとに、月次レポートを作成する。',
            'プルリクエスト #123 のコードレビューを行い、コメントする。',
            '先月実施したアンケート結果を集計し、改善点を洗い出す。',
            '次期バージョンで追加する新機能について、仕様を詳細に詰める。',
            '報告された致命的なバグを修正し、本番環境にデプロイする。',
            '取引先から依頼されたセキュリティチェックシートに回答する。',
            '週次バックアップが正しく実行されているかログを確認する。',
        ];

        for ($i = 0; $i < 20; $i++) {
            $title = $faker->randomElement($titles);
            $description = $faker->randomElement($descriptions);
            
            // タイトルと説明文の組み合わせを少しランダムにする（完全一致でなくても良いが、自然に見える範囲で）
            // ここでは単純にランダムに選ぶが、より自然にするなら連想配列でペアにするのもあり。
            // 今回はバリエーション重視でランダム。

            $deadline = $faker->dateTimeBetween('now', '+1 month');

            Reminder::create([
                'user_id' => $user->id,
                'title' => $title,
                'description' => $description,
                'deadline_date' => $deadline->format('Y-m-d'),
                'deadline_time' => $deadline->format('H:i:s'),
                'completed' => $faker->boolean(20), // 20%の確率で完了済み
            ]);
        }
    }
}
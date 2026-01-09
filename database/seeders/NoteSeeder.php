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
        for ($i = 0; $i < 20; $i++) {
            $hasDeadline = rand(0, 1); // 50%の確率で期限あり
            $deadlineDate = null;
            $deadlineTime = null;
            
            if ($hasDeadline) {
                $deadlineDate = $faker->dateTimeBetween('now', '+3 months')->format('Y-m-d');
                $deadlineTime = sprintf('%02d:%02d:00', rand(9, 18), rand(0, 59));
            }

            $noteTitles = [
                'プロジェクトAlphaの進捗報告', 'マーケティング戦略会議の議事録', '新機能のアイデア',
                '来週のタスクリスト', '顧客からのフィードバックまとめ', '競合他社の動向分析',
                '採用面接の評価シート', '四半期売上レポート', 'チームビルディングの企画案',
                '開発ロードマップ案',
            ];
            $noteContents = [
                '現在の開発状況は80%完了。UIの改善とバグ修正が残っている。',
                '主要な決定事項：SNS広告の予算を20%増額。インフルエンサーマーケティングを新たに開始する。',
                'ユーザー認証機能のUXを改善するため、パスキー認証の導入を検討する。',
                '・A機能の設計レビュー\n・B機能の実装\n・単体テストの作成',
                '「UIが直感的で使いやすい」との高評価が多数。一方で、「読み込みが遅い」との指摘も。',
                'X社が新サービスを発表。当社のサービスと類似しており、価格設定で優位性を持つ。',
                '候補者Aは技術力は高いが、チーム文化との適合性に懸念あり。',
                '第3四半期の売上は目標比110%を達成。特にBtoB事業が好調だった。',
                'オフサイトでのワークショップと懇親会を計画。予算は30万円。',
                'Q1: 認証機能の強化, Q2: 新しいダッシュボードの導入, Q3: パフォーマンス改善',
            ];
            $title = $noteTitles[array_rand($noteTitles)];
            $content = $noteContents[array_rand($noteContents)];

            $note = SharedNote::create([
                'title' => $title,
                'content' => $content,
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

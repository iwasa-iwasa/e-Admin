<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionOption;

class SurveyResponseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $surveys = Survey::with(['questions.options'])->get();

        if ($users->isEmpty() || $surveys->isEmpty()) {
            $this->command->warn('Users or Surveys not found. Run UserSeeder and SurveySeeder first.');
            return;
        }

        foreach ($surveys as $survey) {
            // 回答を作成するユーザーを取得（まだこのアンケートに回答していないユーザー）
            $respondedUserIds = SurveyResponse::where('survey_id', $survey->survey_id)->pluck('respondent_id');
            $availableUsers = User::whereNotIn('id', $respondedUserIds)->get();

            if ($availableUsers->isEmpty()) {
                continue; // 回答できるユーザーがいない場合はスキップ
            }

            // 8問設問のアンケート（社員満足度調査）は全員参加、その他はランダム
            if ($survey->questions->count() >= 8) {
                $selectedUsers = $availableUsers; // 全員参加
            } else {
                $responseCount = min($availableUsers->count(), rand(1, 5));
                $selectedUsers = $availableUsers->shuffle()->take($responseCount);
            }

            foreach ($selectedUsers as $user) {
                // 各質問に対する回答を生成
                $answers = [];
                foreach ($survey->questions as $question) {
                    $answerData = $this->generateAnswer($question);
                    if ($answerData) {
                        $answers[$question->uuid] = $answerData;
                    }
                }

                // 回答を作成（JSON形式で保存）
                SurveyResponse::create([
                    'survey_id' => $survey->survey_id,
                    'respondent_id' => is_object($user) ? $user->id : $user['id'],
                    'answers' => json_encode($answers),
                    'status' => 'submitted',
                    'survey_version' => $survey->version,
                    'submitted_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
                ]);
            }
        }

        $this->command->info('Survey responses seeded successfully.');
    }

    /**
     * Generate answer data based on question type.
     *
     * @param  \App\Models\SurveyQuestion  $question
     * @return array|null
     */
    private function generateAnswer($question)
    {
        switch ($question->question_type) {
            case 'single_choice':
            case 'single':
            case 'radio':
                // 選択肢からランダムに1つ選択
                $options = $question->options;
                if ($options->isEmpty()) {
                    return null;
                }
                $selectedOption = $options->random();
                return [
                    'type' => 'single_choice',
                    'value' => $selectedOption->uuid,
                ];

            case 'multiple_choice':
            case 'multiple':
                // 選択肢から複数ランダムに選択（1-3個）
                $options = $question->options;
                if ($options->isEmpty()) {
                    return null;
                }
                $count = min(rand(1, 3), $options->count());
                
                $selectedOptions = $options->shuffle()->take($count);
                $optionUuids = $selectedOptions->map(fn($opt) => $opt->uuid)->all();
                
                return [
                    'type' => 'multiple_choice',
                    'value' => $optionUuids,
                ];

            case 'dropdown':
                // 選択肢からランダムに1つ選択
                $options = $question->options;
                if ($options->isEmpty()) {
                    return null;
                }
                $selectedOption = $options->random();
                return [
                    'type' => 'dropdown',
                    'value' => $selectedOption->uuid,
                ];

            case 'text':
                // ダミーテキスト
                $texts = [
                    'とても良かったです。',
                    '改善の余地があると思います。',
                    '特に問題ありませんでした。',
                    'もっと詳しい説明が欲しいです。',
                    '非常に満足しています。',
                    '良いと思います。',
                    '普通です。',
                ];
                return [
                    'type' => 'text',
                    'value' => $texts[array_rand($texts)],
                ];

            case 'textarea':
                // ダミー長文テキスト
                $texts = [
                    '楽しい忘年会を期待しています。去年も楽しかったです。',
                    'アレルギー対応のメニューがあると助かります。',
                    '早めに会場を教えていただけると嬉しいです。',
                    '特に要望はありません。',
                    '会場のアクセスが良い場所だと助かります。',
                    '予算に応じたメニューを希望します。',
                ];
                return [
                    'type' => 'textarea',
                    'value' => $texts[array_rand($texts)],
                ];

            case 'rating':
                // ランダムな評価（1-5）
                $rating = rand(1, 5);
                return [
                    'type' => 'rating',
                    'value' => $rating,
                ];

            case 'scale':
                // リッカートスケール：複数項目に対する評価
                $options = $question->options;
                if ($options->isEmpty()) {
                    // optionsがない場合は単一値
                    $scale = rand(1, 5);
                    return [
                        'type' => 'scale',
                        'value' => $scale,
                    ];
                }
                
                // 各項目に対してランダムな評価値を生成
                $scaleData = [];
                foreach ($options as $option) {
                    $scaleData[$option->uuid] = rand(1, 5);
                }
                
                return [
                    'type' => 'scale',
                    'value' => $scaleData,
                ];

            case 'date':
                // ランダムな日付
                $date = now()->addDays(rand(1, 60))->format('Y-m-d');
                return [
                    'type' => 'date',
                    'value' => $date,
                ];

            default:
                $this->command->warn("Unknown question type: {$question->question_type}");
                return [
                    'type' => 'text',
                    'value' => 'サンプル回答',
                ];
        }
    }
}

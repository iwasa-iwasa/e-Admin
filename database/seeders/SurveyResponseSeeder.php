<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\SurveyAnswer;
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
                // 回答を作成
                $response = SurveyResponse::create([
                    'survey_id' => $survey->survey_id,
                    'respondent_id' => is_object($user) ? $user->id : $user['id'],
                    'submitted_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
                ]);

                // 各質問に対する回答を作成
                foreach ($survey->questions as $question) {
                    $answerData = $this->generateAnswer($question);

                    if ($answerData) {
                        // 複数選択の場合は複数のレコードを作成
                        if ($question->question_type === 'multiple_choice' && isset($answerData['option_ids'])) {
                            foreach ($answerData['option_ids'] as $optionId) {
                                SurveyAnswer::create([
                                    'response_id' => $response->response_id,
                                    'question_id' => $question->question_id,
                                    'answer_text' => null,
                                    'selected_option_id' => $optionId,
                                ]);
                            }
                        } else {
                            SurveyAnswer::create([
                                'response_id' => $response->response_id,
                                'question_id' => $question->question_id,
                                'answer_text' => $answerData['text'] ?? null,
                                'selected_option_id' => $answerData['option_id'] ?? null,
                            ]);
                        }
                    }
                }
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
        // デバッグ用ログ
        $this->command->info("Question: {$question->question_text}");
        $this->command->info("Type: {$question->question_type}");
        $this->command->info("Options count: {$question->options->count()}");
        
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
                    'text' => $selectedOption->option_text,
                    'option_id' => $selectedOption->option_id,
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
                $texts = $selectedOptions->map(fn($opt) => $opt->option_text)->all();
                $optionIds = $selectedOptions->map(fn($opt) => $opt->option_id)->all();
                
                return [
                    'text' => implode(', ', $texts),
                    'option_ids' => $optionIds, // 複数のoption_idを配列で返す
                ];

            case 'dropdown':
                // 選択肢からランダムに1つ選択
                $options = $question->options;
                if ($options->isEmpty()) {
                    return null;
                }
                $selectedOption = $options->random();
                return [
                    'text' => $selectedOption->option_text,
                    'option_id' => $selectedOption->option_id,
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
                    'text' => $texts[array_rand($texts)],
                    'option_id' => null,
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
                    'text' => $texts[array_rand($texts)],
                    'option_id' => null,
                ];

            case 'rating':
                // ランダムな評価（1-5）
                $rating = rand(1, 5);
                return [
                    'text' => (string) $rating,
                    'option_id' => null,
                ];

            case 'scale':
                // リッカートスケール：複数項目に対する評価
                // scaleタイプの場合、optionsが評価項目を表す
                $options = $question->options;
                if ($options->isEmpty()) {
                    // optionsがない場合は単一値
                    $scale = rand(1, 5);
                    return [
                        'text' => (string) $scale,
                        'option_id' => null,
                    ];
                }
                
                // 各項目に対してランダムな評価値を生成
                $scaleData = [];
                foreach ($options as $option) {
                    $scaleData[$option->option_text] = rand(1, 5);
                }
                
                return [
                    'text' => json_encode($scaleData, JSON_UNESCAPED_UNICODE),
                    'option_id' => null,
                ];

            case 'date':
                // ランダムな日付（秒なし）
                $date = now()->subDays(rand(0, 30))->format('Y-m-d H:i:00');
                return [
                    'text' => $date,
                    'option_id' => null,
                ];

            default:
                $this->command->warn("Unknown question type: {$question->question_type}");
                return [
                    'text' => 'サンプル回答',
                    'option_id' => null,
                ];
        }
    }
}

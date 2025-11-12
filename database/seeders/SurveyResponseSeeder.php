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
            // 各アンケートに5〜15件のランダムな回答を生成
            $responseCount = rand(5, 15);

            for ($i = 0; $i < $responseCount; $i++) {
                // ランダムなユーザーを選択
                $user = $users->random();

                // 回答を作成
                $response = SurveyResponse::create([
                    'survey_id' => $survey->survey_id,
                    'respondent_id' => $user->id,
                    'submitted_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
                ]);

                // 各質問に対する回答を作成
                foreach ($survey->questions as $question) {
                    $answerData = $this->generateAnswer($question);

                    if ($answerData) {
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
                // 選択肢から複数ランダムに選択（1-3個）
                $options = $question->options;
                if ($options->isEmpty()) {
                    return null;
                }
                $count = min(rand(1, 3), $options->count());
                $selectedOptions = $options->random($count);
                // Collectionのrandom()は単一オブジェクトを返す場合があるため配列に変換
                if (!is_array($selectedOptions) && !($selectedOptions instanceof \Illuminate\Support\Collection)) {
                    $selectedOptions = [$selectedOptions];
                }
                $selectedOptionsArray = is_array($selectedOptions) ? $selectedOptions : $selectedOptions->all();
                $texts = array_map(fn($opt) => $opt->option_text, $selectedOptionsArray);
                return [
                    'text' => implode(', ', $texts),
                    'option_id' => $selectedOptionsArray[0]->option_id, // 最初の選択肢のIDを保存
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
                // ランダムなスケール値（1-5）
                $scale = rand(1, 5);
                return [
                    'text' => (string) $scale,
                    'option_id' => null,
                ];

            case 'date':
                // ランダムな日付
                $date = now()->subDays(rand(0, 30))->format('Y-m-d H:i:s');
                return [
                    'text' => $date,
                    'option_id' => null,
                ];

            default:
                return [
                    'text' => 'サンプル回答',
                    'option_id' => null,
                ];
        }
    }
}

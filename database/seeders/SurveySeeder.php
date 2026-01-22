<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionOption;

class SurveySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::take(2)->get();

        if ($users->count() < 2) {
            $this->command->error('At least 2 users are required to run SurveySeeder.');
            return;
        }
        $user1 = $users[0];
        $user2 = $users[1];

        $surveys = [
            [
                'survey' => [
                    'title' => '2025年度 社員満足度調査',
                    'description' => '職場環境や業務内容について、率直なご意見をお聞かせください。',
                    'deadline_date' => '2025-10-25',
                    'deadline_time' => '23:59:59',
                    'created_by' => $user1->id,
                    'is_active' => true,
                ],
                'questions' => [
                    [
                        'text' => '現在の勤務形態を選択してください',
                        'type' => 'single',
                        'options' => ['フルタイム勤務', 'リモートワーク', 'ハイブリッド勤務', 'フレックスタイム'],
                    ],
                    [
                        'text' => '利用している福利厚生制度をすべて選択してください',
                        'type' => 'multiple',
                        'options' => ['健康診断', '社員食堂', 'スポーツジム', '住宅手当', '資格取得支援'],
                    ],
                    [
                        'text' => '会社の経営方針やビジョンに共感できますか？',
                        'type' => 'rating',
                        'options' => [],
                    ],
                    [
                        'text' => '以下の項目について評価してください',
                        'type' => 'scale',
                        'options' => [],
                    ],
                    [
                        'text' => '最も重要だと思うスキル開発分野を選択してください',
                        'type' => 'dropdown',
                        'options' => ['リーダーシップ', '技術スキル', 'コミュニケーション', 'プロジェクト管理', '語学'],
                    ],
                    [
                        'text' => '今年度の目標を簡潔に記入してください',
                        'type' => 'text',
                        'options' => [],
                    ],
                    [
                        'text' => '会社や部署への要望・提案があれば自由に記述してください',
                        'type' => 'textarea',
                        'options' => [],
                    ],
                    [
                        'text' => '次回の1on1ミーティングの希望日時を入力してください',
                        'type' => 'date',
                        'options' => [],
                    ],
                ]
            ],
            [
                'survey' => [
                    'title' => '2025年度 忘年会の候補日アンケート',
                    'description' => '12月の忘年会について、参加可能な日程を教えてください。',
                    'deadline_date' => '2025-10-20',
                    'deadline_time' => '23:59:59',
                    'created_by' => $user1->id,
                    'is_active' => true,
                ],
                'questions' => [
                    [
                        'text' => '12/20(水) 19:00-21:00',
                        'type' => 'single',
                        'options' => ['参加可能', '未定', '不参加'],
                    ],
                    [
                        'text' => '12/22(金) 19:00-21:00',
                        'type' => 'single',
                        'options' => ['参加可能', '未定', '不参加'],
                    ],
                ]
            ],
            [
                'survey' => [
                    'title' => 'オフィス備品の購入希望アンケート',
                    'description' => '来月の備品発注に向けて、必要な物品や優先順位をお聞かせください。',
                    'deadline_date' => '2025-10-18',
                    'deadline_time' => '23:59:59',
                    'created_by' => $user2->id,
                    'is_active' => true,
                ],
                'questions' => [
                    [
                        'text' => '希望する備品を自由にご記入ください。',
                        'type' => 'text',
                        'options' => [],
                    ],
                    [
                        'text' => '特に優先してほしいものは何ですか？',
                        'type' => 'textarea',
                        'options' => [],
                    ],
                ]
            ],
        ];

        foreach ($surveys as $surveyData) {
            $survey = Survey::create($surveyData['survey']);

            foreach ($surveyData['questions'] as $qIndex => $questionData) {
                // フロントエンド形式からDB形式に変換
                $questionType = $this->mapQuestionType($questionData['type']);
                
                $question = $survey->questions()->create([
                    'question_text' => $questionData['text'],
                    'question_type' => $questionType,
                    'is_required' => true,
                    'display_order' => $qIndex + 1,
                ]);

                if (!empty($questionData['options'])) {
                    foreach ($questionData['options'] as $oIndex => $optionText) {
                        $question->options()->create([
                            'option_text' => $optionText,
                            'display_order' => $oIndex + 1,
                        ]);
                    }
                }
            }

            // Create responses
            // User 2 answers Survey 1 (User 1's survey)
            if ($survey->created_by === $user1->id) {
                $respondent = $user2;
            } else {
                $respondent = $user1;
            }

            $answers = [];
            foreach ($survey->questions as $question) {
                $val = null;
                switch ($question->question_type) {
                    case 'single_choice':
                    case 'dropdown':
                        $opt = $question->options->random();
                        $val = $opt ? $opt->option_id : null;
                        break;
                    case 'multiple_choice':
                        // Pick 1-2 random options
                        $opts = $question->options->random(min(2, $question->options->count()));
                        $val = $opts->pluck('option_id')->toArray();
                        break;
                    case 'rating':
                    case 'scale':
                        $val = rand(1, 5);
                        break;
                    case 'date':
                        $val = '2025-12-25';
                        break;
                    default:
                        $val = 'Test Answer';
                        break;
                }
                if ($val) {
                    $answers[$question->question_id] = $val;
                }
            }

            \App\Models\SurveyResponse::create([
                'survey_id' => $survey->survey_id,
                'respondent_id' => $respondent->id,
                'answers' => $answers,
                'status' => 'submitted',
                'survey_version' => 1,
                'submitted_at' => now(),
            ]);
        }
    }
    
    private function mapQuestionType(string $frontendType): string
    {
        $mapping = [
            'single' => 'single_choice',
            'multiple' => 'multiple_choice',
            'text' => 'text',
            'textarea' => 'textarea',
            'rating' => 'rating',
            'scale' => 'scale',
            'dropdown' => 'dropdown',
            'date' => 'date',
        ];

        return $mapping[$frontendType] ?? $frontendType;
    }
}
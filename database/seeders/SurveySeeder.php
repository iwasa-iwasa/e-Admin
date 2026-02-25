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
        // 一般ユーザーのみを取得（管理者アカウントを除外）
        $users = User::whereNotIn('name', ['全社管理者', '総務部管理者', '営業部管理者', '開発部管理者'])
            ->where('is_active', true)
            ->get();

        if ($users->count() < 2) {
            $this->command->error('At least 2 active users are required to run SurveySeeder.');
            return;
        }

        $creator = $users->first();
        $allUserIds = $users->pluck('id')->toArray();

        $surveys = [
            // 2問のシンプルなアンケート
            [
                'survey' => [
                    'title' => '2025年度 忘年会の候補日アンケート',
                    'description' => '12月の忘年会について、参加可能な日程を教えてください。',
                    'deadline_date' => '2025-12-20',
                    'deadline_time' => '23:59:59',
                    'created_by' => $creator->id,
                    'is_active' => true,
                    'categories' => ['イベント', '全社'],
                ],
                'questions' => [
                    [
                        'text' => '12/20(金) 19:00-21:00',
                        'type' => 'single',
                        'options' => ['参加可能', '未定', '不参加'],
                    ],
                    [
                        'text' => '12/22(日) 18:00-20:00',
                        'type' => 'single',
                        'options' => ['参加可能', '未定', '不参加'],
                    ],
                ],
                'respondents' => $allUserIds,
            ],
            // 8問の全質問タイプを含むアンケート
            [
                'survey' => [
                    'title' => '2025年度 社員満足度調査',
                    'description' => '職場環境や業務内容について、率直なご意見をお聞かせください。',
                    'deadline_date' => '2025-12-25',
                    'deadline_time' => '23:59:59',
                    'created_by' => $creator->id,
                    'is_active' => true,
                    'categories' => ['人事', '全社', '満足度調査'],
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
                        'text' => '職場環境の満足度を評価してください',
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
                        'text' => '次回の1on1ミーティングの希望日を入力してください',
                        'type' => 'date',
                        'options' => [],
                    ],
                ],
                'respondents' => $allUserIds,
            ],
        ];

        foreach ($surveys as $surveyData) {
            $survey = Survey::create($surveyData['survey']);

            // 回答者を登録
            foreach ($surveyData['respondents'] as $userId) {
                \App\Models\SurveyRespondent::create([
                    'survey_id' => $survey->survey_id,
                    'user_id' => $userId,
                ]);
            }

            // 質問を作成
            foreach ($surveyData['questions'] as $qIndex => $questionData) {
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
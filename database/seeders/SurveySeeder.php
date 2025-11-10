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
        $tanaka = User::where('name', '田中')->first();
        $sato = User::where('name', '佐藤')->first();

        if (!$tanaka || !$sato) {
            $this->command->error('Required users not found. Run UserSeeder first.');
            return;
        }

        $surveys = [
            [
                'survey' => [
                    'title' => '2025年度 忘年会の候補日アンケート',
                    'description' => '12月の忘年会について、参加可能な日程を教えてください。',
                    'deadline' => '2025-10-20 23:59:59',
                    'created_by' => $tanaka->id,
                    'is_active' => true,
                ],
                'questions' => [
                    [
                        'text' => '12/20(水) 19:00-21:00',
                        'type' => 'radio',
                        'options' => ['参加可能', '未定', '不参加'],
                    ],
                    [
                        'text' => '12/22(金) 19:00-21:00',
                        'type' => 'radio',
                        'options' => ['参加可能', '未定', '不参加'],
                    ],
                ]
            ],
            [
                'survey' => [
                    'title' => 'オフィス備品の購入希望アンケート',
                    'description' => '来月の備品発注に向けて、必要な物品や優先順位をお聞かせください。',
                    'deadline' => '2025-10-18 23:59:59',
                    'created_by' => $sato->id,
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
                $question = $survey->questions()->create([
                    'question_text' => $questionData['text'],
                    'question_type' => $questionData['type'],
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
}
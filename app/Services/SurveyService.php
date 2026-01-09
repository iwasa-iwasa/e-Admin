<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionOption;
use App\Models\SurveyRespondent;
use App\Models\SurveyResponse;
use App\Models\TrashItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SurveyService
{
    /**
     * Create a new survey.
     */
    public function createSurvey(array $data)
    {
        return DB::transaction(function () use ($data) {
            // アンケート本体を保存
            $deadline = $data['deadline'];
            $deadlineDate = null;
            $deadlineTime = null;
            
            if ($deadline) {
                $dt = \Carbon\Carbon::parse($deadline);
                $deadlineDate = $dt->format('Y-m-d');
                $deadlineTime = $dt->format('H:i:s');
            }
            
            $survey = Survey::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'created_by' => Auth::id(),
                'deadline_date' => $deadlineDate,
                'deadline_time' => $deadlineTime,
                'is_active' => !($data['isDraft'] ?? false),
            ]);

            // 質問を保存
            $this->saveQuestions($survey, $data['questions']);

            // 回答者を保存
            if (!empty($data['respondents'])) {
                foreach ($data['respondents'] as $userId) {
                    SurveyRespondent::create([
                        'survey_id' => $survey->survey_id,
                        'user_id' => $userId,
                    ]);
                }
            }

            return $survey;
        });
    }

    /**
     * Update an existing survey.
     */
    public function updateSurvey(Survey $survey, array $data)
    {
        return DB::transaction(function () use ($survey, $data) {
            $deadline = $data['deadline'];
            $deadlineDate = null;
            $deadlineTime = null;
            
            if ($deadline) {
                $dt = \Carbon\Carbon::parse($deadline);
                $deadlineDate = $dt->format('Y-m-d');
                $deadlineTime = $dt->format('H:i:s');
            }
            
            // 既存回答の有無を確認
            $hasResponses = $survey->responses()->exists();
            $questionsChanged = $this->questionsChanged($survey, $data['questions']);
            $onlyDeadlineChanged = $this->onlyDeadlineChanged($survey, $data);

            $survey->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'deadline_date' => $deadlineDate,
                'deadline_time' => $deadlineTime,
            ]);

            // 期限のみの変更の場合は質問と回答を保持
            if (!$onlyDeadlineChanged) {
                // 既存回答があり質問が変更された場合は回答を削除
                if ($hasResponses && $questionsChanged) {
                    $survey->responses()->each(function ($response) {
                        $response->answers()->delete();
                    });
                    $survey->responses()->delete();
                }
                
                // 質問を再作成
                $survey->questions()->each(fn($question) => $question->options()->delete());
                $survey->questions()->delete();

                $this->saveQuestions($survey, $data['questions']);
                
                // Note: Respondents update logic was separated in controller, 
                // but if needed we can add logic here to update respondents too.
                // Assuming respondents are not updated in this request based on existing controller.
            }
            
            return $survey;
        });
    }

    /**
     * Delete a survey (move to trash).
     */
    public function deleteSurvey(Survey $survey)
    {
        if ($survey->is_deleted) {
            throw new \Exception('This survey is already deleted.');
        }

        return DB::transaction(function () use ($survey) {
            $survey->update(['is_deleted' => true, 'deleted_at' => now()]);

            $trashItem = TrashItem::create([
                'user_id' => Auth::id(),
                'item_type' => 'survey',
                'is_shared' => true,
                'item_id' => $survey->survey_id,
                'original_title' => $survey->title,
                'deleted_at' => now(),
                'permanent_delete_at' => now()->addMonth(),
            ]);
            
            return $trashItem;
        });
    }

    /**
     * Save survey answer.
     */
    public function saveAnswer(Survey $survey, array $answers, int $userId)
    {
        return DB::transaction(function () use ($survey, $answers, $userId) {
            // 既存の回答を確認
            $existingResponse = $survey->responses()
                ->where('respondent_id', $userId)
                ->first();
            
            if ($existingResponse) {
                // 既存の回答を削除
                $existingResponse->answers()->delete();
                $response = $existingResponse;
                $response->update(['submitted_at' => now()]);
            } else {
                // 新規回答を作成
                $response = SurveyResponse::create([
                    'survey_id' => $survey->survey_id,
                    'respondent_id' => $userId,
                    'submitted_at' => now(),
                ]);
            }
            
            foreach ($answers as $questionId => $answerData) {
                if ($answerData !== null && $answerData !== '') {
                    $question = SurveyQuestion::find($questionId);
                    
                    if (!$question) continue;

                    // multiple_choiceの場合、配列をカンマ区切りのテキストに変換
                    if ($question->question_type === 'multiple_choice' && is_array($answerData)) {
                        $optionTexts = [];
                        foreach ($answerData as $optionId) {
                            $option = SurveyQuestionOption::find($optionId);
                            if ($option) {
                                $optionTexts[] = $option->option_text;
                            }
                        }
                        
                        \App\Models\SurveyAnswer::create([
                            'response_id' => $response->response_id,
                            'question_id' => $questionId,
                            'answer_text' => implode(', ', $optionTexts),
                            'selected_option_id' => null,
                        ]);
                    } else {
                        $selectedOptionId = null;
                        $answerText = $answerData;
                        
                        // single_choiceとdropdownの場合
                        if (in_array($question->question_type, ['single_choice', 'dropdown']) && is_numeric($answerData)) {
                            $selectedOptionId = $answerData;
                            $option = SurveyQuestionOption::find($answerData);
                            $answerText = $option ? $option->option_text : $answerData;
                        } elseif (is_array($answerData)) {
                            $answerText = json_encode($answerData);
                        }
                        
                        \App\Models\SurveyAnswer::create([
                            'response_id' => $response->response_id,
                            'question_id' => $questionId,
                            'answer_text' => $answerText,
                            'selected_option_id' => $selectedOptionId,
                        ]);
                    }
                }
            }
            
            return $response;
        });
    }

    /**
     * Calculate statistics for survey results.
     */
    public function calculateStatistics($survey, $responses)
    {
        $stats = [];

        foreach ($survey->questions()->orderBy('display_order')->get() as $question) {
            $answers = $responses->flatMap->answers
                ->where('question_id', $question->question_id);

            $stats[$question->question_id] = [
                'question' => $question->question_text,
                'question_type' => $question->question_type,
                'total_responses' => $answers->count(),
                'answers' => $answers->pluck('answer_text')->toArray(),
            ];

            // 選択式の場合は集計
            if (in_array($question->question_type, ['single_choice', 'multiple_choice', 'dropdown'])) {
                $distribution = [];
                foreach ($answers as $answer) {
                    $answerText = $answer->answer_text;
                    if ($answer->selected_option_id) {
                        $option = $question->options->firstWhere('option_id', $answer->selected_option_id);
                        $answerText = $option ? $option->option_text : $answer->answer_text;
                    }
                    if (!empty($answerText)) {
                        $distribution[$answerText] = ($distribution[$answerText] ?? 0) + 1;
                    }
                }
                $stats[$question->question_id]['distribution'] = $distribution;
            }
        }

        return $stats;
    }

    /**
     * Helper to save questions and options.
     */
    private function saveQuestions(Survey $survey, array $questionsData)
    {
        foreach ($questionsData as $index => $questionData) {
            $questionType = $this->mapQuestionType($questionData['type']);

            $question = SurveyQuestion::create([
                'survey_id' => $survey->survey_id,
                'question_text' => $questionData['question'],
                'question_type' => $questionType,
                'is_required' => $questionData['required'] ?? false,
                'display_order' => $index + 1,
                'scale_min' => $questionData['scaleMin'] ?? null,
                'scale_max' => $questionData['scaleMax'] ?? null,
                'scale_min_label' => $questionData['scaleMinLabel'] ?? null,
                'scale_max_label' => $questionData['scaleMaxLabel'] ?? null,
            ]);

            if (in_array($questionData['type'], ['single', 'multiple', 'dropdown']) && !empty($questionData['options'])) {
                foreach ($questionData['options'] as $optionIndex => $optionText) {
                    $trimmedOption = trim($optionText);
                    if (!empty($trimmedOption)) {
                        SurveyQuestionOption::create([
                            'question_id' => $question->question_id,
                            'option_text' => $trimmedOption,
                            'display_order' => $optionIndex + 1,
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Map frontend question type to database question type.
     */
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
    
    /**
     * Restore a deleted survey.
     */
    public function restoreSurvey($surveyId)
    {
        return DB::transaction(function () use ($surveyId) {
            $survey = Survey::findOrFail($surveyId);
            $survey->update([
                'is_deleted' => false,
                'deleted_at' => null,
            ]);
            
            TrashItem::where('item_type', 'survey')
                ->where('item_id', $surveyId)
                ->where('user_id', Auth::id())
                ->delete();
                
            return $survey;
        });
    }

    /**
     * Check if questions have changed.
     */
    public function questionsChanged(Survey $survey, array $newQuestions): bool
    {
        $currentQuestions = $survey->questions()->with('options')->orderBy('display_order')->get();
        
        if ($currentQuestions->count() !== count($newQuestions)) {
            return true;
        }
        
        foreach ($currentQuestions as $index => $currentQuestion) {
            $newQuestion = $newQuestions[$index] ?? null;
            
            if (!$newQuestion) {
                return true;
            }
            
            // 質問テキストの比較
            if ($currentQuestion->question_text !== $newQuestion['question']) {
                return true;
            }
            
            // 質問タイプの比較
            $newType = $this->mapQuestionType($newQuestion['type']);
            if ($currentQuestion->question_type !== $newType) {
                return true;
            }
            
            // 必須フラグの比較
            if ($currentQuestion->is_required !== ($newQuestion['required'] ?? false)) {
                return true;
            }
            
            // 選択肢の比較
            if (in_array($newQuestion['type'], ['single', 'multiple', 'dropdown'])) {
                $currentOptions = $currentQuestion->options->pluck('option_text')->toArray();
                $newOptions = array_filter($newQuestion['options'] ?? [], fn($opt) => !empty(trim($opt)));
                
                if (count($currentOptions) !== count($newOptions)) {
                    return true;
                }
                
                foreach ($currentOptions as $i => $currentOption) {
                    if (!isset($newOptions[$i]) || $currentOption !== trim($newOptions[$i])) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Check if only deadline has changed.
     */
    public function onlyDeadlineChanged(Survey $survey, array $data): bool
    {
        // タイトル、説明、質問が変更されていないかチェック
        $titleChanged = $survey->title !== $data['title'];
        $descriptionChanged = $survey->description !== ($data['description'] ?? null);
        $questionsChanged = $this->questionsChanged($survey, $data['questions']);
        
        return !$titleChanged && !$descriptionChanged && !$questionsChanged;
    }
}

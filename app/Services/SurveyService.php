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
                'categories' => !empty($data['categories']) ? $data['categories'] : null,
                'created_by' => Auth::id(),
                'deadline_date' => $deadlineDate,
                'deadline_time' => $deadlineTime,
                'is_active' => ! ($data['isDraft'] ?? false),
            ]);

            // 質問を保存
            $this->saveQuestions($survey, $data['questions']);

            // 回答者を保存
            if (! empty($data['respondents'])) {
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
                'categories' => !empty($data['categories']) ? $data['categories'] : null,
                'deadline_date' => $deadlineDate,
                'deadline_time' => $deadlineTime,
            ]);

            // 質問が変更された場合、バージョンをインクリメント
            if ($questionsChanged && ! $onlyDeadlineChanged) {
                $survey->increment('version');
            }

            // 質問の更新（Reconciliation）
            if (! $onlyDeadlineChanged) {
                $this->reconcileQuestions($survey, $data['questions']);
            }

            // 回答者の更新（完全同期）
            if (isset($data['respondents'])) {
                $existingRespondents = $survey->respondents()->pluck('user_id')->toArray();
                $newRespondents = $data['respondents'];
                
                // 削除する回答者
                $toRemove = array_diff($existingRespondents, $newRespondents);
                if (!empty($toRemove)) {
                    $survey->respondents()->whereIn('user_id', $toRemove)->delete();
                }
                
                // 追加する回答者
                $toAdd = array_diff($newRespondents, $existingRespondents);
                foreach ($toAdd as $userId) {
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
    public function saveAnswer(Survey $survey, array $answers, int $userId, string $status = 'submitted')
    {
        // 送信時のみ必須項目をバリデーション
        if ($status === 'submitted') {
            $this->validateRequiredAnswers($survey, $answers);
        }
        
        return DB::transaction(function () use ($survey, $answers, $userId, $status) {
            // 回答データを整形 (question_id => value)
            // 必要であれば質問テキストなどのメタデータもここで付与できるが、
            // シンプルにIDと値を保存し、参照時に解決する方針とする。
            // ただし、snapshot的に質問内容を残す場合はここでloadして構造化する。

            // JSONとしての構造: { question_id: value, ... }
            // フロントエンドからくる $answers は { question_id: value } の形式を想定

            $response = SurveyResponse::updateOrCreate(
                [
                    'survey_id' => $survey->survey_id,
                    'respondent_id' => $userId,
                ],
                [
                    'answers' => $answers,
                    'status' => $status,
                    'survey_version' => $survey->version,
                    'submitted_at' => ($status === 'submitted') ? now() : null,
                ]
            );

            return $response;
        });
    }

    /**
     * Validate required answers.
     */
    private function validateRequiredAnswers(Survey $survey, array $answers)
    {
        $requiredQuestions = $survey->questions()->where('is_required', true)->get();
        
        foreach ($requiredQuestions as $question) {
            $answer = $answers[$question->question_id] ?? null;
            
            // 回答が空かチェック
            if ($question->question_type === 'multiple_choice') {
                if (empty($answer) || (is_array($answer) && count($answer) === 0)) {
                    throw new \Exception("必須項目「{$question->question_text}」に回答してください。");
                }
            } elseif (in_array($question->question_type, ['single_choice', 'dropdown'])) {
                if ($answer === null || $answer === '') {
                    throw new \Exception("必須項目「{$question->question_text}」に回答してください。");
                }
            } elseif (in_array($question->question_type, ['rating', 'scale'])) {
                if ($answer === null) {
                    throw new \Exception("必須項目「{$question->question_text}」に回答してください。");
                }
            } else {
                // text, textarea, date など
                if ($answer === null || (is_string($answer) && trim($answer) === '')) {
                    throw new \Exception("必須項目「{$question->question_text}」に回答してください。");
                }
            }
        }
    }

    /**
     * Calculate statistics for survey results.
     */
    public function calculateStatistics($survey, $responses)
    {
        $stats = [];

        foreach ($survey->questions()->orderBy('display_order')->get() as $question) {
            $questionId = $question->question_id;

            // JSON回答から該当する質問の回答を抽出
            $answersValues = $responses->map(function ($response) use ($questionId) {
                $answers = $response->answers; // Array casted

                return $answers[$questionId] ?? null;
            })->filter(fn ($val) => ! is_null($val) && $val !== '');

            $stats[$questionId] = [
                'question' => $question->question_text,
                'question_type' => $question->question_type,
                'total_responses' => $answersValues->count(),
                'answers' => $answersValues->values()->toArray(),
            ];

            // 選択式の場合は分布を集計
            if (in_array($question->question_type, ['single_choice', 'multiple_choice', 'dropdown'])) {
                $distribution = [];
                foreach ($answersValues as $val) {
                    // multiple_choiceは配列またはカンマ区切りで来る可能性
                    // JSON保存時に array ([option_id, option_id]) としている前提

                    $selectedOptionIds = is_array($val) ? $val : [$val];

                    foreach ($selectedOptionIds as $optionId) {
                        // Optionテキストの解決
                        // $question->options は load済み前提
                        $option = $question->options->firstWhere('option_id', $optionId);
                        $label = $option ? $option->option_text : (string) $optionId;

                        $distribution[$label] = ($distribution[$label] ?? 0) + 1;
                    }
                }
                $stats[$questionId]['distribution'] = $distribution;
            } elseif (in_array($question->question_type, ['rating', 'scale'])) {
                // Rating/Scale distribution
                $max = $question->scale_max ?? 5;
                $distribution = [];

                // Initialize all possible values with 0
                for ($i = 1; $i <= $max; $i++) {
                    $distribution[(string) $i] = 0;
                }

                foreach ($answersValues as $val) {
                    $numVal = (int) $val;
                    if ($numVal >= 1 && $numVal <= $max) {
                        $key = (string) $numVal;
                        $distribution[$key]++;
                    }
                }

                $stats[$questionId]['distribution'] = $distribution;
            }
        }

        return $stats;
    }

    /**
     * Smart update of questions (Create/Update/Delete).
     */
    private function reconcileQuestions(Survey $survey, array $questionsData)
    {
        $existingQuestions = $survey->questions()->get()->keyBy('question_id');
        $processedIds = [];

        foreach ($questionsData as $index => $qData) {
            $qId = $qData['question_id'] ?? null;
            $questionType = $this->mapQuestionType($qData['type']);

            $attributes = [
                'question_text' => $qData['question'],
                'question_type' => $questionType,
                'is_required' => $qData['required'] ?? false,
                'display_order' => $index + 1,
                'scale_min' => $qData['scaleMin'] ?? null,
                'scale_max' => $qData['scaleMax'] ?? null,
                'scale_min_label' => $qData['scaleMinLabel'] ?? null,
                'scale_max_label' => $qData['scaleMaxLabel'] ?? null,
            ];

            if ($qId && $existingQuestions->has($qId)) {
                // Update
                $question = $existingQuestions->get($qId);
                $question->update($attributes);
                $processedIds[] = $qId;
            } else {
                // Create
                $question = $survey->questions()->create($attributes);
            }

            // Options Reconciliation
            if (in_array($qData['type'], ['single', 'multiple', 'dropdown']) && ! empty($qData['options'])) {
                // 既存オプションを取得
                $existingQuestionsCollection = $question->options()->get()->keyBy('option_id');
                $processedOptionIds = [];

                foreach ($qData['options'] as $oIndex => $optionData) {
                    $optionText = is_array($optionData) ? ($optionData['text'] ?? $optionData['option_text'] ?? '') : $optionData;
                    $optionId = is_array($optionData) ? ($optionData['option_id'] ?? null) : null;

                    $trimmed = trim($optionText);
                    if (! empty($trimmed)) {
                        if ($optionId && $existingQuestionsCollection->has($optionId)) {
                            // Update existing option
                            $existingQuestionsCollection->get($optionId)->update([
                                'option_text' => $trimmed,
                                'display_order' => $oIndex + 1,
                            ]);
                            $processedOptionIds[] = $optionId;
                        } else {
                            // Create new option
                            $newOption = $question->options()->create([
                                'option_text' => $trimmed,
                                'display_order' => $oIndex + 1,
                            ]);
                            // If we want to track this new ID immediately, we can, but not strictly required here
                        }
                    }
                }

                // Delete removed options
                // Note: Only delete options that were NOT in the processed list.
                // If the frontend sends string arrays (legacy or new creation), $processedOptionIds will be empty,
                // causing ALL existing options to be deleted.
                // To support mixed mode (strings = recreate, objects = update), we check execution mode.

                // If $qData['options'] contains objects with IDs, we assume we are in "Update Mode".
                // If it contains only strings, we are in "Recreate Mode".
                $hasObjects = ! empty($qData['options']) && is_array($qData['options'][0]);

                if ($hasObjects) {
                    $idsToDelete = $existingQuestionsCollection->keys()->diff($processedOptionIds);
                    SurveyQuestionOption::destroy($idsToDelete);
                } else {
                    // Fallback to old behavior: if we received strings, we can't map to IDs,
                    // so we must delete all previous options to avoid duplicates/inconsistency.
                    // Ideally frontend should ALWAYS send objects for edit.

                    // However, we just processed them above.
                    // If we processed strings above, we created NEW options.
                    // So we should delete OLD options.
                    // BUT, if we do this, we lose the IDs.

                    // For the bug fix, we rely on Frontend sending IDs.
                    // If Frontend sends strings (e.g. from previously created draft?), we lose IDs.
                    // But for "Edit Survey", we will ensure Frontend sends objects.

                    if (! $hasObjects) {
                        // We created new options above because $optionId was null.
                        // So we should remove all OLD options.
                        // But we should confirm we don't delete the ones we JUST created?
                        // The $existingQuestionsCollection only contains ID-based previous options.
                        // So it is safe to delete all of them if we are replacing them.
                        $question->options()->whereIn('option_id', $existingQuestionsCollection->keys())->delete();
                    }
                }
            }
        }

        // Delete removed questions
        $idsToDelete = $existingQuestions->keys()->diff($processedIds);
        SurveyQuestion::destroy($idsToDelete);
    }

    /**
     * Helper to save questions and options.
     */
    private function saveQuestions(Survey $survey, array $questionsData)
    {
        \Log::info('saveQuestions called', ['questionsData' => $questionsData]);
        
        foreach ($questionsData as $index => $questionData) {
            \Log::info('Processing question', [
                'index' => $index,
                'questionData' => $questionData,
                'required' => $questionData['required'] ?? 'NOT SET'
            ]);
            
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
            
            \Log::info('Question created', ['question_id' => $question->question_id, 'is_required' => $question->is_required]);

            if (in_array($questionData['type'], ['single', 'multiple', 'dropdown']) && ! empty($questionData['options'])) {
                foreach ($questionData['options'] as $optionIndex => $optionData) {
                    $optionText = is_array($optionData) ? ($optionData['text'] ?? $optionData['option_text'] ?? '') : $optionData;
                    $trimmedOption = trim($optionText);
                    if (! empty($trimmedOption)) {
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
     * Check if there are destructive changes (deletions or type changes).
     */
    public function hasDestructiveChanges(Survey $survey, array $newQuestions): bool
    {
        $currentQuestions = $survey->questions()->with('options')->get()->keyBy('question_id');

        // 1. Check for Deleted Questions
        $newQuestionIds = array_filter(array_column($newQuestions, 'question_id'));
        foreach ($currentQuestions->keys() as $currentId) {
            if (! in_array($currentId, $newQuestionIds)) {
                return true; // Question deleted
            }
        }

        // 2. Check for Modifications to Existing Questions
        foreach ($newQuestions as $newQ) {
            $qId = $newQ['question_id'] ?? null;
            if ($qId && $currentQuestions->has($qId)) {
                $currentQ = $currentQuestions->get($qId);

                // Type Change
                if ($currentQ->question_type !== $this->mapQuestionType($newQ['type'])) {
                    return true;
                }

                // Option Changes
                // If an existing option text is NOT present in the new options, it's a deletion.
                if (in_array($newQ['type'], ['single', 'multiple', 'dropdown'])) {
                    $currentOptionTexts = $currentQ->options->pluck('option_text')->map(fn ($t) => trim($t))->toArray();
                    // If any current option text is missing from new options, it's destructive (data loss for that option)
                    $newOptionTextsPlain = array_map(function ($t) {
                        return trim(is_array($t) ? ($t['text'] ?? $t['option_text'] ?? '') : $t);
                    }, $newQ['options'] ?? []);

                    foreach ($currentOptionTexts as $currentText) {
                        if (! in_array($currentText, $newOptionTextsPlain)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if questions have changed (for versioning).
     */
    public function questionsChanged(Survey $survey, array $newQuestions): bool
    {
        // ... (Keep existing implementation or simplify? Keep as is if it works for ANY change detection)
        // Re-implementing strictly to ensure context matches.
        // Or I can just overwrite the whole block.
        // Let's reuse the existing simple logic or better, relying on checking diffs.
        // For brevity and to minimize diff context issues, I will LEAVE questionsChanged alone in this chunk if it's outside.
        // Wait, I am targeted `questionsChanged` to be REPLACED/MOVED?
        // No, I'll insert `hasDestructiveChanges` before it.

        $currentQuestions = $survey->questions()->with('options')->orderBy('display_order')->get();

        if ($currentQuestions->count() !== count($newQuestions)) {
            return true;
        }

        foreach ($currentQuestions as $index => $currentQuestion) {
            $newQuestion = $newQuestions[$index] ?? null;

            if (! $newQuestion) {
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

                // Extract text from new options (could be strings or objects)
                $newOptionsRaw = $newQuestion['options'] ?? [];
                $newOptions = [];
                foreach ($newOptionsRaw as $opt) {
                    $txt = trim(is_array($opt) ? ($opt['text'] ?? $opt['option_text'] ?? '') : $opt);
                    if (! empty($txt)) {
                        $newOptions[] = $txt;
                    }
                }

                if (count($currentOptions) !== count($newOptions)) {
                    return true;
                }

                foreach ($currentOptions as $i => $currentOption) {
                    if ($currentOption !== $newOptions[$i]) {
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

        return ! $titleChanged && ! $descriptionChanged && ! $questionsChanged;
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyQuestionOption;
use App\Models\SurveyResponse;
use App\Models\SurveyAnswer;

class SurveyController extends Controller
{
    /**
     * Get a single survey for API.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $survey = Survey::with(['creator', 'questions.options', 'responses.respondent', 'designatedUsers'])
            ->findOrFail($id);
        
        return response()->json($survey);
    }

    /**
     * Display the surveys page.
     *
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        
        $surveys = Survey::with(['creator', 'questions.options', 'responses.respondent', 'designatedUsers'])
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($survey) use ($userId) {
                $survey->has_responded = $survey->responses()->where('respondent_id', $userId)->exists();
                
                // 回答済み者の名前
                $survey->respondent_names = $survey->responses->pluck('respondent.name')->filter()->values();
                
                // 未回答者の名前
                $respondedUserIds = $survey->responses->pluck('respondent_id')->toArray();
                $survey->unanswered_names = $survey->designatedUsers
                    ->whereNotIn('id', $respondedUserIds)
                    ->pluck('name')
                    ->values();
                
                return $survey;
            });

        // チームメンバーを取得
        $teamMembers = \App\Models\User::where('is_active', true)->get();

        return Inertia::render('Surveys', [
            'surveys' => $surveys,
            'teamMembers' => $teamMembers,
            'highlight' => $request->query('highlight'),
        ]);
    }

    /**
     * Store a newly created survey.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'deadline' => ['required'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question' => ['required', 'string'],
            'questions.*.type' => ['required', 'string', 'in:single,multiple,text,textarea,rating,scale,dropdown,date'],
            'questions.*.required' => ['boolean'],
            'questions.*.options' => ['array'],
            'questions.*.options.*' => ['string'],
            'questions.*.scaleMin' => ['nullable', 'integer'],
            'questions.*.scaleMax' => ['nullable', 'integer'],
            'questions.*.scaleMinLabel' => ['nullable', 'string'],
            'questions.*.scaleMaxLabel' => ['nullable', 'string'],
            'respondents' => ['array'],
            'respondents.*' => ['integer', 'exists:users,id'],
            'isDraft' => ['boolean'],
        ]);
        
        // カスタムエラーメッセージを設定
        $validator->setCustomMessages([
            'title.required' => 'アンケートタイトルは必須です。',
            'deadline.required' => '回答期限は必須です。',
            'deadline.date' => '回答期限は有効な日付形式で入力してください。',
            'questions.required' => '最低1つの質問を追加してください。',
            'questions.min' => '最低1つの質問を追加してください。',
            'questions.*.question.required' => 'すべての質問を入力してください。',
            'questions.*.type.required' => '質問形式を選択してください。',
        ]);
        
        // 選択肢のエラーメッセージをカスタマイズ
        $validator->after(function ($validator) use ($request) {
            $errors = $validator->errors();
            $messages = $errors->messages();
            
            foreach ($messages as $key => $message) {
                if (preg_match('/^questions\.(\d+)\.options\.(\d+)$/', $key, $matches)) {
                    $questionIndex = (int)$matches[1] + 1;
                    $optionIndex = (int)$matches[2] + 1;
                    $errors->forget($key);
                    $errors->add($key, "質問{$questionIndex}番目の選択肢{$optionIndex}に文字を入力してください。");
                }
            }
        });

        // 選択肢形式の質問のバリデーション（最低2つの選択肢チェック）
        $validator->after(function ($validator) use ($request) {
            foreach ($request->questions as $index => $question) {
                if (in_array($question['type'], ['single', 'multiple', 'dropdown'])) {
                    $validOptions = array_filter($question['options'] ?? [], function ($opt) {
                        return !empty(trim($opt));
                    });
                    if (count($validOptions) < 2) {
                        $validator->errors()->add(
                            "questions.{$index}.options",
                            "選択肢形式の質問には最低2つの選択肢が必要です。"
                        );
                    }
                }
            }
        });

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            // アンケート本体を保存
            $deadline = $request->deadline;
            $deadlineDate = null;
            $deadlineTime = null;
            
            if ($deadline) {
                $dt = \Carbon\Carbon::parse($deadline);
                $deadlineDate = $dt->format('Y-m-d');
                $deadlineTime = $dt->format('H:i:s');
            }
            
            $survey = Survey::create([
                'title' => $request->title,
                'description' => $request->description,
                'created_by' => Auth::id(),
                'deadline_date' => $deadlineDate,
                'deadline_time' => $deadlineTime,
                'is_active' => !($request->isDraft ?? false),
            ]);

            // 質問を保存
            foreach ($request->questions as $index => $questionData) {
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

                // 選択肢を保存（選択肢形式の質問の場合）
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

            // 回答者を保存
            if (!empty($request->respondents)) {
                foreach ($request->respondents as $userId) {
                    \App\Models\SurveyRespondent::create([
                        'survey_id' => $survey->survey_id,
                        'user_id' => $userId,
                    ]);
                }
            }

            DB::commit();

            $message = $request->isDraft
                ? 'アンケートを一時保存しました。'
                : 'アンケートを公開しました。';

            return redirect()->route('surveys')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Survey store error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return redirect()->back()
                ->withErrors(['error' => 'アンケートの保存中にエラーが発生しました: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function edit(Survey $survey)
    {
        $survey->load([
            'questions' => fn($query) => $query->orderBy('display_order'),
            'questions.options' => fn($query) => $query->orderBy('display_order'),
        ]);

        $deadline = null;
        if ($survey->deadline_date) {
            $deadline = $survey->deadline_date . ' ' . ($survey->deadline_time ?? '23:59:00');
        }
        
        $editSurvey = [
            'survey_id' => $survey->survey_id,
            'title' => $survey->title,
            'description' => $survey->description,
            'deadline' => $deadline,
            'questions' => $survey->questions->map(fn($question) => [
                'question_id' => $question->question_id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'is_required' => $question->is_required,
                'scaleMin' => $question->scale_min,
                'scaleMax' => $question->scale_max,
                'scaleMinLabel' => $question->scale_min_label,
                'scaleMaxLabel' => $question->scale_max_label,
                'options' => $question->options->pluck('option_text')->toArray(),
            ])->toArray(),
        ];

        $userId = Auth::id();
        
        $surveys = Survey::with(['creator', 'questions.options', 'responses.respondent'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($survey) use ($userId) {
                $survey->has_responded = $survey->responses()->where('respondent_id', $userId)->exists();
                $survey->respondent_names = $survey->responses->pluck('respondent.name')->filter()->values();
                return $survey;
            });

        // チームメンバーを取得
        $teamMembers = \App\Models\User::where('is_active', true)->get();

        return Inertia::render('Surveys', [
            'surveys' => $surveys,
            'editSurvey' => $editSurvey,
            'teamMembers' => $teamMembers,
        ]);
    }

    public function update(Request $request, Survey $survey)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'deadline' => 'required',
            'questions' => 'required|array',
        ]);

        try {
            DB::transaction(function () use ($request, $survey) {
                $deadline = $request->deadline;
                $deadlineDate = null;
                $deadlineTime = null;
                
                if ($deadline) {
                    $dt = \Carbon\Carbon::parse($deadline);
                    $deadlineDate = $dt->format('Y-m-d');
                    $deadlineTime = $dt->format('H:i:s');
                }
                
                $survey->update([
                    'title' => $request->title,
                    'description' => $request->description,
                    'deadline_date' => $deadlineDate,
                    'deadline_time' => $deadlineTime,
                ]);

                $survey->questions()->each(fn($question) => $question->options()->delete());
                $survey->questions()->delete();

                foreach ($request->questions as $index => $questionData) {
                    $question = SurveyQuestion::create([
                        'survey_id' => $survey->survey_id,
                        'question_text' => $questionData['question'],
                        'question_type' => $this->mapQuestionType($questionData['type']),
                        'is_required' => $questionData['required'] ?? false,
                        'display_order' => $index + 1,
                        'scale_min' => $questionData['scaleMin'] ?? null,
                        'scale_max' => $questionData['scaleMax'] ?? null,
                        'scale_min_label' => $questionData['scaleMinLabel'] ?? null,
                        'scale_max_label' => $questionData['scaleMaxLabel'] ?? null,
                    ]);

                    if (in_array($questionData['type'], ['single', 'multiple', 'dropdown']) && !empty($questionData['options'])) {
                        foreach ($questionData['options'] as $optionIndex => $optionText) {
                            if (!empty(trim($optionText))) {
                                SurveyQuestionOption::create([
                                    'question_id' => $question->question_id,
                                    'option_text' => trim($optionText),
                                    'display_order' => $optionIndex + 1,
                                ]);
                            }
                        }
                    }
                }
            });

            return redirect()->route('surveys')->with('success', 'アンケートを更新しました');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'アンケートの更新中にエラーが発生しました']);
        }
    }

    public function answer(Survey $survey)
    {
        // 既に回答済みかチェック
        $hasResponded = $survey->responses()->where('respondent_id', Auth::id())->exists();
        
        if ($hasResponded) {
            return redirect()->route('surveys')
                ->with('error', 'このアンケートには既に回答済みです。');
        }
        
        $survey->load([
            'questions' => fn($query) => $query->orderBy('display_order'),
            'questions.options'
        ]);
        
        return Inertia::render('Surveys/Answer', [
            'survey' => [
                'survey_id' => $survey->survey_id,
                'title' => $survey->title,
                'description' => $survey->description,
                'deadline_date' => $survey->deadline_date,
                'deadline_time' => $survey->deadline_time,
            ],
            'questions' => $survey->questions->map(fn($question) => [
                'question_id' => $question->question_id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'is_required' => $question->is_required,
                'scale_min' => $question->scale_min,
                'scale_max' => $question->scale_max,
                'scale_min_label' => $question->scale_min_label,
                'scale_max_label' => $question->scale_max_label,
                'options' => $question->options->map(fn($option) => [
                    'option_id' => $option->option_id,
                    'option_text' => $option->option_text,
                ])->toArray(),
            ])->toArray(),
        ]);
    }

    public function submitAnswer(Request $request, Survey $survey)
    {
        $validated = $request->validate([
            'answers' => 'required|array',
        ]);
        
        try {
            DB::transaction(function () use ($survey, $validated) {
                $response = SurveyResponse::create([
                    'survey_id' => $survey->survey_id,
                    'respondent_id' => auth()->id(),
                    'submitted_at' => now(),
                ]);
                
                foreach ($validated['answers'] as $questionId => $answerData) {
                    if ($answerData !== null && $answerData !== '') {
                        $question = SurveyQuestion::find($questionId);
                        $selectedOptionId = null;
                        $answerText = $answerData;
                        
                        // Handle option-based questions
                        if (in_array($question->question_type, ['single_choice', 'dropdown']) && is_numeric($answerData)) {
                            $selectedOptionId = $answerData;
                            $option = SurveyQuestionOption::find($answerData);
                            $answerText = $option ? $option->option_text : $answerData;
                        } elseif (is_array($answerData)) {
                            $answerText = json_encode($answerData);
                        }
                        
                        SurveyAnswer::create([
                            'response_id' => $response->response_id,
                            'question_id' => $questionId,
                            'answer_text' => $answerText,
                            'selected_option_id' => $selectedOptionId,
                        ]);
                    }
                }
            });
            
            return redirect()->route('surveys')
                ->with('success', '回答を送信しました');
        } catch (\Exception $e) {
            \Log::error('Survey answer submission failed', [
                'survey_id' => $survey->survey_id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->withErrors(['error' => '回答の送信に失敗しました: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function submit(Request $request, Survey $survey)
    {
        $request->validate([
            'answers' => 'required|array',
        ]);

        try {
            DB::transaction(function () use ($request, $survey) {
                $response = SurveyResponse::create([
                    'survey_id' => $survey->survey_id,
                    'respondent_id' => auth()->id(),
                    'submitted_at' => now(),
                ]);

                foreach ($request->answers as $questionId => $answerData) {
                    SurveyAnswer::create([
                        'response_id' => $response->response_id,
                        'question_id' => $questionId,
                        'answer_text' => is_array($answerData) ? json_encode($answerData) : $answerData,
                    ]);
                }
            });

            return redirect()->route('surveys')->with('success', '回答を送信しました');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => '回答の送信中にエラーが発生しました']);
        }
    }

    /**
     * Move the specified survey to trash.
     *
     * @param  \App\Models\Survey  $survey
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Survey $survey)
    {
        try {
            \Log::info('SurveyController destroy called for survey: ' . $survey->survey_id . ' by user: ' . Auth::id());
            
            // 既に削除済みかチェック
            if ($survey->is_deleted) {
                \Log::info('Survey already deleted: ' . $survey->survey_id);
                return redirect()->route('surveys')
                    ->with('error', 'このアンケートは既に削除されています。');
            }
            
            DB::beginTransaction();

            // アンケートを削除済みにする
            $survey->update(['is_deleted' => true, 'deleted_at' => now()]);
            \Log::info('Survey marked as deleted: ' . $survey->survey_id);

            // ゴミ箱に追加
            $trashItem = \App\Models\TrashItem::create([
                'user_id' => Auth::id(),
                'item_type' => 'survey',
                'is_shared' => true,
                'item_id' => $survey->survey_id,
                'original_title' => $survey->title,
                'deleted_at' => now(),
                'permanent_delete_at' => now()->addMonth(),
            ]);
            \Log::info('TrashItem created with ID: ' . $trashItem->id . ' for survey_id: ' . $survey->survey_id);
            \Log::info('Survey added to trash: ' . $survey->survey_id);

            DB::commit();
            \Log::info('Survey deletion completed successfully');

            return redirect()->route('surveys')
                ->with('success', 'アンケートをゴミ箱に移動しました。');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('SurveyController destroy error: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'アンケートの削除に失敗しました。');
        }
    }

    /**
     * Map frontend question type to database question type.
     *
     * @param  string  $frontendType
     * @return string
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
     * Display the survey results.
     *
     * @param  \App\Models\Survey  $survey
     * @return \Inertia\Response
     */
    public function results(Survey $survey)
    {
        // アンケート本体と質問を取得
        $survey->load(['questions.options', 'creator', 'designatedUsers']);

        // このアンケートの回答データを取得
        $responses = $survey->responses()
            ->with(['answers.question', 'respondent'])
            ->latest('submitted_at')
            ->get();

        // 未回答者を取得
        $respondedUserIds = $responses->pluck('respondent_id')->toArray();
        $unansweredUsers = $survey->designatedUsers
            ->whereNotIn('id', $respondedUserIds)
            ->values();

        // 統計データの計算
        $statistics = $this->calculateStatistics($survey, $responses);

        return Inertia::render('SurveyResults', [
            'survey' => $survey,
            'responses' => $responses,
            'statistics' => $statistics,
            'statistics' => $statistics,
            'unansweredUsers' => $unansweredUsers,
        ]);
    }

    /**
     * Export survey results as CSV.
     *
     * @param  \App\Models\Survey  $survey
     * @return \Illuminate\Http\Response
     */
    public function export(Survey $survey)
    {
        // アンケートの回答データを取得
        $responses = $survey->responses()
            ->with(['answers.question', 'respondent'])
            ->latest('submitted_at')
            ->get();

        // CSVヘッダー
        $headers = ['回答ID', '回答者', '回答日時'];
        foreach ($survey->questions()->orderBy('display_order')->get() as $question) {
            $headers[] = $question->question_text;
        }

        // CSVデータ作成
        $csvData = [];
        $csvData[] = $headers;

        foreach ($responses as $response) {
            $row = [
                $response->response_id,
                $response->respondent->name ?? '匿名',
                $response->submitted_at ? $response->submitted_at->format('Y-m-d H:i:s') : '',
            ];

            foreach ($survey->questions()->orderBy('display_order')->get() as $question) {
                $answer = $response->answers->firstWhere('question_id', $question->question_id);
                if ($answer) {
                    // 選択肢IDがある場合は選択肢テキストを取得
                    if ($answer->selected_option_id) {
                        $option = $question->options->firstWhere('option_id', $answer->selected_option_id);
                        $row[] = $option ? $option->option_text : $answer->answer_text;
                    } else {
                        $row[] = $answer->answer_text ?? '';
                    }
                } else {
                    $row[] = '';
                }
            }

            $csvData[] = $row;
        }

        // CSV出力
        $filename = sprintf('survey_%s_%s.csv', $survey->survey_id, now()->format('YmdHis'));

        $callback = function () use ($csvData) {
            $file = fopen('php://output', 'w');
            // BOM付加（Excel対応）
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            foreach ($csvData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Calculate statistics for survey results.
     *
     * @param  \App\Models\Survey  $survey
     * @param  \Illuminate\Database\Eloquent\Collection  $responses
     * @return array
     */
    private function calculateStatistics($survey, $responses)
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
     * Restore the specified survey from trash.
     *
     * @param  int  $surveyId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore($surveyId)
    {
        try {
            DB::transaction(function () use ($surveyId) {
                $survey = Survey::findOrFail($surveyId);
                $survey->update([
                    'is_deleted' => false,
                    'deleted_at' => null,
                ]);
                
                \App\Models\TrashItem::where('item_type', 'survey')
                    ->where('item_id', $surveyId)
                    ->where('user_id', Auth::id())
                    ->delete();
            });
            
            return redirect()->route('surveys')
                ->with('success', 'アンケートを復元しました。');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', '復元に失敗しました。');
        }
    }

    /**
     * Map database question type to frontend question type.
     *
     * @param  string  $dbType
     * @return string
     */
    private function mapQuestionTypeToFrontend(string $dbType): string
    {
        $mapping = [
            'single_choice' => 'single',
            'multiple_choice' => 'multiple',
            'text' => 'text',
            'textarea' => 'textarea',
            'rating' => 'rating',
            'scale' => 'scale',
            'dropdown' => 'dropdown',
            'date' => 'date',
        ];

        return $mapping[$dbType] ?? $dbType;
    }
}

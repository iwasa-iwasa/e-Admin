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
     * Display the surveys page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        $surveys = Survey::with(['creator', 'questions.options', 'responses.respondent'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Surveys', [
            'surveys' => $surveys,
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
            'category' => ['nullable', 'string', 'max:50'],
            'deadline' => ['required', 'date'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question' => ['required', 'string'],
            'questions.*.type' => ['required', 'string', 'in:single,multiple,text,textarea,rating,scale,dropdown,date'],
            'questions.*.required' => ['boolean'],
            'questions.*.options' => ['array'],
            'questions.*.options.*' => ['string'],
            'isDraft' => ['boolean'],
        ], [
            'title.required' => 'アンケートタイトルは必須です。',
            'deadline.required' => '回答期限は必須です。',
            'deadline.date' => '回答期限は有効な日付形式で入力してください。',
            'questions.required' => '最低1つの質問を追加してください。',
            'questions.min' => '最低1つの質問を追加してください。',
            'questions.*.question.required' => 'すべての質問を入力してください。',
            'questions.*.type.required' => '質問形式を選択してください。',
        ]);

        // 選択肢形式の質問のバリデーション
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
            $survey = Survey::create([
                'title' => $request->title,
                'description' => $request->description,
                'created_by' => Auth::id(),
                'deadline' => $request->deadline,
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

                // スケール形式の質問の場合、最小値・最大値を保存（必要に応じて）
                // 現在のスキーマではスケール値は保存されないため、必要に応じて拡張可能
            }

            DB::commit();

            $message = $request->isDraft
                ? 'アンケートを一時保存しました。'
                : 'アンケートを公開しました。';

            return redirect()->route('surveys')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withErrors(['error' => 'アンケートの保存中にエラーが発生しました。'])
                ->withInput();
        }
    }

    public function edit(Survey $survey)
    {
        $survey->load([
            'questions' => fn($query) => $query->orderBy('display_order'),
            'questions.options' => fn($query) => $query->orderBy('display_order'),
        ]);

        $editSurvey = [
            'survey_id' => $survey->survey_id,
            'title' => $survey->title,
            'description' => $survey->description,
            'deadline' => $survey->deadline,
            'questions' => $survey->questions->map(fn($question) => [
                'question_id' => $question->question_id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'is_required' => $question->is_required,
                'options' => $question->options->pluck('option_text')->toArray(),
            ])->toArray(),
        ];

        return Inertia::render('Surveys', [
            'surveys' => Survey::with(['creator', 'questions.options', 'responses.respondent'])->orderBy('created_at', 'desc')->get(),
            'editSurvey' => $editSurvey,
        ]);
    }

    public function update(Request $request, Survey $survey)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'deadline' => 'required|date',
            'questions' => 'required|array',
        ]);

        try {
            DB::transaction(function () use ($request, $survey) {
                $survey->update([
                    'title' => $request->title,
                    'description' => $request->description,
                    'deadline' => $request->deadline,
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
        $survey->load([
            'questions' => fn($query) => $query->orderBy('display_order'),
            'questions.options'
        ]);

        return Inertia::render('Surveys/Answer', [
            'survey' => [
                'survey_id' => $survey->survey_id,
                'title' => $survey->title,
                'description' => $survey->description,
                'deadline' => $survey->deadline,
            ],
            'questions' => $survey->questions->map(fn($question) => [
                'question_id' => $question->question_id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'is_required' => $question->is_required,
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
                $response = \App\Models\SurveyResponse::create([
                    'survey_id' => $survey->survey_id,
                    'respondent_id' => auth()->id(),
                    'submitted_at' => now(),
                ]);
                
                foreach ($validated['answers'] as $questionId => $answerText) {
                    if ($answerText !== null && $answerText !== '') {
                        SurveyAnswer::create([
                            'response_id' => $response->response_id,
                            'question_id' => $questionId,
                            'answer_text' => is_array($answerText) ? json_encode($answerText) : $answerText,
                            'selected_option_id' => null,
                        ]);
                    }
                }
            });
            
            return redirect()->route('surveys')
                ->with('success', '回答を送信しました');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => '回答の送信に失敗しました'])
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
     * Remove the specified survey from storage.
     *
     * @param  \App\Models\Survey  $survey
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Survey $survey)
    {
        try {
            DB::beginTransaction();

            // 関連データを削除（カスケード削除でない場合）
            $survey->questions()->each(function ($question) {
                $question->options()->delete();
            });
            $survey->questions()->delete();
            $survey->delete();

            DB::commit();

            return redirect()->route('surveys')
                ->with('success', 'アンケートを削除しました。');
        } catch (\Exception $e) {
            DB::rollBack();

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
        $survey->load(['questions.options', 'creator']);

        // このアンケートの回答データを取得
        $responses = $survey->responses()
            ->with(['answers.question', 'respondent'])
            ->latest('submitted_at')
            ->get();

        // 統計データの計算
        $statistics = $this->calculateStatistics($survey, $responses);

        return Inertia::render('SurveyResults', [
            'survey' => $survey,
            'responses' => $responses,
            'statistics' => $statistics,
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

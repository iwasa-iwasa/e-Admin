<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\Survey;
use App\Http\Requests\StoreSurveyRequest;
use App\Http\Requests\UpdateSurveyRequest;
use App\Services\SurveyService;

class SurveyController extends Controller
{
    protected $surveyService;

    public function __construct(SurveyService $surveyService)
    {
        $this->surveyService = $surveyService;
    }

    /**
     * Get a single survey for API.
     */
    public function show($id)
    {
        $survey = Survey::with(['creator', 'questions.options', 'responses.respondent', 'designatedUsers'])
            ->findOrFail($id);
        
        return response()->json($survey);
    }

    /**
     * Display the surveys page.
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
     */
    public function store(StoreSurveyRequest $request)
    {
        try {
            $this->surveyService->createSurvey($request->validated());

            $message = $request->isDraft
                ? 'アンケートを一時保存しました。'
                : 'アンケートを公開しました。';

            return redirect()->route('surveys')
                ->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Survey store error', [
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
                'options' => $question->options->map(fn($option) => [
                    'option_id' => $option->option_id,
                    'text' => $option->option_text,
                    'option_text' => $option->option_text, // For compatibility
                ])->toArray(),
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

    public function update(UpdateSurveyRequest $request, Survey $survey)
    {
        try {
            // 既存回答の確認と警告ロジックはServiceに移譲するか、ここでチェック
            // 警告表示はViewへの返却が必要なため、Controllerに残すかServiceから例外/戻り値で制御
            
            // Serviceのヘルパーを利用
            $hasResponses = $survey->responses()->exists();
            $questionsChanged = $this->surveyService->questionsChanged($survey, $request->questions);
            $onlyDeadlineChanged = $this->surveyService->onlyDeadlineChanged($survey, $request->validated());
            $responseCount = $survey->responses()->count();
            
            // 既存回答があり、質問が変更され、確認が取れていない場合
            if ($hasResponses && $questionsChanged && !$onlyDeadlineChanged && !$request->boolean('confirm_reset')) {
                return back()->withErrors([
                    'requires_confirmation' => true,
                    'response_count' => $responseCount,
                    'message' => "このアンケートには{$responseCount}件の回答があります。質問を変更すると既存の回答が削除されます。続行しますか？"
                ]);
            }

            // 確認済みでリセットが要求された場合、既存の回答を全て削除
            if ($request->boolean('reset_responses') && $hasResponses) {
                Log::info("Survey {$survey->survey_id} responses reset by user " . Auth::id());
                $survey->responses()->delete();
            }

            $this->surveyService->updateSurvey($survey, $request->validated());

            return redirect()->route('surveys')->with('success', 'アンケートを更新しました');
        } catch (\Exception $e) {
            Log::error('Survey update error', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'アンケートの更新中にエラーが発生しました']);
        }
    }

    public function answer(Survey $survey)
    {
        // 既存の回答を取得
        $existingResponse = $survey->responses()
            ->where('respondent_id', Auth::id())
            ->first();
        
        $survey->load([
            'questions' => fn($query) => $query->orderBy('display_order'),
            'questions.options'
        ]);
        
        // 既存の回答をマップ化
        //$existingAnswers = $existingResponse ? $existingResponse->answers : [];
        // Note: SurveyResponse model casts 'answers' to array, so we can use it directly.
        // However, we need to ensure it's an array.
        $existingAnswers = ($existingResponse && $existingResponse->answers) ? $existingResponse->answers : [];
        
        // If status is draft, we should probably let frontend know?
        // But the frontend mainly needs the answers to fill the form.
        // We can pass status to frontend if needed.
        
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
            'existingAnswers' => $existingAnswers,
            'isEditing' => $existingResponse !== null,
        ]);
    }

    public function submitAnswer(Request $request, Survey $survey)
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'status' => 'nullable|in:draft,submitted',
        ]);
        
        $status = $validated['status'] ?? 'submitted';
        
        try {
            $this->surveyService->saveAnswer($survey, $validated['answers'], Auth::id(), $status);
            
            $message = ($status === 'draft') ? '回答を一時保存しました' : '回答を送信しました';
            
            return redirect()->route('surveys')
                ->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Survey answer submission failed', [
                'survey_id' => $survey->survey_id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->withErrors(['error' => '回答の送信に失敗しました: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Move the specified survey to trash.
     */
    public function destroy(Survey $survey)
    {
        try {
            Log::info('SurveyController destroy called for survey: ' . $survey->survey_id . ' by user: ' . Auth::id());
            
            $this->surveyService->deleteSurvey($survey);
            
            Log::info('Survey deletion completed successfully');

            return redirect()->route('surveys')
                ->with('success', 'アンケートをゴミ箱に移動しました。');
        } catch (\Exception $e) {
            Log::error('SurveyController destroy error: ' . $e->getMessage());

            return redirect()->back()
                ->with('error', 'アンケートの削除に失敗しました。');
        }
    }

    /**
     * Display the survey results.
     */
    public function results(Survey $survey)
    {
        // アンケート本体と質問を取得
        $survey->load(['questions.options', 'creator', 'designatedUsers']);
        
        // 質問の詳細情報を取得
        $survey->questions->each(function ($question) {
            $question->makeVisible(['scale_min_label', 'scale_max_label']);
        });

        // このアンケートの回答データを取得
        $responses = $survey->responses()
            ->with(['respondent'])
            ->latest('submitted_at')
            ->get();

        // 未回答者を取得
        $respondedUserIds = $responses->pluck('respondent_id')->toArray();
        $unansweredUsers = $survey->designatedUsers
            ->whereNotIn('id', $respondedUserIds)
            ->values();

        // 統計データの計算 (Service利用)
        $statistics = $this->surveyService->calculateStatistics($survey, $responses);

        return Inertia::render('SurveyResults', [
            'survey' => $survey,
            'responses' => $responses,
            'statistics' => $statistics,
            'unansweredUsers' => $unansweredUsers,
        ]);
    }

    /**
     * Export survey results as CSV.
     */
    public function export(Survey $survey)
    {
        // このアンケートの回答データを取得
        $responses = $survey->responses()
            ->with(['respondent']) // answers relation removed
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

            $answers = $response->answers ?? [];

            foreach ($survey->questions()->orderBy('display_order')->get() as $question) {
                $val = $answers[$question->question_id] ?? null;
                
                if ($val !== null && $val !== '') {
                    if (in_array($question->question_type, ['single_choice', 'dropdown']) && is_numeric($val)) {
                         $option = $question->options->firstWhere('option_id', $val);
                         $row[] = $option ? $option->option_text : $val;
                    } elseif ($question->question_type === 'multiple_choice') {
                         // Resolve options
                         $vals = is_array($val) ? $val : [$val];
                         $texts = [];
                         foreach($vals as $vid) {
                             $opt = $question->options->firstWhere('option_id', $vid);
                             if($opt) $texts[] = $opt->option_text;
                         }
                         $row[] = implode(', ', $texts);
                    } else {
                         $row[] = is_string($val) ? $val : json_encode($val, JSON_UNESCAPED_UNICODE);
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
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM

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
     * Restore the specified survey from trash.
     */
    public function restore($surveyId)
    {
        try {
            $this->surveyService->restoreSurvey($surveyId);
            
            return redirect()->route('surveys')
                ->with('success', 'アンケートを復元しました。');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', '復元に失敗しました。');
        }
    }
}

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
}

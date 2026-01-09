<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSurveyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'title.required' => 'アンケートタイトルは必須です。',
            'deadline.required' => '回答期限は必須です。',
            'questions.required' => '最低1つの質問を追加してください。',
            'questions.min' => '最低1つの質問を追加してください。',
            'questions.*.question.required' => 'すべての質問を入力してください。',
            'questions.*.type.required' => '質問形式を選択してください。',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        // 選択肢のエラーメッセージをカスタマイズ
        $validator->after(function ($validator) {
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
        $validator->after(function ($validator) {
            $questions = $this->input('questions', []);
            foreach ($questions as $index => $question) {
                if (in_array($question['type'] ?? '', ['single', 'multiple', 'dropdown'])) {
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
    }
}

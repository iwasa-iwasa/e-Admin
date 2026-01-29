<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSharedNoteRequest extends FormRequest
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
            'content' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'in:yellow,blue,green,pink,purple,gray'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
            'deadline' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'participants' => ['nullable', 'array'],
            'participants.*' => ['exists:users,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'タイトルを入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'participants.*.exists' => '選択された参加者が見つかりません。',
            'tags.*.max' => 'タグは50文字以内で入力してください。',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSharedNoteRequest extends FormRequest
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
            'pinned' => ['nullable', 'boolean'],
        ];
    }
}

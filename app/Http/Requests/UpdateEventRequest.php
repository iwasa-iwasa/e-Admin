<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Permission check is handled in Controller or Policy, but simple check can be here if needed
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
            'title' => 'required|string|max:255',
            'date_range' => 'required|array|size:2',
            'date_range.*' => 'required|date',
            'is_all_day' => 'required|boolean',
            'start_time' => 'nullable|required_if:is_all_day,false|date_format:H:i',
            'end_time' => 'nullable|required_if:is_all_day,false|date_format:H:i|after:start_time',
            'participants' => 'nullable|array',
            'participants.*' => 'exists:users,id',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'url' => 'nullable|url|max:500',
            'category' => 'required|string|in:会議,休暇,業務,重要,来客,出張,その他',
            'importance' => 'required|string|in:重要,中,低',
            'progress' => 'nullable|integer|min:0|max:100',
            'attachments' => 'nullable|array',
            'attachments.new_files' => 'nullable|array',
            'attachments.new_files.*' => 'file|max:10240', // 10MB max
            'attachments.removed_ids' => 'nullable|array',
            'attachments.removed_ids.*' => 'integer',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\EventCategory;
use App\Enums\EventImportance;

class StoreEventRequest extends FormRequest
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
            'category' => ['required', Rule::enum(EventCategory::class)],
            'importance' => ['required', Rule::enum(EventImportance::class)],
            'progress' => 'nullable|integer|min:0|max:100',
            'recurrence' => 'nullable|array',
            'recurrence.is_recurring' => 'boolean',
            'recurrence.recurrence_type' => 'required_if:recurrence.is_recurring,true|string|in:daily,weekly,monthly,yearly',
            'recurrence.recurrence_interval' => 'required_if:recurrence.is_recurring,true|integer|min:1',
            'recurrence.by_day' => 'nullable|array',
            'recurrence.by_day.*' => [Rule::in(['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'])],
            'recurrence.by_set_pos' => 'nullable|integer',
            'recurrence.end_date' => 'nullable|date|after_or_equal:end_date',
            'attachments' => 'nullable|array',
            'attachments.new_files' => 'nullable|array',
            'attachments.new_files.*' => 'file|max:10240', // 10MB max
        ];
    }
}

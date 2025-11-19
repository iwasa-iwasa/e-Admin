<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SurveyQuestionData extends Data
{
    public function __construct(
        public int $question_id,
        public int $survey_id,
        public string $question_text,
        public string $question_type,
        public bool $is_required,
        public int $display_order,
    ) {
    }
}

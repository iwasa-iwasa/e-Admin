<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SurveyQuestionOptionData extends Data
{
    public function __construct(
        public int $option_id,
        public int $question_id,
        public string $option_text,
        public int $display_order,
    ) {
    }
}

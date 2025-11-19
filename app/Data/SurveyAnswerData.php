<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SurveyAnswerData extends Data
{
    public function __construct(
        public int $answer_id,
        public int $response_id,
        public int $question_id,
        public ?string $answer_text,
        public ?int $selected_option_id,
    ) {
    }
}

<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class SurveyResponseData extends Data
{
    public function __construct(
        public int $response_id,
        public int $survey_id,
        public int $respondent_id,
        public ?Carbon $submitted_at,
    ) {
    }
}

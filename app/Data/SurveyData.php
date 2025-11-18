<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class SurveyData extends Data
{
    public function __construct(
        public int $survey_id,
        public string $title,
        public ?string $description,
        public int $created_by,
        public ?Carbon $deadline,
        public bool $is_active,
        public bool $is_deleted,
        public ?Carbon $created_at,
        public ?Carbon $updated_at,
        public ?Carbon $deleted_at,
    ) {
    }
}

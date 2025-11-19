<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class CalendarData extends Data
{
    public function __construct(
        public int $calendar_id,
        public string $calendar_name,
        public string $calendar_type,
        public ?Carbon $created_at,
    ) {
    }
}

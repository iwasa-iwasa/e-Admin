<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class EventRecurrenceData extends Data
{
    public function __construct(
        public int $recurrence_id,
        public int $event_id,
        public string $recurrence_type,
        public int $recurrence_interval,
        public ?array $by_day,
        public ?int $by_set_pos,
        public ?string $recurrence_unit,
        public ?Carbon $end_date,
    ) {
    }
}
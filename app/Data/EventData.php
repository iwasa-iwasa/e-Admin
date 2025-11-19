<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class EventData extends Data
{
    public function __construct(
        public int $event_id,
        public int $calendar_id,
        public string $title,
        public ?string $description,
        public ?string $location,
        public ?string $url,
        public string $category,
        public string $importance,
        public Carbon $start_date,
        public ?string $start_time,
        public Carbon $end_date,
        public ?string $end_time,
        public bool $is_all_day,
        public int $created_by,
        public bool $is_deleted,
        public ?Carbon $created_at,
        public ?Carbon $updated_at,
        public ?Carbon $deleted_at,
    ) {
    }
}

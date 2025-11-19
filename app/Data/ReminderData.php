<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class ReminderData extends Data
{
    public function __construct(
        public int $reminder_id,
        public int $user_id,
        public string $title,
        public ?string $description,
        public Carbon $deadline,
        public string $category,
        public bool $completed,
        public ?Carbon $completed_at,
        public bool $is_deleted,
        public ?Carbon $created_at,
        public ?Carbon $updated_at,
        public ?Carbon $deleted_at,
    ) {
    }
}

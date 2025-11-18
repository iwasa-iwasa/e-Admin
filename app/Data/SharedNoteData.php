<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class SharedNoteData extends Data
{
    public function __construct(
        public int $note_id,
        public string $title,
        public ?string $content,
        public int $author_id,
        public string $color,
        public string $priority,
        public ?Carbon $deadline,
        public bool $is_deleted,
        public ?Carbon $created_at,
        public ?Carbon $updated_at,
        public ?Carbon $deleted_at,
    ) {
    }
}

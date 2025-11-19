<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class TrashItemData extends Data
{
    public function __construct(
        public int $id,
        public int $user_id,
        public string $item_type,
        public int $item_id,
        public string $original_title,
        public ?Carbon $deleted_at,
        public ?Carbon $permanent_delete_at,
        public ?Carbon $created_at,
        public ?Carbon $updated_at,
    ) {
    }
}

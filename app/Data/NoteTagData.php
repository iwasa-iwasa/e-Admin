<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class NoteTagData extends Data
{
    public function __construct(
        public int $tag_id,
        public string $tag_name,
    ) {
    }
}

<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class ExampleData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $is_active,
    ) {
    }
}

<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?Carbon $email_verified_at,
        public string $department,
        public string $role,
        public bool $is_active,
        public ?Carbon $created_at,
        public ?Carbon $updated_at,
        public ?string $reason = null,
        public ?Carbon $deactivated_at = null,
    ) {
    }
}

<?php

namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;
use App\Models\User;

class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?Carbon $email_verified_at,
        public ?string $department,
        public ?int $department_id,
        public ?string $role_type,
        public string $role,
        public bool $is_active,
        public ?Carbon $created_at,
        public ?Carbon $updated_at,
        public ?string $reason = null,
        public ?Carbon $deactivated_at = null,
    ) {
    }
    
    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            email_verified_at: $user->email_verified_at,
            department: $user->department?->name ?? $user->department,
            department_id: $user->department_id,
            role_type: $user->role_type,
            role: $user->role,
            is_active: $user->is_active,
            created_at: $user->created_at,
            updated_at: $user->updated_at,
            reason: $user->reason,
            deactivated_at: $user->deactivated_at,
        );
    }
}

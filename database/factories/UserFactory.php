<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 部署をランダムに選択
        $departments = [
            ['総務部', 1],
            ['営業部', 2],
            ['開発部', 3],
            ['経理部', 4],
        ];
        
        $selectedDept = fake()->randomElement($departments);
        
        $department = \App\Models\Department::firstOrCreate(
            ['id' => $selectedDept[1]],
            ['name' => $selectedDept[0], 'is_active' => true]
        );
        
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'department' => $department->name,
            'department_id' => $department->id,
            'role' => 'user',
            'role_type' => 'member',
            'is_active' => true,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}

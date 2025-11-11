<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we don't create duplicate users
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'department' => '総務部',
                'role' => 'admin',
            ]
        );

        $users = [
            [
                'name' => 'aaa',
                'email' => 'tanaka@example.com',
                'password' => Hash::make('password'),
                'department' => '総務部',
                'role' => 'member',
            ],
            [
                'name' => 'bbb',
                'email' => 'sato@example.com',
                'password' => Hash::make('password'),
                'department' => '総務部',
                'role' => 'member',
            ],
            [
                'name' => 'ccc',
                'email' => 'suzuki@example.com',
                'password' => Hash::make('password'),
                'department' => '総務部',
                'role' => 'member',
            ],
            [
                'name' => 'ddd',
                'email' => 'yamada@example.com',
                'password' => Hash::make('password'),
                'department' => '総務部',
                'role' => 'member',
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(['email' => $userData['email']], $userData);
        }
    }
}

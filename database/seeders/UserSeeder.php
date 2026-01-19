<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use \App\Models\Calendar;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'a@a'],
            [
                'name' => 'a',
                'password' => Hash::make('a'),
                'department' => '総務部',
                'role' => 'admin',
            ]
        );
        User::firstOrCreate(
            ['email' => 'b@b'],
            [
                'name' => 'b',
                'password' => Hash::make('b'),
                'department' => '総務部',
                'role' => 'admin',
            ]
        );
        User::factory()->count(8)->create();
    }
}

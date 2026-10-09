<?php

namespace Database\Seeders;

use App\Models\UserModel\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = Hash::make('password');

        foreach (range(1, 10) as $number) {
            User::create([
                'name' => "Test User {$number}",
                'email' => "user{$number}@example.com",
                'username' => "testuser{$number}",
                'phone_number' => '0812345678' . str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'status' => 'Available',
                'role' => 'user',
                'password' => $password,
            ]);
        }
    }
}

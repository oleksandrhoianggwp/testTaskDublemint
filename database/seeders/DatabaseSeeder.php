<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(['email' => 'demo@example.com'], [
            'name' => 'Alex Morgan',
            'password' => 'password',
            'balance_minor' => 100_000,
        ]);

        PromoCode::query()->updateOrCreate(['code' => 'WELCOME10'], [
            'bonus_amount_minor' => 2_500,
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);

        PromoCode::query()->updateOrCreate(['code' => 'BONUS50'], [
            'bonus_amount_minor' => 5_000,
            'expires_at' => now()->addYear(),
            'is_active' => true,
        ]);

        PromoCode::query()->updateOrCreate(['code' => 'OLD100'], [
            'bonus_amount_minor' => 10_000,
            'expires_at' => now()->subDay(),
            'is_active' => true,
        ]);

        PromoCode::query()->updateOrCreate(['code' => 'PAUSED25'], [
            'bonus_amount_minor' => 2_500,
            'expires_at' => now()->addYear(),
            'is_active' => false,
        ]);
    }
}

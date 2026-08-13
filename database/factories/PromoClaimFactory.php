<?php

namespace Database\Factories;

use App\Enums\PromoClaimStatus;
use App\Models\PromoClaim;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PromoClaim> */
class PromoClaimFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'promo_code_id' => PromoCode::factory(),
            'submitted_code' => fn (array $attributes) => PromoCode::find($attributes['promo_code_id'])->code,
            'bonus_amount_minor' => 2_500,
            'status' => PromoClaimStatus::Applied,
            'claimed_at' => now(),
        ];
    }

    public function rejected(string $code = 'PROMO_NOT_FOUND'): static
    {
        return $this->state(fn () => [
            'promo_code_id' => null,
            'submitted_code' => 'UNKNOWN1',
            'bonus_amount_minor' => null,
            'status' => PromoClaimStatus::Rejected,
            'rejection_code' => $code,
            'rejection_reason' => 'Promo code was rejected.',
            'claimed_at' => null,
        ]);
    }
}

<?php

namespace App\Models;

use App\Enums\PromoClaimStatus;
use Database\Factories\PromoClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id',
    'promo_code_id',
    'submitted_code',
    'bonus_amount_minor',
    'status',
    'rejection_code',
    'rejection_reason',
    'claimed_at',
    'revoked_at',
])]
class PromoClaim extends Model
{
    /** @use HasFactory<PromoClaimFactory> */
    use HasFactory;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class);
    }

    protected function casts(): array
    {
        return [
            'bonus_amount_minor' => 'integer',
            'status' => PromoClaimStatus::class,
            'claimed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}

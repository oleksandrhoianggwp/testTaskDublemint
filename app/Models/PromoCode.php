<?php

namespace App\Models;

use Database\Factories\PromoCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'bonus_amount_minor', 'expires_at', 'is_active'])]
class PromoCode extends Model
{
    /** @use HasFactory<PromoCodeFactory> */
    use HasFactory;

    public function claims()
    {
        return $this->hasMany(PromoClaim::class);
    }

    protected function casts(): array
    {
        return [
            'bonus_amount_minor' => 'integer',
            'expires_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }
}

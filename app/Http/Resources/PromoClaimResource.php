<?php

namespace App\Http\Resources;

use App\Enums\PromoClaimStatus;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromoClaimResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->submitted_code,
            'amount' => $this->bonus_amount_minor === null
                ? null
                : Money::format($this->bonus_amount_minor),
            'status' => $this->status->value,
            'rejection_code' => $this->rejection_code,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'can_revoke' => $this->status === PromoClaimStatus::Applied,
        ];
    }
}

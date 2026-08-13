<?php

namespace App\Actions\Promo;

use App\Enums\PromoClaimStatus;
use App\Exceptions\PromoDomainException;
use App\Models\PromoClaim;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ClaimPromoAction
{
    public function execute(int $userId, string $submittedCode): PromoClaim
    {
        $code = strtoupper($submittedCode);

        try {
            $result = DB::transaction(function () use ($userId, $code): PromoClaim|PromoDomainException {
                $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
                $promo = PromoCode::query()->where('code', $code)->lockForUpdate()->first();

                if (! $promo) {
                    return $this->reject($user, null, $code, 'PROMO_NOT_FOUND', 'This promo code does not exist.', 404);
                }

                if (! $promo->is_active) {
                    return $this->reject($user, $promo, $code, 'PROMO_INACTIVE', 'This promo code is inactive.');
                }

                if ($promo->expires_at?->isPast()) {
                    return $this->reject($user, $promo, $code, 'PROMO_EXPIRED', 'This promo code has expired.');
                }

                $alreadyUsed = PromoClaim::query()
                    ->where('user_id', $user->id)
                    ->where('promo_code_id', $promo->id)
                    ->whereIn('status', [PromoClaimStatus::Applied->value, PromoClaimStatus::Revoked->value])
                    ->exists();

                if ($alreadyUsed) {
                    return $this->reject($user, $promo, $code, 'PROMO_ALREADY_USED', 'This promo code has already been used.');
                }

                $claimedAt = now();
                $claim = PromoClaim::query()->create([
                    'user_id' => $user->id,
                    'promo_code_id' => $promo->id,
                    'submitted_code' => $code,
                    'bonus_amount_minor' => $promo->bonus_amount_minor,
                    'status' => PromoClaimStatus::Applied,
                    'claimed_at' => $claimedAt,
                ]);

                $user->balance_minor += $promo->bonus_amount_minor;
                $user->save();

                return $claim;
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->isConsumptionUniquenessViolation($exception)) {
                throw $exception;
            }

            $promo = PromoCode::query()->where('code', $code)->first();
            $user = User::query()->findOrFail($userId);
            $error = $this->reject($user, $promo, $code, 'PROMO_ALREADY_USED', 'This promo code has already been used.');
            throw $error;
        }

        if ($result instanceof PromoDomainException) {
            throw $result;
        }

        return $result;
    }

    private function reject(
        User $user,
        ?PromoCode $promo,
        string $code,
        string $errorCode,
        string $message,
        int $httpStatus = 409,
    ): PromoDomainException {
        PromoClaim::query()->create([
            'user_id' => $user->id,
            'promo_code_id' => $promo?->id,
            'submitted_code' => $code,
            'bonus_amount_minor' => $promo?->bonus_amount_minor,
            'status' => PromoClaimStatus::Rejected,
            'rejection_code' => $errorCode,
            'rejection_reason' => $message,
        ]);

        return new PromoDomainException($errorCode, $message, $httpStatus);
    }

    private function isConsumptionUniquenessViolation(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'promo_claims_unique_consumption');
    }
}

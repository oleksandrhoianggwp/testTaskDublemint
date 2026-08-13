<?php

namespace App\Actions\Promo;

use App\Enums\PromoClaimStatus;
use App\Exceptions\PromoDomainException;
use App\Models\PromoClaim;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RevokePromoAction
{
    public function execute(int $userId, int $claimId): PromoClaim
    {
        $result = DB::transaction(function () use ($userId, $claimId): PromoClaim|PromoDomainException {
            $user = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();
            $claim = PromoClaim::query()
                ->whereKey($claimId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $claim) {
                return new PromoDomainException(
                    'CLAIM_NOT_FOUND',
                    'This promo claim was not found.',
                    404,
                );
            }

            if ($claim->status === PromoClaimStatus::Revoked) {
                return new PromoDomainException(
                    'CLAIM_ALREADY_REVOKED',
                    'This promo bonus has already been revoked.',
                );
            }

            if ($claim->status !== PromoClaimStatus::Applied || $claim->bonus_amount_minor === null) {
                return new PromoDomainException(
                    'CLAIM_NOT_REVOCABLE',
                    'Only an applied promo bonus can be revoked.',
                );
            }

            if ($user->balance_minor < $claim->bonus_amount_minor) {
                return new PromoDomainException(
                    'INSUFFICIENT_BALANCE_TO_REVOKE',
                    'The bonus cannot be revoked because the current balance is insufficient.',
                );
            }

            $user->balance_minor -= $claim->bonus_amount_minor;
            $user->save();

            $claim->status = PromoClaimStatus::Revoked;
            $claim->revoked_at = now();
            $claim->save();

            return $claim;
        }, 3);

        if ($result instanceof PromoDomainException) {
            throw $result;
        }

        return $result;
    }
}

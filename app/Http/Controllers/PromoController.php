<?php

namespace App\Http\Controllers;

use App\Actions\Promo\ClaimPromoAction;
use App\Actions\Promo\RevokePromoAction;
use App\Http\Requests\ClaimPromoRequest;
use App\Http\Requests\PromoHistoryRequest;
use App\Http\Resources\PromoClaimResource;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PromoController extends Controller
{
    public function revoke(Request $request, int $claimId, RevokePromoAction $action): JsonResponse
    {
        $claim = $action->execute($request->user()->id, $claimId);
        $user = $request->user()->fresh();

        return response()->json([
            'message' => 'Promo bonus revoked successfully.',
            'balance' => Money::format($user->balance_minor),
            'deducted_amount' => Money::format($claim->bonus_amount_minor),
            'claim' => new PromoClaimResource($claim),
        ]);
    }

    public function history(PromoHistoryRequest $request): AnonymousResourceCollection
    {
        $claims = $request->user()->promoClaims()
            ->when(
                $request->validated('status'),
                fn ($query, string $status) => $query->where('status', $status),
            )
            ->latest('created_at')
            ->latest('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return PromoClaimResource::collection($claims);
    }

    public function claim(ClaimPromoRequest $request, ClaimPromoAction $action): JsonResponse
    {
        $claim = $action->execute($request->user()->id, $request->validated('code'));
        $request->user()->refresh();

        return response()->json([
            'message' => 'Promo code applied successfully.',
            'balance' => Money::format($request->user()->balance_minor),
            'bonus_amount' => Money::format($claim->bonus_amount_minor),
            'claim' => new PromoClaimResource($claim),
        ], 201);
    }
}

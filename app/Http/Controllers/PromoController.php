<?php

namespace App\Http\Controllers;

use App\Actions\Promo\ClaimPromoAction;
use App\Http\Requests\ClaimPromoRequest;
use App\Http\Resources\PromoClaimResource;
use App\Support\Money;
use Illuminate\Http\JsonResponse;

class PromoController extends Controller
{
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

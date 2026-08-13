<?php

namespace Tests\Feature;

use App\Enums\PromoClaimStatus;
use App\Models\PromoClaim;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RevokePromoTest extends TestCase
{
    use RefreshDatabase;

    public function test_applied_claim_can_be_revoked(): void
    {
        [$user, $claim] = $this->authenticatedAppliedClaim();

        $this->patchJson("/api/promo/{$claim->id}/revoke")
            ->assertOk()
            ->assertJsonPath('message', 'Promo bonus revoked successfully.')
            ->assertJsonPath('claim.status', 'revoked')
            ->assertJsonPath('claim.can_revoke', false);

        $this->assertSame(100_000, $user->fresh()->balance_minor);
    }

    public function test_revoke_deducts_original_claim_amount_even_if_promo_changes(): void
    {
        [$user, $claim, $promo] = $this->authenticatedAppliedClaim(amount: 2_500);
        $promo->update(['bonus_amount_minor' => 9_900]);

        $this->patchJson("/api/promo/{$claim->id}/revoke")
            ->assertOk()
            ->assertJsonPath('deducted_amount', '25.00')
            ->assertJsonPath('balance', '1000.00');

        $this->assertSame(100_000, $user->fresh()->balance_minor);
    }

    public function test_revoke_persists_status_and_timestamp(): void
    {
        [, $claim] = $this->authenticatedAppliedClaim();

        $this->patchJson("/api/promo/{$claim->id}/revoke")->assertOk();

        $claim->refresh();
        $this->assertSame(PromoClaimStatus::Revoked, $claim->status);
        $this->assertNotNull($claim->revoked_at);
    }

    public function test_second_revoke_returns_explicit_error(): void
    {
        [, $claim] = $this->authenticatedAppliedClaim();
        $this->patchJson("/api/promo/{$claim->id}/revoke")->assertOk();

        $this->patchJson("/api/promo/{$claim->id}/revoke")
            ->assertConflict()
            ->assertJsonPath('code', 'CLAIM_ALREADY_REVOKED');
    }

    public function test_second_revoke_never_debits_balance_again(): void
    {
        [$user, $claim] = $this->authenticatedAppliedClaim();
        $this->patchJson("/api/promo/{$claim->id}/revoke")->assertOk();
        $balanceAfterFirstRevoke = $user->fresh()->balance_minor;

        $this->patchJson("/api/promo/{$claim->id}/revoke")->assertConflict();

        $this->assertSame($balanceAfterFirstRevoke, $user->fresh()->balance_minor);
    }

    public function test_rejected_claim_cannot_be_revoked(): void
    {
        $user = User::factory()->create();
        $claim = PromoClaim::factory()->rejected()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/promo/{$claim->id}/revoke")
            ->assertConflict()
            ->assertJsonPath('code', 'CLAIM_NOT_REVOCABLE');

        $this->assertSame(PromoClaimStatus::Rejected, $claim->fresh()->status);
    }

    public function test_player_cannot_revoke_another_players_claim(): void
    {
        $owner = User::factory()->create(['balance_minor' => 102_500]);
        $attacker = User::factory()->create(['balance_minor' => 200_000]);
        $claim = $this->createAppliedClaim($owner);
        Sanctum::actingAs($attacker);

        $this->patchJson("/api/promo/{$claim->id}/revoke")
            ->assertNotFound()
            ->assertJsonPath('code', 'CLAIM_NOT_FOUND');

        $this->assertSame(102_500, $owner->fresh()->balance_minor);
        $this->assertSame(200_000, $attacker->fresh()->balance_minor);
        $this->assertSame(PromoClaimStatus::Applied, $claim->fresh()->status);
    }

    public function test_insufficient_balance_returns_conflict(): void
    {
        $user = User::factory()->create(['balance_minor' => 1_000]);
        $claim = $this->createAppliedClaim($user, 2_500);
        Sanctum::actingAs($user);

        $this->patchJson("/api/promo/{$claim->id}/revoke")
            ->assertConflict()
            ->assertJsonPath('code', 'INSUFFICIENT_BALANCE_TO_REVOKE');
    }

    public function test_insufficient_balance_leaves_wallet_unchanged(): void
    {
        $user = User::factory()->create(['balance_minor' => 1_000]);
        $claim = $this->createAppliedClaim($user, 2_500);
        Sanctum::actingAs($user);

        $this->patchJson("/api/promo/{$claim->id}/revoke")->assertConflict();

        $this->assertSame(1_000, $user->fresh()->balance_minor);
    }

    public function test_insufficient_balance_leaves_claim_applied(): void
    {
        $user = User::factory()->create(['balance_minor' => 1_000]);
        $claim = $this->createAppliedClaim($user, 2_500);
        Sanctum::actingAs($user);

        $this->patchJson("/api/promo/{$claim->id}/revoke")->assertConflict();

        $claim->refresh();
        $this->assertSame(PromoClaimStatus::Applied, $claim->status);
        $this->assertNull($claim->revoked_at);
    }

    public function test_unknown_claim_returns_not_found_without_wallet_mutation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/promo/999999/revoke')
            ->assertNotFound()
            ->assertJsonPath('code', 'CLAIM_NOT_FOUND');

        $this->assertSame(100_000, $user->fresh()->balance_minor);
    }

    private function authenticatedAppliedClaim(int $amount = 2_500): array
    {
        $user = User::factory()->create(['balance_minor' => 100_000 + $amount]);
        $claim = $this->createAppliedClaim($user, $amount);
        Sanctum::actingAs($user);

        return [$user, $claim, $claim->promoCode];
    }

    private function createAppliedClaim(User $user, int $amount = 2_500): PromoClaim
    {
        $promo = PromoCode::factory()->create(['bonus_amount_minor' => $amount]);

        return PromoClaim::query()->create([
            'user_id' => $user->id,
            'promo_code_id' => $promo->id,
            'submitted_code' => $promo->code,
            'bonus_amount_minor' => $amount,
            'status' => PromoClaimStatus::Applied,
            'claimed_at' => now(),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\PromoClaimStatus;
use App\Models\PromoClaim;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PromoHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_only_contains_authenticated_players_claims(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $ownClaim = $this->createClaim($user, PromoClaimStatus::Applied);
        $this->createClaim($other, PromoClaimStatus::Rejected);
        Sanctum::actingAs($user);

        $this->getJson('/api/promo/history')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownClaim->id)
            ->assertJsonMissing(['user_id' => $other->id]);
    }

    public function test_history_is_ordered_newest_first(): void
    {
        $user = User::factory()->create();
        $oldest = $this->createClaim($user, PromoClaimStatus::Rejected);
        $middle = $this->createClaim($user, PromoClaimStatus::Rejected);
        $newest = $this->createClaim($user, PromoClaimStatus::Rejected);
        Sanctum::actingAs($user);

        $this->getJson('/api/promo/history')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonPath('data.2.id', $oldest->id);
    }

    #[DataProvider('statuses')]
    public function test_status_filter_returns_only_requested_status(string $status): void
    {
        $user = User::factory()->create();
        foreach (PromoClaimStatus::cases() as $claimStatus) {
            $this->createClaim($user, $claimStatus);
        }
        Sanctum::actingAs($user);

        $this->getJson("/api/promo/history?status={$status}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', $status)
            ->assertJsonPath('meta.total', 1);
    }

    public static function statuses(): array
    {
        return [
            'applied' => [PromoClaimStatus::Applied->value],
            'rejected' => [PromoClaimStatus::Rejected->value],
            'revoked' => [PromoClaimStatus::Revoked->value],
        ];
    }

    public function test_invalid_status_filter_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/promo/history?status=pending')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_history_uses_real_backend_pagination(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 13) as $index) {
            $this->createClaim($user, PromoClaimStatus::Rejected, "UNKNOWN{$index}");
        }
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/promo/history?per_page=5&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 13);

        $this->assertStringContainsString('per_page=5', $response->json('links.next'));
    }

    public function test_per_page_is_capped_at_fifty(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/promo/history?per_page=51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_history_resource_exposes_revocation_capability_without_internal_money_units(): void
    {
        $user = User::factory()->create();
        $this->createClaim($user, PromoClaimStatus::Applied, 'WELCOME10', 2_500);
        Sanctum::actingAs($user);

        $this->getJson('/api/promo/history')
            ->assertOk()
            ->assertJsonPath('data.0.amount', '25.00')
            ->assertJsonPath('data.0.can_revoke', true)
            ->assertJsonMissingPath('data.0.bonus_amount_minor')
            ->assertJsonMissingPath('data.0.user_id');
    }

    private function createClaim(
        User $user,
        PromoClaimStatus $status,
        ?string $code = null,
        int $amount = 2_500,
    ): PromoClaim {
        $code ??= 'PROMO'.str_pad((string) PromoClaim::query()->count(), 3, '0', STR_PAD_LEFT);
        $promo = $status === PromoClaimStatus::Rejected
            ? null
            : PromoCode::factory()->create(['code' => $code]);

        return PromoClaim::query()->create([
            'user_id' => $user->id,
            'promo_code_id' => $promo?->id,
            'submitted_code' => $code,
            'bonus_amount_minor' => $status === PromoClaimStatus::Rejected ? null : $amount,
            'status' => $status,
            'rejection_code' => $status === PromoClaimStatus::Rejected ? 'PROMO_NOT_FOUND' : null,
            'rejection_reason' => $status === PromoClaimStatus::Rejected ? 'This promo code does not exist.' : null,
            'claimed_at' => $status === PromoClaimStatus::Rejected ? null : now(),
            'revoked_at' => $status === PromoClaimStatus::Revoked ? now() : null,
        ]);
    }
}

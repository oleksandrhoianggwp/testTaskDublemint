<?php

namespace Tests\Feature;

use App\Enums\PromoClaimStatus;
use App\Models\PromoClaim;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PromoClaimTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidPromoCodes')]
    public function test_invalid_promo_payloads_return_validation_errors(array $payload): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/promo/claim', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->assertDatabaseCount('promo_claims', 0);
        $this->assertSame(100_000, $user->fresh()->balance_minor);
    }

    public static function invalidPromoCodes(): array
    {
        return [
            'missing' => [[]],
            'too short' => [['code' => 'ABC12']],
            'too long' => [['code' => 'PROMOCODE12345']],
            'invalid symbol' => [['code' => 'BAD-CODE']],
            'non string' => [['code' => ['WELCOME10']]],
        ];
    }

    public function test_unknown_promo_is_recorded_as_rejected_without_crediting_balance(): void
    {
        $user = $this->authenticatedUser();

        $this->postJson('/api/promo/claim', ['code' => 'UNKNOWN1'])
            ->assertNotFound()
            ->assertJsonPath('code', 'PROMO_NOT_FOUND');

        $this->assertSame(100_000, $user->fresh()->balance_minor);
        $this->assertDatabaseHas('promo_claims', [
            'user_id' => $user->id,
            'submitted_code' => 'UNKNOWN1',
            'status' => PromoClaimStatus::Rejected->value,
            'rejection_code' => 'PROMO_NOT_FOUND',
        ]);
    }

    public function test_expired_promo_is_rejected(): void
    {
        $user = $this->authenticatedUser();
        $promo = PromoCode::factory()->expired()->create(['code' => 'OLD100']);

        $this->postJson('/api/promo/claim', ['code' => $promo->code])
            ->assertConflict()
            ->assertJsonPath('code', 'PROMO_EXPIRED');

        $this->assertSame(100_000, $user->fresh()->balance_minor);
    }

    public function test_inactive_promo_is_rejected(): void
    {
        $this->authenticatedUser();
        $promo = PromoCode::factory()->inactive()->create(['code' => 'PAUSED25']);

        $this->postJson('/api/promo/claim', ['code' => $promo->code])
            ->assertConflict()
            ->assertJsonPath('code', 'PROMO_INACTIVE');
    }

    public function test_valid_promo_credits_exact_amount_and_persists_claim(): void
    {
        $user = $this->authenticatedUser();
        $promo = PromoCode::factory()->create([
            'code' => 'WELCOME10',
            'bonus_amount_minor' => 2_500,
        ]);

        $this->postJson('/api/promo/claim', ['code' => $promo->code])
            ->assertCreated()
            ->assertJsonPath('balance', '1025.00')
            ->assertJsonPath('bonus_amount', '25.00')
            ->assertJsonPath('claim.code', 'WELCOME10')
            ->assertJsonPath('claim.status', 'applied');

        $this->assertSame(102_500, $user->fresh()->balance_minor);
        $this->assertDatabaseHas('promo_claims', [
            'user_id' => $user->id,
            'promo_code_id' => $promo->id,
            'bonus_amount_minor' => 2_500,
            'status' => PromoClaimStatus::Applied->value,
        ]);
    }

    public function test_same_promo_cannot_credit_player_twice(): void
    {
        $user = $this->authenticatedUser();
        $promo = PromoCode::factory()->create(['code' => 'WELCOME10']);

        $this->postJson('/api/promo/claim', ['code' => $promo->code])->assertCreated();
        $balanceAfterFirstClaim = $user->fresh()->balance_minor;

        $this->postJson('/api/promo/claim', ['code' => $promo->code])
            ->assertConflict()
            ->assertJsonPath('code', 'PROMO_ALREADY_USED');

        $this->assertSame($balanceAfterFirstClaim, $user->fresh()->balance_minor);
        $this->assertDatabaseCount('promo_claims', 2);
    }

    public function test_different_promo_is_rejected_during_24_hour_cooldown(): void
    {
        $this->travelTo(Carbon::parse('2026-08-18 10:00:00 UTC'), function (): void {
            $user = $this->authenticatedUser();
            $firstPromo = PromoCode::factory()->create(['code' => 'PROMOA1']);
            $secondPromo = PromoCode::factory()->create(['code' => 'PROMOB2']);

            $this->postJson('/api/promo/claim', ['code' => $firstPromo->code])->assertCreated();
            $balanceAfterFirstClaim = $user->fresh()->balance_minor;

            $this->travel(10)->minutes();

            $this->postJson('/api/promo/claim', ['code' => $secondPromo->code])
                ->assertConflict()
                ->assertJsonPath('code', 'PROMO_CLAIM_COOLDOWN');

            $this->assertSame($balanceAfterFirstClaim, $user->fresh()->balance_minor);
            $this->assertDatabaseHas('promo_claims', [
                'user_id' => $user->id,
                'promo_code_id' => $secondPromo->id,
                'status' => PromoClaimStatus::Rejected->value,
                'rejection_code' => 'PROMO_CLAIM_COOLDOWN',
            ]);
        });
    }

    public function test_claim_is_rejected_at_23_hours_and_59_minutes(): void
    {
        $this->travelTo(Carbon::parse('2026-08-18 10:00:00 UTC'), function (): void {
            $user = $this->authenticatedUser();
            $firstPromo = PromoCode::factory()->create(['code' => 'PROMOA1']);
            $secondPromo = PromoCode::factory()->create(['code' => 'PROMOB2']);

            $this->postJson('/api/promo/claim', ['code' => $firstPromo->code])->assertCreated();
            $balanceAfterFirstClaim = $user->fresh()->balance_minor;

            $this->travel(23)->hours();
            $this->travel(59)->minutes();

            $this->postJson('/api/promo/claim', ['code' => $secondPromo->code])
                ->assertConflict()
                ->assertJsonPath('code', 'PROMO_CLAIM_COOLDOWN');

            $this->assertSame($balanceAfterFirstClaim, $user->fresh()->balance_minor);
        });
    }

    public function test_different_promo_may_be_claimed_after_24_hours(): void
    {
        $this->travelTo(Carbon::parse('2026-08-18 10:00:00 UTC'), function (): void {
            $user = $this->authenticatedUser();
            $firstPromo = PromoCode::factory()->create([
                'code' => 'PROMOA1',
                'bonus_amount_minor' => 2_500,
            ]);
            $secondPromo = PromoCode::factory()->create([
                'code' => 'PROMOB2',
                'bonus_amount_minor' => 5_000,
            ]);

            $this->postJson('/api/promo/claim', ['code' => $firstPromo->code])->assertCreated();

            $this->travel(24)->hours();

            $this->postJson('/api/promo/claim', ['code' => $secondPromo->code])
                ->assertCreated()
                ->assertJsonPath('balance', '1075.00')
                ->assertJsonPath('bonus_amount', '50.00');

            $this->assertSame(107_500, $user->fresh()->balance_minor);
        });
    }

    public function test_rejected_attempts_neither_start_nor_reset_cooldown(): void
    {
        $this->travelTo(Carbon::parse('2026-08-18 10:00:00 UTC'), function (): void {
            $user = $this->authenticatedUser();
            $firstPromo = PromoCode::factory()->create(['code' => 'PROMOA1']);
            $secondPromo = PromoCode::factory()->create(['code' => 'PROMOB2']);
            $expiredPromo = PromoCode::factory()->expired()->create(['code' => 'OLD100']);

            $this->postJson('/api/promo/claim', ['code' => 'UNKNOWN1'])
                ->assertNotFound()
                ->assertJsonPath('code', 'PROMO_NOT_FOUND');

            $this->postJson('/api/promo/claim', ['code' => $firstPromo->code])->assertCreated();

            $this->travel(23)->hours();

            $this->postJson('/api/promo/claim', ['code' => $expiredPromo->code])
                ->assertConflict()
                ->assertJsonPath('code', 'PROMO_EXPIRED');

            $this->travel(1)->hours();

            $this->postJson('/api/promo/claim', ['code' => $secondPromo->code])
                ->assertCreated()
                ->assertJsonPath('balance', '1050.00');

            $this->assertSame(105_000, $user->fresh()->balance_minor);
        });
    }

    public function test_revoked_promo_still_counts_as_used(): void
    {
        $user = $this->authenticatedUser();
        $promo = PromoCode::factory()->create(['code' => 'WELCOME10']);
        $this->createConsumedClaim($user, $promo, PromoClaimStatus::Revoked);

        $this->postJson('/api/promo/claim', ['code' => $promo->code])
            ->assertConflict()
            ->assertJsonPath('code', 'PROMO_ALREADY_USED');

        $this->assertSame(100_000, $user->fresh()->balance_minor);
    }

    public function test_player_identity_comes_from_token_not_request_body(): void
    {
        $authenticated = $this->authenticatedUser();
        $other = User::factory()->create(['balance_minor' => 200_000]);
        $promo = PromoCode::factory()->create(['code' => 'WELCOME10']);

        $this->postJson('/api/promo/claim', [
            'code' => $promo->code,
            'user_id' => $other->id,
            'player_id' => $other->id,
        ])->assertCreated();

        $this->assertSame(102_500, $authenticated->fresh()->balance_minor);
        $this->assertSame(200_000, $other->fresh()->balance_minor);
    }

    public function test_each_player_may_use_the_same_promo_once(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $promo = PromoCode::factory()->create(['code' => 'WELCOME10']);

        Sanctum::actingAs($first);
        $this->postJson('/api/promo/claim', ['code' => $promo->code])->assertCreated();

        Sanctum::actingAs($second);
        $this->postJson('/api/promo/claim', ['code' => $promo->code])->assertCreated();

        $this->assertSame(102_500, $first->fresh()->balance_minor);
        $this->assertSame(102_500, $second->fresh()->balance_minor);
    }

    public function test_promo_code_is_trimmed_and_normalized_to_uppercase(): void
    {
        $this->authenticatedUser();
        PromoCode::factory()->create(['code' => 'WELCOME10']);

        $this->postJson('/api/promo/claim', ['code' => '  welcome10  '])
            ->assertCreated()
            ->assertJsonPath('claim.code', 'WELCOME10');
    }

    public function test_database_prevents_negative_balance(): void
    {
        $user = User::factory()->create();

        $this->assertPostgresConstraint(fn () => $user->update(['balance_minor' => -1]));
    }

    public function test_database_prevents_non_positive_bonus_amount(): void
    {
        $this->assertPostgresConstraint(
            fn () => PromoCode::factory()->create(['bonus_amount_minor' => 0]),
        );
    }

    public function test_database_prevents_duplicate_successful_consumption(): void
    {
        $user = User::factory()->create();
        $promo = PromoCode::factory()->create();
        $this->createConsumedClaim($user, $promo, PromoClaimStatus::Applied);

        $this->assertPostgresConstraint(
            fn () => $this->createConsumedClaim($user, $promo, PromoClaimStatus::Applied),
            '23505',
        );
    }

    private function authenticatedUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function createConsumedClaim(User $user, PromoCode $promo, PromoClaimStatus $status): PromoClaim
    {
        return PromoClaim::query()->create([
            'user_id' => $user->id,
            'promo_code_id' => $promo->id,
            'submitted_code' => $promo->code,
            'bonus_amount_minor' => $promo->bonus_amount_minor,
            'status' => $status,
            'claimed_at' => now(),
            'revoked_at' => $status === PromoClaimStatus::Revoked ? now() : null,
        ]);
    }

    private function assertPostgresConstraint(callable $operation, string $expectedSqlState = '23514'): void
    {
        try {
            $operation();
            $this->fail('Expected PostgreSQL to reject the invalid financial state.');
        } catch (QueryException $exception) {
            $this->assertSame($expectedSqlState, (string) $exception->getCode());
        }
    }
}

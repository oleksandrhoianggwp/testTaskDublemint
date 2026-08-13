<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->bigInteger('bonus_amount_minor');
            $table->timestampTz('expires_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE promo_codes ADD CONSTRAINT promo_bonus_positive CHECK (bonus_amount_minor > 0)');
        DB::statement("ALTER TABLE promo_codes ADD CONSTRAINT promo_code_format CHECK (code ~ '^[A-Z0-9]{6,12}$')");

        Schema::create('promo_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('submitted_code', 12);
            $table->bigInteger('bonus_amount_minor')->nullable();
            $table->string('status', 16);
            $table->string('rejection_code', 64)->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'status', 'created_at']);
        });

        DB::statement("ALTER TABLE promo_claims ADD CONSTRAINT promo_claim_status_valid CHECK (status IN ('applied', 'rejected', 'revoked'))");
        DB::statement('ALTER TABLE promo_claims ADD CONSTRAINT promo_claim_amount_positive CHECK (bonus_amount_minor IS NULL OR bonus_amount_minor > 0)');
        DB::statement("CREATE UNIQUE INDEX promo_claims_unique_consumption ON promo_claims (user_id, promo_code_id) WHERE status IN ('applied', 'revoked')");
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_claims');
        Schema::dropIfExists('promo_codes');
    }
};

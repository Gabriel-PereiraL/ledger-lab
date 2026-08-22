<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->timestampsTz();
        });

        Schema::create('wallets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_client_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->char('currency', 3)->default('BRL');
            $table->bigInteger('balance_cents')->default(0);
            $table->timestampsTz();
        });

        Schema::create('transfers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_client_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('source_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignUuid('destination_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('status', 20)->default('posted');
            $table->string('idempotency_key', 128);
            $table->char('request_hash', 64);
            $table->uuid('reverses_transfer_id')->nullable();
            $table->timestampsTz();
            $table->unique(['api_client_id', 'idempotency_key']);
            $table->unique('reverses_transfer_id');
            $table->index(['source_wallet_id', 'created_at']);
            $table->index(['destination_wallet_id', 'created_at']);
        });

        Schema::table('transfers', function (Blueprint $table): void {
            $table->foreign('reverses_transfer_id')->references('id')->on('transfers')->restrictOnDelete();
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('wallet_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_cents');
            $table->char('currency', 3);
            $table->string('direction', 6);
            $table->timestampsTz();
            $table->unique(['transfer_id', 'wallet_id']);
            $table->index(['wallet_id', 'created_at']);
        });

        Schema::create('provider_operations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->unique();
            $table->bigInteger('amount_cents');
            $table->string('status', 30);
            $table->timestampsTz();
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('external_event_id')->unique();
            $table->string('type');
            $table->jsonb('payload');
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE wallets ADD CONSTRAINT wallets_nonnegative_balance CHECK (balance_cents >= 0)');
        DB::statement('ALTER TABLE transfers ADD CONSTRAINT transfers_positive_amount CHECK (amount_cents > 0)');
        DB::statement('ALTER TABLE transfers ADD CONSTRAINT transfers_distinct_wallets CHECK (source_wallet_id <> destination_wallet_id)');
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_nonzero_amount CHECK (amount_cents <> 0)');
        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_direction_amount CHECK ((direction = 'debit' AND amount_cents < 0) OR (direction = 'credit' AND amount_cents > 0))");
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('provider_operations');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('api_clients');
    }
};

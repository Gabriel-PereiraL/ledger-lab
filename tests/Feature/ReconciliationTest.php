<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\ProviderOperation;
use App\Models\Transfer;
use App\Models\Wallet;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function transfer(): Transfer
    {
        $c = ApiClient::query()->create(['name' => 'x', 'token_hash' => hash('sha256', 'x')]);
        $a = Wallet::query()->create(['api_client_id' => $c->id, 'name' => 'a', 'currency' => 'BRL', 'balance_cents' => 100]);
        $b = Wallet::query()->create(['api_client_id' => $c->id, 'name' => 'b', 'currency' => 'BRL', 'balance_cents' => 0]);

        return app(TransferService::class)->transfer($c, ['source_wallet_id' => $a->id, 'destination_wallet_id' => $b->id, 'amount_cents' => 100, 'currency' => 'BRL'], 'x');
    }

    public function test_detects_missing_amount_and_status_differences(): void
    {
        $t = $this->transfer();
        $this->artisan('ledger:reconcile --json')->expectsOutputToContain('missing')->assertFailed();
        $provider = ProviderOperation::query()->create(['transfer_id' => $t->id, 'external_id' => 'op', 'amount_cents' => 99, 'status' => 'posted']);
        $this->artisan('ledger:reconcile --json')->expectsOutputToContain('amount_mismatch')->assertFailed();
        $provider->update(['amount_cents' => 100, 'status' => 'pending']);
        $this->artisan('ledger:reconcile --json')->expectsOutputToContain('status_mismatch')->assertFailed();
    }

    public function test_passes_when_provider_matches(): void
    {
        $t = $this->transfer();
        ProviderOperation::query()->create(['transfer_id' => $t->id, 'external_id' => 'op', 'amount_cents' => $t->amount_cents, 'status' => $t->status]);
        $this->artisan('ledger:reconcile')->expectsOutput('Reconciliation passed: no differences found.')->assertSuccessful();
    }
}

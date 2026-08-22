<?php

namespace Tests\Feature;

use App\Jobs\NotifyTransferPosted;
use App\Models\ApiClient;
use App\Models\Transfer;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    private ApiClient $client;

    private Wallet $source;

    private Wallet $destination;

    private string $token = 'secret-token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = ApiClient::query()->create(['name' => 'Test', 'token_hash' => hash('sha256', $this->token)]);
        $this->source = Wallet::query()->create(['api_client_id' => $this->client->id, 'name' => 'Source', 'currency' => 'BRL', 'balance_cents' => 10000]);
        $this->destination = Wallet::query()->create(['api_client_id' => $this->client->id, 'name' => 'Destination', 'currency' => 'BRL', 'balance_cents' => 0]);
    }

    /** @return array<string,mixed> */
    private function payload(int $amount = 2500): array
    {
        return ['source_wallet_id' => $this->source->id, 'destination_wallet_id' => $this->destination->id, 'amount_cents' => $amount, 'currency' => 'BRL'];
    }

    private function postTransfer(string $key = 'key-1', int $amount = 2500)
    {
        return $this->withHeaders(['X-Api-Key' => $this->token, 'Idempotency-Key' => $key])->postJson('/api/transfers', $this->payload($amount));
    }

    public function test_posts_balanced_ledger_entries_and_updates_projection_atomically(): void
    {
        Queue::fake();
        $this->postTransfer()->assertCreated()->assertJsonPath('data.status', 'posted');
        $transfer = Transfer::query()->firstOrFail();
        $this->assertSame(0, $transfer->entries()->sum('amount_cents'));
        $this->assertSame(2, $transfer->entries()->count());
        $this->assertSame(7500, $this->source->fresh()->balance_cents);
        $this->assertSame(2500, $this->destination->fresh()->balance_cents);
        Queue::assertPushed(NotifyTransferPosted::class);
    }

    public function test_rejects_insufficient_funds_without_partial_writes(): void
    {
        $this->postTransfer('large', 10001)->assertUnprocessable();
        $this->assertDatabaseCount('transfers', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
        $this->assertSame(10000, $this->source->fresh()->balance_cents);
    }

    public function test_same_idempotency_key_returns_original_without_duplicate_entries(): void
    {
        $first = $this->postTransfer('same')->assertCreated()->json('data.id');
        $second = $this->postTransfer('same')->assertCreated()->json('data.id');
        $this->assertSame($first, $second);
        $this->assertDatabaseCount('transfers', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_same_key_with_different_payload_is_a_conflict(): void
    {
        $this->postTransfer('same')->assertCreated();
        $this->postTransfer('same', 100)->assertConflict();
        $this->assertDatabaseCount('transfers', 1);
    }

    public function test_reversal_uses_compensating_entries_and_cannot_repeat(): void
    {
        $id = $this->postTransfer()->json('data.id');
        $first = $this->withHeaders(['X-Api-Key' => $this->token, 'Idempotency-Key' => 'reverse-1'])->postJson("/api/transfers/$id/reversal")->assertCreated()->assertJsonPath('data.reverses_transfer_id', $id)->json('data.id');
        $retry = $this->withHeaders(['X-Api-Key' => $this->token, 'Idempotency-Key' => 'reverse-1'])->postJson("/api/transfers/$id/reversal")->assertCreated()->json('data.id');
        $this->assertSame($first, $retry);
        $this->assertSame(10000, $this->source->fresh()->balance_cents);
        $this->assertSame(0, $this->destination->fresh()->balance_cents);
        $this->withHeaders(['X-Api-Key' => $this->token, 'Idempotency-Key' => 'reverse-2'])->postJson("/api/transfers/$id/reversal")->assertUnprocessable();
        $this->assertDatabaseCount('ledger_entries', 4);
    }

    public function test_authentication_and_wallet_ownership_are_enforced(): void
    {
        $this->postJson('/api/transfers', $this->payload(), ['Idempotency-Key' => 'x'])->assertUnauthorized();
        $other = ApiClient::query()->create(['name' => 'Other', 'token_hash' => hash('sha256', 'other')]);
        $wallet = Wallet::query()->create(['api_client_id' => $other->id, 'name' => 'Other', 'currency' => 'BRL', 'balance_cents' => 0]);
        $payload = $this->payload();
        $payload['destination_wallet_id'] = $wallet->id;
        $this->withHeaders(['X-Api-Key' => $this->token, 'Idempotency-Key' => 'ownership'])->postJson('/api/transfers', $payload)->assertUnprocessable();
    }

    public function test_secondary_job_failure_does_not_change_posted_financial_state(): void
    {
        Queue::fake();
        $id = $this->postTransfer('job-failure')->assertCreated()->json('data.id');

        (new NotifyTransferPosted($id))->failed(new \RuntimeException('simulated notification outage'));

        $this->assertDatabaseHas('transfers', ['id' => $id, 'status' => 'posted']);
        $this->assertSame(7500, $this->source->fresh()->balance_cents);
        $this->assertSame(2500, $this->destination->fresh()->balance_cents);
        $this->assertDatabaseCount('ledger_entries', 2);
    }
}

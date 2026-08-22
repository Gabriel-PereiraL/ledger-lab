<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrentTransferTest extends TestCase
{
    use DatabaseMigrations;

    public function test_a_waiting_transfer_observes_the_committed_balance_and_cannot_double_spend(): void
    {
        $client = ApiClient::query()->create(['name' => 'Concurrent client', 'token_hash' => hash('sha256', 'concurrent')]);
        $source = Wallet::query()->create(['api_client_id' => $client->id, 'name' => 'Source', 'currency' => 'BRL', 'balance_cents' => 100]);
        $destination = Wallet::query()->create(['api_client_id' => $client->id, 'name' => 'Destination', 'currency' => 'BRL', 'balance_cents' => 0]);

        DB::beginTransaction();
        Wallet::query()->whereKey($source->id)->lockForUpdate()->firstOrFail();
        $command = [PHP_BINARY, base_path('tests/Support/concurrent_transfer.php'), $client->id, $source->id, $destination->id];
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
        $this->assertIsResource($process);

        usleep(250_000);
        Wallet::query()->whereKey($source->id)->decrement('balance_cents', 80);
        DB::commit();
        $status = proc_close($process);

        $this->assertSame(0, $status);
        $this->assertSame(20, $source->fresh()->balance_cents);
        $this->assertDatabaseCount('transfers', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }
}

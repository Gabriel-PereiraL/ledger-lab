<?php

namespace App\Console\Commands;

use App\Contracts\ExternalProvider;
use App\Models\Transfer;
use Illuminate\Console\Command;

class ReconcileProvider extends Command
{
    public function __construct(private readonly ExternalProvider $provider)
    {
        parent::__construct();
    }

    protected $signature = 'ledger:reconcile {--json}';

    protected $description = 'Compare posted transfers with the fake provider representation';

    public function handle(): int
    {
        $issues = [];
        Transfer::query()->whereNull('reverses_transfer_id')->orderBy('id')->each(function (Transfer $transfer) use (&$issues): void {
            $provider = $this->provider->findByTransferId($transfer->id);
            if (! $provider) {
                $issues[] = ['transfer_id' => $transfer->id, 'type' => 'missing'];

                return;
            } if ($provider->amount_cents !== $transfer->amount_cents) {
                $issues[] = ['transfer_id' => $transfer->id, 'type' => 'amount_mismatch', 'local' => $transfer->amount_cents, 'provider' => $provider->amount_cents];
            } if ($provider->status !== $transfer->status) {
                $issues[] = ['transfer_id' => $transfer->id, 'type' => 'status_mismatch', 'local' => $transfer->status, 'provider' => $provider->status];
            }
        });
        if ($this->option('json')) {
            $this->line(json_encode(['issues' => $issues], JSON_THROW_ON_ERROR));
        } elseif ($issues === []) {
            $this->info('Reconciliation passed: no differences found.');
        } else {
            $this->table(['transfer_id', 'type', 'local', 'provider'], array_map(fn (array $i): array => [$i['transfer_id'], $i['type'], $i['local'] ?? '', $i['provider'] ?? ''], $issues));
        }

        return $issues === [] ? self::SUCCESS : self::FAILURE;
    }
}

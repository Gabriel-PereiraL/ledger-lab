<?php

namespace App\Services;

use App\Exceptions\IdempotencyConflict;
use App\Exceptions\InsufficientFunds;
use App\Exceptions\TransferNotAllowed;
use App\Jobs\NotifyTransferPosted;
use App\Models\ApiClient;
use App\Models\LedgerEntry;
use App\Models\Transfer;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class TransferService
{
    /** @param array{source_wallet_id:string,destination_wallet_id:string,amount_cents:int,currency:string} $data */
    public function transfer(ApiClient $client, array $data, string $idempotencyKey): Transfer
    {
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($client, $data, $idempotencyKey, $hash): Transfer {
            $ids = [$data['source_wallet_id'], $data['destination_wallet_id']];
            sort($ids, SORT_STRING);
            /** @var Collection<int, Wallet> $wallets */
            $wallets = Wallet::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $existing = Transfer::query()->where('api_client_id', $client->id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                throw_unless(hash_equals($existing->request_hash, $hash), IdempotencyConflict::class, 'Idempotency key was already used with another request.');

                return $existing->load('entries');
            }

            $source = $wallets->get($data['source_wallet_id']);
            $destination = $wallets->get($data['destination_wallet_id']);
            if (! $source || ! $destination || $source->api_client_id !== $client->id || $destination->api_client_id !== $client->id) {
                throw new TransferNotAllowed('Both wallets must exist and belong to the authenticated client.');
            }
            if ($source->currency !== $data['currency'] || $destination->currency !== $data['currency']) {
                throw new TransferNotAllowed('Wallet and transfer currencies must match.');
            }
            if ($source->balance_cents < $data['amount_cents']) {
                throw new InsufficientFunds('Insufficient funds.');
            }

            $transfer = Transfer::query()->create([...$data, 'api_client_id' => $client->id, 'status' => 'posted', 'idempotency_key' => $idempotencyKey, 'request_hash' => $hash]);
            $source->decrement('balance_cents', $data['amount_cents']);
            $destination->increment('balance_cents', $data['amount_cents']);
            LedgerEntry::query()->create(['transfer_id' => $transfer->id, 'wallet_id' => $source->id, 'amount_cents' => -$data['amount_cents'], 'currency' => $data['currency'], 'direction' => 'debit']);
            LedgerEntry::query()->create(['transfer_id' => $transfer->id, 'wallet_id' => $destination->id, 'amount_cents' => $data['amount_cents'], 'currency' => $data['currency'], 'direction' => 'credit']);
            NotifyTransferPosted::dispatch($transfer->id)->afterCommit();
            Log::info('transfer.posted', ['transfer_id' => $transfer->id, 'amount_cents' => $data['amount_cents'], 'currency' => $data['currency']]);

            return $transfer->load('entries');
        }, 3);
    }

    public function reverse(ApiClient $client, Transfer $original, string $idempotencyKey): Transfer
    {
        if ($original->api_client_id !== $client->id || $original->reverses_transfer_id) {
            throw new TransferNotAllowed('Transfer cannot be reversed.');
        }

        return DB::transaction(function () use ($client, $original, $idempotencyKey): Transfer {
            $locked = Transfer::query()->lockForUpdate()->findOrFail($original->id);
            if ($locked->reversal()->exists()) {
                throw new TransferNotAllowed('Transfer was already reversed.');
            }
            $reversal = $this->transfer($client, [
                'source_wallet_id' => $locked->destination_wallet_id,
                'destination_wallet_id' => $locked->source_wallet_id,
                'amount_cents' => $locked->amount_cents,
                'currency' => $locked->currency,
            ], $idempotencyKey);
            $reversal->update(['reverses_transfer_id' => $locked->id]);

            return $reversal->fresh('entries');
        }, 3);
    }
}

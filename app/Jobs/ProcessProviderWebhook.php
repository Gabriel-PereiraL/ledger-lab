<?php

namespace App\Jobs;

use App\Models\ProviderOperation;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

class ProcessProviderWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [1, 5, 30];

    public function __construct(public string $eventId) {}

    public function handle(): void
    {
        DB::transaction(function (): void {
            $event = WebhookEvent::query()->lockForUpdate()->findOrFail($this->eventId);
            if ($event->processed_at) {
                return;
            } if ($event->type !== 'operation.updated') {
                throw new UnexpectedValueException('Unsupported provider event type.');
            } /** @var array{data:array{external_id:string,amount_cents:int,status:string,transfer_id?:string}} $payload */ $payload = $event->payload;
            $data = $payload['data'];
            ProviderOperation::query()->updateOrCreate(['external_id' => $data['external_id']], ['transfer_id' => $data['transfer_id'] ?? null, 'amount_cents' => $data['amount_cents'], 'status' => $data['status']]);
            $event->update(['processed_at' => now()]);
            Log::info('provider.webhook.processed', ['event_id' => $event->external_event_id, 'type' => $event->type]);
        });
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('provider.webhook.failed', ['event_id' => $this->eventId, 'error_type' => $exception ? $exception::class : null]);
    }
}

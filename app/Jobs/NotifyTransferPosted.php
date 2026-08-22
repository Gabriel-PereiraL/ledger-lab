<?php

namespace App\Jobs;

use App\Models\Transfer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class NotifyTransferPosted implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [1, 5, 30];

    public function __construct(public string $transferId)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $transfer = Transfer::query()->findOrFail($this->transferId);
        Log::info('transfer.notification.sent', ['transfer_id' => $transfer->id]);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('transfer.notification.failed', ['transfer_id' => $this->transferId, 'error_type' => $exception?->getMessage() ? $exception::class : null]);
    }
}

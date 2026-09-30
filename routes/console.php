<?php

use App\Jobs\ProcessProviderWebhook;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    WebhookEvent::query()->whereNull('processed_at')->orderBy('created_at')->limit(100)
        ->pluck('id')->each(fn (string $id) => ProcessProviderWebhook::dispatch($id));
})->name('recover-pending-provider-webhooks')->everyMinute()->withoutOverlapping();

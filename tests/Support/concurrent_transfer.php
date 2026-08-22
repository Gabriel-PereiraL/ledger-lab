<?php

use App\Exceptions\InsufficientFunds;
use App\Models\ApiClient;
use App\Services\TransferService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $clientId, $sourceId, $destinationId] = $argv;
$client = ApiClient::query()->findOrFail($clientId);

try {
    $app->make(TransferService::class)->transfer($client, [
        'source_wallet_id' => $sourceId,
        'destination_wallet_id' => $destinationId,
        'amount_cents' => 50,
        'currency' => 'BRL',
    ], 'concurrent-request');
    exit(2);
} catch (InsufficientFunds) {
    exit(0);
} catch (Throwable) {
    exit(3);
}

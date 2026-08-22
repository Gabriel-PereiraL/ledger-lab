<?php

use App\Http\Controllers\ProviderWebhookController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn (): array => ['status' => 'ok']);
Route::post('/webhooks/provider', ProviderWebhookController::class)->middleware('throttle:60,1');
Route::middleware(['client.auth', 'throttle:120,1'])->group(function (): void {
    Route::post('/wallets', [WalletController::class, 'store']);
    Route::get('/wallets/{wallet}', [WalletController::class, 'show']);
    Route::post('/transfers', [TransferController::class, 'store']);
    Route::post('/transfers/{transfer}/reversal', [TransferController::class, 'reverse']);
});

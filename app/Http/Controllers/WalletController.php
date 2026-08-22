<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateWalletRequest;
use App\Models\ApiClient;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function store(CreateWalletRequest $request): JsonResponse
    { /** @var ApiClient $client */ $client = $request->attributes->get('api_client');
        $wallet = $client->wallets()->create([...$request->validated(), 'currency' => strtoupper($request->string('currency', 'BRL')->toString())]);

        return response()->json(['data' => $wallet], 201);
    }

    public function show(string $wallet, Request $request): JsonResponse
    { /** @var ApiClient $client */ $client = $request->attributes->get('api_client');
        $model = Wallet::query()->where('api_client_id', $client->id)->findOrFail($wallet);

        return response()->json(['data' => $model->load('entries')]);
    }
}

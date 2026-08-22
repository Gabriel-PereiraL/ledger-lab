<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTransferRequest;
use App\Models\ApiClient;
use App\Models\Transfer;
use App\Services\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(private readonly TransferService $service) {}

    public function store(CreateTransferRequest $request): JsonResponse
    { /** @var ApiClient $client */ $client = $request->attributes->get('api_client');
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && $key !== '' && strlen($key) <= 128, 422, 'A valid Idempotency-Key header is required.');

        $data = [
            'source_wallet_id' => $request->string('source_wallet_id')->toString(),
            'destination_wallet_id' => $request->string('destination_wallet_id')->toString(),
            'amount_cents' => $request->integer('amount_cents'),
            'currency' => strtoupper($request->string('currency')->toString()),
        ];

        return response()->json(['data' => $this->service->transfer($client, $data, $key)], 201);
    }

    public function reverse(Request $request, Transfer $transfer): JsonResponse
    { /** @var ApiClient $client */ $client = $request->attributes->get('api_client');
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && $key !== '' && strlen($key) <= 128, 422, 'A valid Idempotency-Key header is required.');

        return response()->json(['data' => $this->service->reverse($client, $transfer, $key)], 201);
    }
}

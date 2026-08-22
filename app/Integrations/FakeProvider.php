<?php

namespace App\Integrations;

use App\Contracts\ExternalProvider;
use App\Models\ProviderOperation;

final class FakeProvider implements ExternalProvider
{
    public function findByTransferId(string $transferId): ?ProviderOperation
    {
        return ProviderOperation::query()->where('transfer_id', $transferId)->first();
    }
}

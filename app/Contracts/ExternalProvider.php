<?php

namespace App\Contracts;

use App\Models\ProviderOperation;

interface ExternalProvider
{
    public function findByTransferId(string $transferId): ?ProviderOperation;
}

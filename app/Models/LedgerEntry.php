<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    use HasUuids;

    protected $fillable = ['transfer_id', 'wallet_id', 'amount_cents', 'currency', 'direction'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }
}

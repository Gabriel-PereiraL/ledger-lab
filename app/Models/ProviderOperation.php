<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProviderOperation extends Model
{
    use HasUuids;

    protected $fillable = ['transfer_id', 'external_id', 'amount_cents', 'status'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }
}

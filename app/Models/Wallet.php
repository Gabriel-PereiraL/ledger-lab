<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasUuids;

    protected $fillable = ['api_client_id', 'name', 'currency', 'balance_cents'];

    protected function casts(): array
    {
        return ['balance_cents' => 'integer'];
    }

    /** @return BelongsTo<ApiClient, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}

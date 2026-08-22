<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transfer extends Model
{
    use HasUuids;

    protected $fillable = ['api_client_id', 'source_wallet_id', 'destination_wallet_id', 'amount_cents', 'currency', 'status', 'idempotency_key', 'request_hash', 'reverses_transfer_id'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** @return BelongsTo<Transfer, $this> */
    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transfer_id');
    }

    /** @return HasOne<Transfer, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_transfer_id');
    }
}

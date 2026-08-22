<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiClient extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'token_hash'];

    /** @return HasMany<Wallet, $this> */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'source_wallet_id' => ['required', 'uuid'],
            'destination_wallet_id' => ['required', 'uuid', 'different:source_wallet_id'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'in:BRL'],
        ];
    }
}

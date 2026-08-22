<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'currency' => ['sometimes', 'string', 'size:3', 'in:BRL']];
    }
}

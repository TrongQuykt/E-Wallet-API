<?php

declare(strict_types=1);

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class DepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'wallet_id' => ['required', 'exists:wallets,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_id' => ['required', 'string', 'max:255'],
            'signature' => ['required', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}

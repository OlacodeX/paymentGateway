<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthorizePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'string', 'max:255'],
            'customer_id' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'], 
            'currency' => ['sometimes', 'string', 'size:3'],
            'card_number' => ['required', 'string', 'regex:/^\d{13,19}$/'],
            'cvv' => ['required', 'string', 'regex:/^\d{3,4}$/'],
            'expiry_month' => ['required', 'integer', 'min:1', 'max:12'],
            'expiry_year' => ['required', 'integer', 'min:'.now()->year, 'max:2099'],
        ];
    }
}

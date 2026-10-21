<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'string', 'in:credit_card,paypal'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'simulate' => ['nullable', 'in:fail,exception'],
        ];
    }
}

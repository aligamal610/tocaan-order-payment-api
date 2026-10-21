<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:pending,successful,failed'],
            'method' => ['nullable', 'in:credit_card,paypal'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

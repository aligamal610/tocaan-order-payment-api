<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge($this->itemRules(), [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', 'in:pending,confirmed,cancelled'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'items' => ['required', 'array', 'min:1'],
        ]);
    }

    protected function orderRules(): array
    {
        return [
            'customer_name' => ['sometimes', 'required', 'string', 'max:255'],
            'customer_email' => ['sometimes', 'required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', 'in:pending,confirmed,cancelled'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ];
    }

    protected function itemRules(): array
    {
        return [
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['required_with:items', 'regex:/^\d{1,8}(\.\d{1,2})?$/'],
        ];
    }
}

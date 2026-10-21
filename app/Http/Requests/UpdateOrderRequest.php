<?php

namespace App\Http\Requests;

class UpdateOrderRequest extends StoreOrderRequest
{
    public function rules(): array
    {
        return array_merge($this->orderRules(), $this->itemRules(), [
            'items' => ['sometimes', 'array', 'min:1'],
        ]);
    }
}

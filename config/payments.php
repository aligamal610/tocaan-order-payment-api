<?php

return [
    'default_currency' => env('ORDER_DEFAULT_CURRENCY', 'USD'),
    'gateways' => [
        'credit_card' => App\Payments\Gateways\CreditCardGateway::class,
        'paypal' => App\Payments\Gateways\PayPalGateway::class,
    ],
];

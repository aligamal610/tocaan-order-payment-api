<?php

namespace App\Payments;

interface PaymentGatewayInterface
{
    public function method(): string;

    public function charge(string $amount, string $currency, array $payload = []): GatewayResult;
}

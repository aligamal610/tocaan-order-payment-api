<?php

namespace App\Payments;

use DomainException;

final class PaymentGatewayManager
{
    public function gateway(string $method): PaymentGatewayInterface
    {
        $class = config('payments.gateways.'.$method);

        if (! $class || ! is_a($class, PaymentGatewayInterface::class, true)) {
            throw new DomainException('Unsupported payment method.');
        }

        return app($class);
    }
}

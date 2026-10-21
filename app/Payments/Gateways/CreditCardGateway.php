<?php

namespace App\Payments\Gateways;

use App\Payments\GatewayResult;
use App\Payments\PaymentGatewayInterface;
use Illuminate\Support\Str;

final class CreditCardGateway implements PaymentGatewayInterface
{
    public function method(): string
    {
        return 'credit_card';
    }

    public function charge(string $amount, string $currency, array $payload = []): GatewayResult
    {
        if (($payload['simulate'] ?? null) === 'exception') {
            throw new \RuntimeException('Simulated gateway outage');
        }

        $success = ($payload['simulate'] ?? null) !== 'fail';

        return new GatewayResult(
            $success ? 'successful' : 'failed',
            $success ? 'cc_'.Str::uuid() : null,
            ['simulation' => true]
        );
    }
}

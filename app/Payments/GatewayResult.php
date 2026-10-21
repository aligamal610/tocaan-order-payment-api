<?php

namespace App\Payments;

final class GatewayResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $reference,
        public readonly array $metadata = []
    ) {
    }
}

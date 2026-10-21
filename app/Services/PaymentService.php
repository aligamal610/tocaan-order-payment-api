<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentGatewayManager;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class PaymentService
{
    public function __construct(private readonly PaymentGatewayManager $gateways)
    {
    }

    public function process(Order $order, array $input): Payment
    {
        return DB::transaction(function () use ($order, $input): Payment {
            if ($order->status !== 'confirmed') {
                throw new DomainException('Only confirmed orders can be paid.');
            }

            $existingPayment = $this->findIdempotentPayment($order, $input);

            if ($existingPayment) {
                return $existingPayment;
            }

            $payment = $order->payments()->create([
                'method' => $input['method'],
                'status' => 'pending',
                'amount' => $order->total,
                'currency' => $order->currency,
                'idempotency_key' => $input['idempotency_key'] ?? null,
            ]);

            try {
                $result = $this->gateways->gateway($input['method'])->charge(
                    (string) $order->total,
                    $order->currency,
                    ['simulate' => $input['simulate'] ?? null]
                );

                $payment->update([
                    'status' => $result->status,
                    'gateway_reference' => $result->reference,
                    'gateway_metadata' => $result->metadata,
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Payment gateway failed', [
                    'payment_id' => $payment->id,
                    'method' => $input['method'],
                ]);

                $payment->update([
                    'status' => 'failed',
                    'gateway_metadata' => ['reason' => 'gateway_error'],
                ]);
            }

            return $payment->fresh();
        });
    }

    private function findIdempotentPayment(Order $order, array $input): ?Payment
    {
        if (empty($input['idempotency_key'])) {
            return null;
        }

        return $order->payments()
            ->where('idempotency_key', $input['idempotency_key'])
            ->first();
    }
}

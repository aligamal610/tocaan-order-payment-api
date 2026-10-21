<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class OrderService
{
    public function create(int $userId, array $attributes): Order
    {
        $attributes['currency'] = strtoupper($attributes['currency'] ?? config('payments.default_currency'));

        return DB::transaction(function () use ($userId, $attributes): Order {
            $order = Order::query()->create([
                'user_id' => $userId,
                'customer_name' => $attributes['customer_name'],
                'customer_email' => $attributes['customer_email'],
                'customer_phone' => $attributes['customer_phone'] ?? null,
                'status' => $attributes['status'] ?? 'pending',
                'currency' => $attributes['currency'],
                'subtotal' => '0.00',
                'total' => '0.00',
            ]);

            return $this->update($order, $attributes);
        });
    }

    public function update(Order $order, array $attributes): Order
    {
        return DB::transaction(function () use ($order, $attributes): Order {
            $order->fill(Arr::except($attributes, ['items']));
            $order->save();

            if (isset($attributes['items'])) {
                $this->replaceItems($order, $attributes['items']);
            }

            return $order->fresh(['items', 'payments']);
        });
    }

    private function replaceItems(Order $order, array $items): void
    {
        $order->items()->delete();
        $subtotalInCents = 0;

        foreach ($items as $item) {
            $unitPriceInCents = $this->toCents((string) $item['unit_price']);
            $lineTotalInCents = $item['quantity'] * $unitPriceInCents;
            $subtotalInCents += $lineTotalInCents;

            $order->items()->create([
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $this->formatCents($unitPriceInCents),
                'line_total' => $this->formatCents($lineTotalInCents),
            ]);
        }

        $total = $this->formatCents($subtotalInCents);

        $order->update([
            'subtotal' => $total,
            'total' => $total,
        ]);
    }

    private function toCents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function formatCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}

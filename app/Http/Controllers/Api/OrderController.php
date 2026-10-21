<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListOrdersRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(ListOrdersRequest $request)
    {
        $orders = $request->user()
            ->orders()
            ->withCount('payments')
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status'))
            )
            ->latest()
            ->paginate($this->perPage($request));

        return OrderResource::collection($orders);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create($request->user()->id, $request->validated());

        return OrderResource::make($order)->response()->setStatusCode(201);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        $this->ensureOwnership($request, $order);

        return OrderResource::make($order->load('items', 'payments'));
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse|OrderResource
    {
        $this->ensureOwnership($request, $order);

        if ($order->payments()->exists()) {
            return response()->json(['message' => 'Paid orders cannot be edited.'], 409);
        }

        return OrderResource::make($this->orders->update($order, $request->validated()));
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        $this->ensureOwnership($request, $order);

        if ($order->payments()->exists()) {
            return response()->json(['message' => 'Orders with payments cannot be deleted.'], 409);
        }

        $orderId = $order->id;

        $deleted = $order->delete();

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'The order could not be deleted.',
                'errors' => (object) [],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully.',
            'data' => ['id' => $orderId],
        ]);
    }

    private function ensureOwnership(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }

    private function perPage(Request $request): int
    {
        return (int) $request->input('per_page', config('api.per_page', 15));
    }
}

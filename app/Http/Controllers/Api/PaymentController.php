<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListPaymentsRequest;
use App\Http\Requests\ProcessPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments)
    {
    }

    public function store(ProcessPaymentRequest $request, Order $order): JsonResponse
    {
        $this->ensureOrderOwnership($request, $order);
        $data = $request->validated();
        $isExistingPayment = isset($data['idempotency_key'])
            && $order->payments()->where('idempotency_key', $data['idempotency_key'])->exists();

        try {
            $payment = $this->payments->process($order, $data);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return PaymentResource::make($payment)
            ->response()
            ->setStatusCode($isExistingPayment ? 200 : 201);
    }

    public function index(ListPaymentsRequest $request)
    {
        $payments = Payment::query()
            ->whereHas('order', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with('order')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('method'), fn ($query) => $query->where('method', $request->string('method')))
            ->latest()
            ->paginate($this->perPage($request));

        return PaymentResource::collection($payments);
    }

    public function show(Request $request, Payment $payment): PaymentResource
    {
        abort_unless($payment->order()->where('user_id', $request->user()->id)->exists(), 404);

        return PaymentResource::make($payment->load('order'));
    }

    public function orderPayments(Request $request, Order $order)
    {
        $this->ensureOrderOwnership($request, $order);

        return PaymentResource::collection($order->payments()->latest()->get());
    }

    private function ensureOrderOwnership(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }

    private function perPage(Request $request): int
    {
        return (int) $request->input('per_page', config('api.per_page', 15));
    }
}

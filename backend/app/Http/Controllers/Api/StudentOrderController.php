<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\PayMongoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StudentOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items.menuItem:item_id,item_name,image_url', 'payment'])
            ->latest('order_date')
            ->latest('order_id')
            ->paginate(20);

        return response()->json($orders);
    }

    public function store(Request $request, PayMongoService $payMongo): JsonResponse
    {
        $validated = $request->validate([
            'pickup_time' => ['required', 'date_format:H:i'],
            'payment_method' => ['sometimes', 'string', 'in:paymongo,cash'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.item_id' => ['required', 'integer', 'distinct', 'exists:menu_items,item_id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $paymentMethod = $validated['payment_method'] ?? 'paymongo';
        $order = DB::transaction(function () use ($request, $validated, $paymentMethod): Order {
            $requestedItems = collect($validated['items'])->keyBy('item_id');
            $menuItems = MenuItem::query()
                ->with('inventory')
                ->whereIn('item_id', $requestedItems->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('item_id');

            if ($menuItems->count() !== $requestedItems->count()) {
                throw ValidationException::withMessages(['items' => 'One or more menu items are unavailable.']);
            }

            $total = 0;
            foreach ($requestedItems as $itemId => $requestedItem) {
                $menuItem = $menuItems->get($itemId);
                $inventory = Inventory::query()->where('item_id', $itemId)->lockForUpdate()->first();

                if (! $menuItem->availability || ! $inventory || $inventory->quantity < $requestedItem['quantity']) {
                    throw ValidationException::withMessages(['items' => "{$menuItem->item_name} is not available in the requested quantity."]);
                }

                $total += $this->priceFor($menuItem) * $requestedItem['quantity'];
            }

            $order = Order::create([
                'user_id' => $request->user()->id,
                'order_date' => today(),
                'pickup_time' => $validated['pickup_time'],
                'status' => 'pending',
                'total_amount' => $total,
            ]);

            foreach ($requestedItems as $itemId => $requestedItem) {
                $menuItem = $menuItems->get($itemId);
                $unitPrice = $this->priceFor($menuItem);
                $quantity = $requestedItem['quantity'];

                $order->items()->create([
                    'item_id' => $menuItem->item_id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $unitPrice * $quantity,
                ]);

                Inventory::query()->where('item_id', $itemId)->decrement('quantity', $quantity);
            }

            $order->payment()->create([
                'payment_provider' => $paymentMethod,
                'payment_method' => $paymentMethod === 'cash' ? 'cash' : null,
                'amount' => $total,
                'payment_status' => 'pending',
                'reference_number' => 'SB-'.str_pad((string) $order->order_id, 10, '0', STR_PAD_LEFT),
            ]);

            return $order;
        });

        if ($paymentMethod === 'cash') {
            return response()->json([
                'message' => 'Order saved. Pay cash at pickup; staff will confirm the payment before preparation.',
                'data' => $order->load(['items.menuItem:item_id,item_name', 'payment']),
            ], 201);
        }

        try {
            $session = $payMongo->createCheckoutSession($order);
        } catch (RuntimeException $exception) {
            DB::transaction(function () use ($order): void {
                $lockedOrder = Order::query()->with('items')->lockForUpdate()->findOrFail($order->order_id);

                foreach ($lockedOrder->items as $item) {
                    Inventory::query()->where('item_id', $item->item_id)->increment('quantity', $item->quantity);
                }

                $lockedOrder->update(['status' => 'cancelled']);
                $lockedOrder->payment()->update(['payment_status' => 'failed']);
            });
            Log::error('PayMongo Checkout Session creation failed.', [
                'order_id' => $order->order_id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $order->payment()->update([
            'provider_checkout_id' => $session['checkout_id'],
            'provider_payment_intent_id' => $session['payment_intent_id'],
            'checkout_url' => $session['checkout_url'],
        ]);

        return response()->json([
            'message' => 'Order created. Complete payment to send it to the canteen.',
            'checkout_url' => $session['checkout_url'],
            'data' => $order->load(['items.menuItem:item_id,item_name', 'payment']),
        ], 201);
    }

    public function paymentStatus(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return response()->json([
            'data' => $order->load('payment:payment_id,order_id,payment_provider,payment_status,paid_at,checkout_url,amount'),
        ]);
    }

    public function notifications(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->canteenNotifications()->latest()->paginate(30),
        ]);
    }

    public function markNotificationRead(Request $request, int $notificationId): JsonResponse
    {
        $notification = $request->user()->canteenNotifications()->whereKey($notificationId)->firstOrFail();
        $notification->update(['is_read' => true]);

        return response()->json(['data' => $notification]);
    }

    private function priceFor(MenuItem $item): float
    {
        $special = $item->specials()
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->latest('start_date')
            ->first();

        return (float) ($special?->special_price ?? $item->price);
    }
}

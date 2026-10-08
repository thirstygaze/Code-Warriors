<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CanteenNotification;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\StaffOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function orders(): JsonResponse
    {
        return response()->json([
            'data' => Order::query()
                ->with([
                    'student:id,name',
                    'items.menuItem:item_id,item_name',
                    'payment:payment_id,order_id,payment_provider,payment_status,payment_method,paid_at',
                ])
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereHas('payment', fn ($query) => $query
                    ->where('payment_status', 'paid')
                    ->orWhere(fn ($cash) => $cash->where('payment_provider', 'cash')->where('payment_status', 'pending')))
                ->orderBy('order_date')
                ->orderBy('pickup_time')
                ->paginate(30),
        ]);
    }

    public function confirmCashPayment(Request $request, Order $order): JsonResponse
    {
        DB::transaction(function () use ($request, $order): void {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->order_id);
            $payment = $lockedOrder->payment()->lockForUpdate()->first();

            if (! $payment
                || $payment->payment_provider !== 'cash'
                || $payment->payment_status !== 'pending'
                || in_array($lockedOrder->status, ['completed', 'cancelled'], true)) {
                abort(422, 'This order is not awaiting cash payment.');
            }

            $payment->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
            ]);

            StaffOrder::create([
                'staff_id' => $request->user()->id,
                'order_id' => $lockedOrder->order_id,
                'assigned_at' => now(),
                'action' => 'cash_payment_received',
            ]);

            CanteenNotification::create([
                'user_id' => $lockedOrder->user_id,
                'order_id' => $lockedOrder->order_id,
                'message' => "Cash payment for order #{$lockedOrder->order_id} was received. Your order can now be prepared.",
            ]);
        });

        return response()->json([
            'message' => 'Cash payment confirmed.',
            'data' => $order->fresh()->load(['items.menuItem:item_id,item_name', 'payment']),
        ]);
    }

    public function updateOrderStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['preparing', 'ready', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $allowedTransitions = [
            'pending' => ['preparing', 'cancelled'],
            'preparing' => ['ready', 'cancelled'],
            'ready' => ['completed', 'cancelled'],
        ];

        if (! in_array($validated['status'], $allowedTransitions[$order->status] ?? [], true)) {
            return response()->json(['message' => "An order cannot transition from {$order->status} to {$validated['status']}."], 422);
        }

        $paymentStatus = $order->payment()->value('payment_status');

        if ($validated['status'] !== 'cancelled' && $paymentStatus !== 'paid') {
            return response()->json(['message' => 'Only paid orders can be prepared.'], 422);
        }

        if ($validated['status'] === 'cancelled' && $paymentStatus === 'paid') {
            return response()->json(['message' => 'Refund paid orders in PayMongo before cancelling them.'], 422);
        }

        DB::transaction(function () use ($request, $order, $validated): void {
            if ($validated['status'] === 'cancelled') {
                foreach ($order->items as $item) {
                    Inventory::query()->where('item_id', $item->item_id)->increment('quantity', $item->quantity);
                }
            }

            $order->update(['status' => $validated['status']]);
            StaffOrder::create([
                'staff_id' => $request->user()->id,
                'order_id' => $order->order_id,
                'assigned_at' => now(),
                'action' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);
            CanteenNotification::create([
                'user_id' => $order->user_id,
                'order_id' => $order->order_id,
                'message' => "Your order #{$order->order_id} is now {$validated['status']}.",
            ]);
        });

        return response()->json(['data' => $order->fresh()->load('items.menuItem:item_id,item_name')]);
    }

    public function menu(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->menuItemsFor($request)
                ->with(['category:category_id,category_name', 'inventory', 'staff:user_id,stall_name'])
                ->orderBy('item_name')
                ->get(),
        ]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()->orderBy('category_name')->get(['category_id', 'category_name']),
        ]);
    }

    public function createMenuItem(Request $request): JsonResponse
    {
        $ownerStaffId = $request->user()->role === 'staff' ? $request->user()->id : null;
        if ($ownerStaffId !== null && blank($request->user()->staffProfile()->value('stall_name'))) {
            return response()->json(['message' => 'Ask an administrator to assign a stall to your staff account before adding menu items.'], 422);
        }

        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,category_id'],
            'item_name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'availability' => ['sometimes', 'boolean'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'quantity' => ['required', 'integer', 'min:0'],
            'reorder_level' => ['sometimes', 'integer', 'min:0'],
        ]);

        $item = DB::transaction(function () use ($validated, $ownerStaffId): MenuItem {
            $item = MenuItem::create([
                'category_id' => $validated['category_id'],
                'item_name' => $validated['item_name'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'availability' => $validated['availability'] ?? true,
                'image_url' => $validated['image_url'] ?? null,
                'owner_staff_id' => $ownerStaffId,
            ]);
            $item->inventory()->create([
                'quantity' => $validated['quantity'],
                'reorder_level' => $validated['reorder_level'] ?? 5,
            ]);

            return $item;
        });

        return response()->json(['data' => $item->load(['category', 'inventory', 'staff:user_id,stall_name'])], 201);
    }

    public function updateMenuItem(Request $request, MenuItem $menuItem): JsonResponse
    {
        abort_unless($request->user()->role === 'admin' || $menuItem->owner_staff_id === $request->user()->id, 404);

        $validated = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,category_id'],
            'item_name' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'numeric', 'min:0.01', 'max:99999999'],
            'availability' => ['sometimes', 'boolean'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ]);

        $menuItem->update($validated);

        return response()->json(['data' => $menuItem->fresh()->load(['category', 'inventory', 'staff:user_id,stall_name'])]);
    }

    public function inventory(Request $request): JsonResponse
    {
        return response()->json([
            'data' => Inventory::query()
                ->when($request->user()->role === 'staff', fn ($query) => $query->whereHas('menuItem', fn ($menuItems) => $menuItems->where('owner_staff_id', $request->user()->id)))
                ->with('menuItem:item_id,item_name,availability')
                ->orderBy('item_id')
                ->get(),
        ]);
    }

    public function updateInventory(Request $request, MenuItem $menuItem): JsonResponse
    {
        abort_unless($request->user()->role === 'admin' || $menuItem->owner_staff_id === $request->user()->id, 404);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
            'reorder_level' => ['sometimes', 'integer', 'min:0'],
        ]);

        $inventory = Inventory::updateOrCreate(
            ['item_id' => $menuItem->item_id],
            [
                'quantity' => $validated['quantity'],
                'reorder_level' => $validated['reorder_level'] ?? 5,
            ],
        );

        return response()->json(['data' => $inventory->load('menuItem:item_id,item_name')]);
    }

    private function menuItemsFor(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return MenuItem::query()
            ->when($request->user()->role === 'staff', fn ($query) => $query->where('owner_staff_id', $request->user()->id));
    }
}

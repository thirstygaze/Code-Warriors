<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = MenuItem::query()
            ->with(['category:category_id,category_name', 'inventory:item_id,quantity,reorder_level', 'staff:user_id,stall_name'])
            ->where('availability', true)
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->whereHas('category', fn ($category) => $category->where('category_name', $request->input('category')));
            })
            ->orderBy('category_id')
            ->orderBy('item_name')
            ->get()
            ->filter(fn (MenuItem $item) => ($item->inventory?->quantity ?? 0) > 0)
            ->values();

        return response()->json(['data' => $items]);
    }
}

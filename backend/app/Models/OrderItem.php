<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $primaryKey = 'order_item_id';

    protected $fillable = ['order_id', 'item_id', 'quantity', 'unit_price', 'subtotal'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'subtotal' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id', 'item_id');
    }
}

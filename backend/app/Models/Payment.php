<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'order_id',
        'payment_provider',
        'provider_checkout_id',
        'provider_payment_intent_id',
        'checkout_url',
        'reference_number',
        'payment_method',
        'amount',
        'payment_status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $primaryKey = 'order_id';

    protected $fillable = ['user_id', 'order_date', 'pickup_time', 'status', 'total_amount'];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'total_amount' => 'decimal:2'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'order_id', 'order_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(CanteenNotification::class, 'order_id', 'order_id');
    }
}

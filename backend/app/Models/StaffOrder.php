<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffOrder extends Model
{
    protected $primaryKey = 'staff_order_id';

    protected $fillable = ['staff_id', 'order_id', 'assigned_at', 'action', 'notes'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }
}

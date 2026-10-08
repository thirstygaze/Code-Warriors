<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuSpecial extends Model
{
    protected $primaryKey = 'special_id';

    protected $fillable = ['item_id', 'special_price', 'start_date', 'end_date', 'created_by'];

    protected function casts(): array
    {
        return ['special_price' => 'decimal:2', 'start_date' => 'datetime', 'end_date' => 'datetime'];
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id', 'item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

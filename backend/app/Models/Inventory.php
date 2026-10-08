<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    protected $table = 'inventory';

    protected $primaryKey = 'inventory_id';

    protected $fillable = ['item_id', 'quantity', 'reorder_level'];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'item_id', 'item_id');
    }
}

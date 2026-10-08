<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MenuItem extends Model
{
    protected $primaryKey = 'item_id';

    protected $fillable = ['category_id', 'item_name', 'description', 'price', 'availability', 'image_url', 'owner_staff_id'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'availability' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'owner_staff_id', 'user_id');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class, 'item_id', 'item_id');
    }

    public function specials(): HasMany
    {
        return $this->hasMany(MenuSpecial::class, 'item_id', 'item_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'item_id', 'item_id');
    }
}

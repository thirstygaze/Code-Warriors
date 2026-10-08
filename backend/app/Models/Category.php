<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $primaryKey = 'category_id';

    protected $fillable = ['category_name', 'description'];

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'category_id', 'category_id');
    }
}

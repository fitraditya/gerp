<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['sku', 'name', 'description', 'brand_id', 'price', 'cost_price', 'tier', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function inventoryTransfers()
    {
        return $this->hasMany(InventoryTransfer::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

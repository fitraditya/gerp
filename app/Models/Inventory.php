<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\WarehouseScope;

class Inventory extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted()
    {
        static::addGlobalScope(new WarehouseScope());
    }

    protected $fillable = ['product_id', 'warehouse_id', 'quantity', 'quantity_reserved'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'quantity_reserved' => 'integer',
            'quantity_available' => 'integer',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function audits()
    {
        return $this->hasMany(InventoryAudit::class);
    }

    public function isNegative()
    {
        return $this->quantity < 0;
    }

    public function getAvailableAttribute()
    {
        return $this->quantity - $this->quantity_reserved;
    }
}

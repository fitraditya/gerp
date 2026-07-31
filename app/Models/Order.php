<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\WarehouseScope;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_number',
        'idempotency_key',
        'warehouse_id',
        'cashier_id',
        'discount_id',
        'subtotal',
        'discount_amount',
        'total',
        'cogs_total',
        'gross_profit',
        'payment_method',
        'status',
        'completed_at',
        'items',
        'has_negative_stock_flag',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'cogs_total' => 'decimal:2',
            'gross_profit' => 'decimal:2',
            'items' => 'json',
            'has_negative_stock_flag' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted()
    {
        static::addGlobalScope(new WarehouseScope());
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function ledgerEntries()
    {
        return $this->morphMany(Ledger::class, 'transactionable');
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('completed_at');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\WarehouseScope;

class SalesReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'return_number',
        'order_id',
        'warehouse_id',
        'created_by',
        'reason',
        'items',
        'refund_amount',
        'cogs_reversal',
        'refund_method',
        'status',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'json',
            'refund_amount' => 'decimal:2',
            'cogs_reversal' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted()
    {
        static::addGlobalScope(new WarehouseScope());
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ledgerEntries()
    {
        return $this->morphMany(Ledger::class, 'transactionable');
    }
}

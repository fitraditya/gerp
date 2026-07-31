<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\WarehouseScope;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'warehouse_id',
        'created_by',
        'status',
        'subtotal',
        'total',
        'received_total',
        'amount_paid',
        'balance_due',
        'items',
        'ordered_at',
        'received_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'received_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'items' => 'json',
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted()
    {
        static::addGlobalScope(new WarehouseScope());
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
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

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['ordered', 'partially_received']);
    }
}

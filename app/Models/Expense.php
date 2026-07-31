<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Scopes\WarehouseScope;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_number',
        'warehouse_id',
        'created_by',
        'category',
        'description',
        'payee_name',
        'amount',
        'payment_method',
        'fund_pool',
        'status',
        'verified_at',
        'verified_by',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function ledgerEntries()
    {
        return $this->morphMany(Ledger::class, 'transactionable');
    }

    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'recorded');
    }
}

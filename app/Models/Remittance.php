<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Remittance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'remittance_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'submitted_by',
        'amount',
        'status',
        'verified_at',
        'verified_by',
        'completed_at',
        'rejection_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // No WarehouseScope: this table has from_warehouse_id/to_warehouse_id, not a
    // single warehouse_id column, so the generic scope's `where('warehouse_id', ...)`
    // would fatal on every query for non-Admin users. Row visibility is enforced by
    // RemittancePolicy + RemittanceResource::getEloquentQuery() instead.

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }
}

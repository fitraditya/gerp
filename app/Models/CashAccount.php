<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'holder_name',
        'branch_id',
        'description',
        'balance',
        'account_type',
        'counts_as_cash',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'counts_as_cash' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Cash actually on hand — excludes fund-pool/revenue running totals. */
    public function scopeCash($query)
    {
        return $query->where('counts_as_cash', true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashAccount extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Chart-of-accounts classification. `expense` covers both fund pools (HR/OPS/DEV/DISC)
     * and COGS_EXPENSE — anything that reduces net income, as opposed to `equity` which
     * is reserved for actual owner/org capital (none seeded yet).
     */
    public const ACCOUNT_TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

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

    /**
     * GAAP normal-balance side for this account_type — used by LedgerReportService to
     * present balances with the conventional sign instead of this schema's internal
     * "from loses / to gains" bookkeeping (see LedgerService::post() docblock).
     */
    public function normalBalance(): string
    {
        return in_array($this->account_type, ['asset', 'expense'], true) ? 'debit' : 'credit';
    }
}

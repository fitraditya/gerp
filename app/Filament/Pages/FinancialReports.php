<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Services\LedgerReportService;
use BackedEnum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Chart-of-Accounts reporting: trial balance (live, all-time) + profit & loss
 * (period-scoped). Admin/Manager only — same boundary as ErpDashboard's margin
 * tiles (RBAC.md Dashboard Widget Visibility), since this exposes account-level
 * cost/expense detail a Supervisor shouldn't see.
 */
class FinancialReports extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.financial-reports';

    // Reuses ErpDashboard's confirmed-working icon rather than guessing at an
    // unverified Heroicon case (no vendor/ present in this checkout to check against).
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    public static function getNavigationLabel(): string
    {
        return __('financial_reports.nav_label');
    }

    public ?array $data = [];

    public array $trialBalance = [];

    public array $profitAndLoss = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Manager']) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'branch_id' => null,
        ]);

        $this->applyFilters();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('period_start')->label(__('financial_reports.filter.period_start'))->required()->live(),
                DatePicker::make('period_end')->label(__('financial_reports.filter.period_end'))->required()->live(),
                Select::make('branch_id')
                    ->label(__('financial_reports.filter.branch'))
                    ->options(Branch::query()->pluck('name', 'id'))
                    ->placeholder(__('financial_reports.filter.all_branches'))
                    ->live()
                    ->searchable(),
            ])
            ->statePath('data')
            ->columns(3);
    }

    public function applyFilters(): void
    {
        $state = $this->form->getState();
        $service = app(LedgerReportService::class);

        $warehouseId = $state['branch_id'] ?? null
            ? Branch::find($state['branch_id'])?->warehouse_id
            : null;

        $this->trialBalance = $service->trialBalance()->toArray();
        $this->profitAndLoss = $service->profitAndLoss(
            \Carbon\Carbon::parse($state['period_start'])->startOfDay(),
            \Carbon\Carbon::parse($state['period_end'])->endOfDay(),
            $warehouseId,
        );
    }

    public function updated($name): void
    {
        if (str_starts_with($name, 'data.')) {
            $this->applyFilters();
        }
    }
}

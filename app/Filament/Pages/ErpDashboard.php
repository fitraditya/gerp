<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Services\DashboardService;
use BackedEnum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ErpDashboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.erp-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    public static function getNavigationLabel(): string
    {
        return __('dashboard.nav_label');
    }

    public ?array $data = [];

    public array $summary = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Admin', 'Manager', 'Supervisor']) ?? false;
    }

    public function mount(): void
    {
        $user = auth()->user();

        $this->form->fill([
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'branch_id' => (!$user->hasAnyRole(['Admin', 'Manager']) && $user->warehouse_id)
                ? (Branch::where('warehouse_id', $user->warehouse_id)->first()?->id)
                : null,
        ]);

        $this->applyFilters();
    }

    public function form(Schema $schema): Schema
    {
        $user = auth()->user();
        $branchLockedForRole = !$user->hasAnyRole(['Admin', 'Manager']);

        return $schema
            ->components([
                DatePicker::make('period_start')->label(__('dashboard.filter.period_start'))->required()->live(),
                DatePicker::make('period_end')->label(__('dashboard.filter.period_end'))->required()->live(),
                Select::make('branch_id')
                    ->label(__('dashboard.filter.branch'))
                    ->options(Branch::query()->pluck('name', 'id'))
                    ->placeholder(__('dashboard.filter.all_branches'))
                    ->disabled($branchLockedForRole)
                    ->live()
                    ->searchable(),
            ])
            ->statePath('data')
            ->columns(3);
    }

    public function applyFilters(): void
    {
        $state = $this->form->getState();

        $this->summary = app(DashboardService::class)->summary(
            \Carbon\Carbon::parse($state['period_start'])->startOfDay(),
            \Carbon\Carbon::parse($state['period_end'])->endOfDay(),
            $state['branch_id'] ?? null,
        );
    }

    public function updated($name): void
    {
        if (str_starts_with($name, 'data.')) {
            $this->applyFilters();
        }
    }
}

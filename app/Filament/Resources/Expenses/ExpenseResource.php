<?php

namespace App\Filament\Resources\Expenses;

use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('expenses.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('expenses.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('expenses.plural');
    }

    /** Edits/deletes go through LedgerService only; entries are audit-only once posted. */
    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    private static function fundPoolOptions(): array
    {
        return [
            'HR' => __('expenses.fields.fund_pool_hr'),
            'OPS' => __('expenses.fields.fund_pool_ops'),
            'DEV' => __('expenses.fields.fund_pool_dev'),
            'DISC' => __('expenses.fields.fund_pool_disc'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('warehouse_id')->label(__('expenses.fields.warehouse'))->options(Warehouse::query()->pluck('name', 'id'))->searchable()->required(),
                Select::make('source_cash_account_id')
                    ->label(__('expenses.fields.source_cash_account'))
                    ->options(fn () => CashAccount::cash()->get()->mapWithKeys(fn ($a) => [$a->id => $a->name.($a->holder_name ? " ({$a->holder_name})" : '')]))
                    ->searchable()
                    ->required(),
                TextInput::make('category')->label(__('expenses.fields.category'))->required()->maxLength(255),
                TextInput::make('description')->label(__('expenses.fields.description'))->required()->maxLength(255),
                TextInput::make('payee_name')->label(__('expenses.fields.payee_name'))->maxLength(255),
                TextInput::make('amount')->label(__('expenses.fields.amount'))->numeric()->required()->prefix('Rp'),
                Select::make('fund_pool')->label(__('expenses.fields.fund_pool'))->options(self::fundPoolOptions())->required(),
                Select::make('payment_method')
                    ->label(__('expenses.fields.payment_method'))
                    ->options(['cash' => __('expenses.fields.payment_method_cash'), 'qris' => __('expenses.fields.payment_method_qris')])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_number')->label(__('expenses.fields.reference_number'))->searchable(),
                TextColumn::make('warehouse.name')->label(__('expenses.fields.warehouse')),
                TextColumn::make('category')->label(__('expenses.fields.category')),
                TextColumn::make('description')->label(__('expenses.fields.description')),
                TextColumn::make('amount')->label(__('expenses.fields.amount'))->money('IDR')->sortable(),
                TextColumn::make('fund_pool')->label(__('expenses.fields.fund_pool'))->badge(),
                TextColumn::make('createdBy.name')->label(__('expenses.fields.recorded_by')),
                TextColumn::make('created_at')->label(__('expenses.fields.created_at'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('fund_pool')->label(__('expenses.fields.fund_pool'))->options(self::fundPoolOptions()),
                SelectFilter::make('warehouse_id')->label(__('expenses.fields.warehouse'))->relationship('warehouse', 'name'),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExpenses::route('/'),
        ];
    }
}

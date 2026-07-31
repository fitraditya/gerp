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

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    /** Edits/deletes go through LedgerService only; entries are audit-only once posted. */
    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('warehouse_id')->label('Warehouse')->options(Warehouse::query()->pluck('name', 'id'))->searchable()->required(),
                Select::make('source_cash_account_id')
                    ->label('Cash Source')
                    ->options(fn () => CashAccount::cash()->get()->mapWithKeys(fn ($a) => [$a->id => $a->name.($a->holder_name ? " ({$a->holder_name})" : '')]))
                    ->searchable()
                    ->required(),
                TextInput::make('category')->required()->maxLength(255),
                TextInput::make('description')->required()->maxLength(255),
                TextInput::make('payee_name')->label('Paid To')->maxLength(255),
                TextInput::make('amount')->numeric()->required()->prefix('Rp'),
                Select::make('fund_pool')->options(['HR' => 'HR', 'OPS' => 'Operations', 'DEV' => 'Development', 'DISC' => 'Discretionary'])->required(),
                Select::make('payment_method')->options(['cash' => 'Cash', 'qris' => 'QRIS'])->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_number')->searchable(),
                TextColumn::make('warehouse.name')->label('Warehouse'),
                TextColumn::make('category'),
                TextColumn::make('description'),
                TextColumn::make('amount')->money('IDR')->sortable(),
                TextColumn::make('fund_pool')->badge(),
                TextColumn::make('createdBy.name')->label('Recorded By'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('fund_pool')->options(['HR' => 'HR', 'OPS' => 'Operations', 'DEV' => 'Development', 'DISC' => 'Discretionary']),
                SelectFilter::make('warehouse_id')->label('Warehouse')->relationship('warehouse', 'name'),
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

<?php

namespace App\Filament\Resources\CashAccounts;

use App\Filament\Resources\CashAccounts\Pages\ManageCashAccounts;
use App\Models\CashAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CashAccountResource extends Resource
{
    protected static ?string $model = CashAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    /**
     * Read-only per RFC Module Breakdown ("CashAccountResource (read)") — balances
     * are exclusively mutated by LedgerService::post(), never edited by hand here.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('holder_name')->label('Holder')->searchable(),
                TextColumn::make('branch.name')->label('Branch'),
                TextColumn::make('account_type')->badge(),
                IconColumn::make('counts_as_cash')->label('Cash?')->boolean(),
                TextColumn::make('balance')->money('IDR')->sortable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')->label('Branch')->relationship('branch', 'name'),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCashAccounts::route('/'),
        ];
    }
}

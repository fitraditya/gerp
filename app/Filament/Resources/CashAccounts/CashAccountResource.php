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

    public static function getNavigationGroup(): ?string
    {
        return __('nav.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('cash_accounts.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('cash_accounts.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('cash_accounts.plural');
    }

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
                TextColumn::make('code')->label(__('cash_accounts.fields.code'))->searchable()->sortable(),
                TextColumn::make('name')->label(__('cash_accounts.fields.name'))->searchable(),
                TextColumn::make('holder_name')->label(__('cash_accounts.fields.holder_name'))->searchable(),
                TextColumn::make('branch.name')->label(__('cash_accounts.fields.branch')),
                TextColumn::make('account_type')->label(__('cash_accounts.fields.account_type'))->badge(),
                IconColumn::make('counts_as_cash')->label(__('cash_accounts.fields.counts_as_cash'))->boolean(),
                TextColumn::make('balance')->label(__('cash_accounts.fields.balance'))->money('IDR')->sortable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')->label(__('cash_accounts.fields.branch'))->relationship('branch', 'name'),
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

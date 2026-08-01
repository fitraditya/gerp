<?php

namespace App\Filament\Resources\SalesReturns;

use App\Filament\Resources\SalesReturns\Pages\ManageSalesReturns;
use App\Models\SalesReturn;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Read-only order log — same convention as OrderResource: returns are only ever
 * created via SalesReturnService (Order's "Process Return" action), never a raw form.
 */
class SalesReturnResource extends Resource
{
    protected static ?string $model = SalesReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $modelLabel = 'Return';

    public static function canCreate(): bool
    {
        return false;
    }

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
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('return_number')->searchable(),
                TextColumn::make('order.order_number')->label('Order')->searchable(),
                TextColumn::make('warehouse.name')->label('Warehouse')->sortable(),
                TextColumn::make('refund_amount')->money('IDR')->sortable(),
                TextColumn::make('refund_method')->badge(),
                TextColumn::make('reason')->limit(50),
                TextColumn::make('processed_at')->dateTime()->sortable(),
            ])
            ->defaultSort('processed_at', 'desc')
            ->filters([
                SelectFilter::make('warehouse_id')->label('Warehouse')->relationship('warehouse', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSalesReturns::route('/'),
        ];
    }
}

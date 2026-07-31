<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $modelLabel = 'Order Log';

    /** Orders are only ever created via CheckoutService (POS/API), never a raw form here. */
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

    /** Read-only via ViewAction's disabledSchema() — no create/edit path exists for Order. */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('order_number'),
            TextInput::make('subtotal')->prefix('Rp'),
            TextInput::make('discount_amount')->prefix('Rp'),
            TextInput::make('total')->prefix('Rp'),
            TextInput::make('payment_method'),
            TextInput::make('has_negative_stock_flag')->label('Negative Stock Flag'),
            TextInput::make('completed_at'),
            Repeater::make('items')
                ->schema([
                    TextInput::make('product_id'),
                    TextInput::make('quantity'),
                    TextInput::make('unit_price')->prefix('Rp'),
                    TextInput::make('subtotal')->prefix('Rp'),
                ])
                ->columns(4)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->searchable(),
                TextColumn::make('warehouse.name')->label('Warehouse')->sortable(),
                TextColumn::make('cashier.name')->label('Cashier'),
                TextColumn::make('discount.code')->label('Discount'),
                TextColumn::make('subtotal')->money('IDR'),
                TextColumn::make('discount_amount')->money('IDR'),
                TextColumn::make('total')->money('IDR')->sortable(),
                TextColumn::make('payment_method')->badge(),
                IconColumn::make('has_negative_stock_flag')->label('Neg. Stock')->boolean(),
                TextColumn::make('completed_at')->dateTime()->sortable(),
            ])
            ->defaultSort('completed_at', 'desc')
            ->filters([
                SelectFilter::make('warehouse_id')->label('Warehouse')->relationship('warehouse', 'name'),
                SelectFilter::make('payment_method')->options(['cash' => 'Cash', 'qris' => 'QRIS']),
                TernaryFilter::make('has_negative_stock_flag')->label('Negative Stock Flag'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrders::route('/'),
        ];
    }
}

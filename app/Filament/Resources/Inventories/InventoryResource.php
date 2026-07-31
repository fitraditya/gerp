<?php

namespace App\Filament\Resources\Inventories;

use App\Filament\Resources\Inventories\Pages\ManageInventories;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    /** Mutations go exclusively through InventoryService (receiveStock/transfer), never a raw edit form. */
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
                TextColumn::make('product.sku')->label('SKU')->searchable(),
                TextColumn::make('product.name')->label('Product')->searchable(),
                TextColumn::make('warehouse.name')->label('Warehouse')->sortable(),
                TextColumn::make('quantity')
                    ->sortable()
                    ->color(fn (Inventory $record) => $record->quantity < 0 ? 'danger' : null)
                    ->weight(fn (Inventory $record) => $record->quantity < 0 ? 'bold' : null),
                TextColumn::make('quantity_reserved')->label('Reserved'),
                TextColumn::make('available')->label('Available')->state(fn (Inventory $record) => $record->quantity - $record->quantity_reserved),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')->label('Warehouse')->relationship('warehouse', 'name'),
            ])
            ->recordActions([
                self::transferAction(),
            ])
            ->toolbarActions([])
            ->headerActions([
                self::receiveStockAction(),
            ]);
    }

    private static function receiveStockAction(): Action
    {
        return Action::make('receiveStock')
            ->label('Receive Stock')
            ->visible(fn () => auth()->user()?->hasAnyRole(['Admin', 'Manager']))
            ->schema([
                Select::make('product_id')->label('Product')->options(Product::query()->pluck('name', 'id'))->searchable()->required(),
                Select::make('warehouse_id')->label('Warehouse')->options(Warehouse::query()->pluck('name', 'id'))->searchable()->required(),
                TextInput::make('quantity')->numeric()->minValue(1)->required(),
            ])
            ->action(function (array $data) {
                $product = Product::findOrFail($data['product_id']);
                app(InventoryService::class)->receiveStock($product, (int) $data['warehouse_id'], (int) $data['quantity'], auth()->id());

                Notification::make()->title('Stock received')->success()->send();
            });
    }

    private static function transferAction(): Action
    {
        return Action::make('transfer')
            ->label('Transfer')
            ->visible(fn () => auth()->user()?->hasAnyRole(['Admin', 'Manager']))
            ->schema([
                Select::make('to_warehouse_id')
                    ->label('Destination Warehouse')
                    ->options(fn (Inventory $record) => Warehouse::query()->where('id', '!=', $record->warehouse_id)->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('quantity')->numeric()->minValue(1)->required(),
            ])
            ->action(function (Inventory $record, array $data) {
                try {
                    app(InventoryService::class)->transfer(
                        $record->product,
                        $record->warehouse_id,
                        (int) $data['to_warehouse_id'],
                        (int) $data['quantity'],
                        auth()->id(),
                    );
                    Notification::make()->title('Transfer completed')->success()->send();
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInventories::route('/'),
        ];
    }
}

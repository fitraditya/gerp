<?php

namespace App\Filament\Resources\Inventories;

use App\Filament\Resources\Inventories\Pages\ManageInventories;
use App\Models\CashAccount;
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

    public static function getNavigationGroup(): ?string
    {
        return __('nav.inventory');
    }

    public static function getNavigationLabel(): string
    {
        return __('inventories.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('inventories.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventories.plural');
    }

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
                TextColumn::make('product.sku')->label(__('inventories.fields.sku'))->searchable(),
                TextColumn::make('product.name')->label(__('inventories.fields.product'))->searchable(),
                TextColumn::make('warehouse.name')->label(__('inventories.fields.warehouse'))->sortable(),
                TextColumn::make('quantity')
                    ->label(__('inventories.fields.quantity'))
                    ->sortable()
                    ->color(fn (Inventory $record) => $record->quantity < 0 ? 'danger' : null)
                    ->weight(fn (Inventory $record) => $record->quantity < 0 ? 'bold' : null),
                TextColumn::make('quantity_reserved')->label(__('inventories.fields.reserved')),
                TextColumn::make('available')->label(__('inventories.fields.available'))->state(fn (Inventory $record) => $record->quantity - $record->quantity_reserved),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')->label(__('inventories.fields.warehouse'))->relationship('warehouse', 'name'),
            ])
            ->recordActions([
                self::transferAction(),
            ])
            ->toolbarActions([])
            ->headerActions([
                self::receiveStockAction(),
                Action::make('exportCsv')
                    ->label(__('inventories.export_csv'))
                    ->url(fn () => route('exports.inventory'))
                    ->openUrlInNewTab(),
            ]);
    }

    private static function receiveStockAction(): Action
    {
        return Action::make('receiveStock')
            ->label(__('inventories.receive_stock.action'))
            ->visible(fn () => auth()->user()?->hasAnyRole(['Admin', 'Manager']))
            ->schema([
                Select::make('product_id')->label(__('inventories.fields.product'))->options(Product::query()->pluck('name', 'id'))->searchable()->required(),
                Select::make('warehouse_id')->label(__('inventories.fields.warehouse'))->options(Warehouse::query()->pluck('name', 'id'))->searchable()->required(),
                TextInput::make('quantity')->label(__('inventories.receive_stock.quantity'))->numeric()->minValue(1)->required(),
                Select::make('funding_source_code')
                    ->label(__('inventories.receive_stock.funding_source'))
                    ->helperText(__('inventories.receive_stock.funding_source_help'))
                    ->options(CashAccount::active()->cash()->pluck('name', 'code'))
                    ->searchable(),
            ])
            ->action(function (array $data) {
                $product = Product::findOrFail($data['product_id']);
                app(InventoryService::class)->receiveStock(
                    $product,
                    (int) $data['warehouse_id'],
                    (int) $data['quantity'],
                    auth()->id(),
                    fundingSource: $data['funding_source_code'] ?? null,
                );

                Notification::make()->title(__('inventories.receive_stock.notification'))->success()->send();
            });
    }

    private static function transferAction(): Action
    {
        return Action::make('transfer')
            ->label(__('inventories.transfer.action'))
            ->visible(fn () => auth()->user()?->hasAnyRole(['Admin', 'Manager']))
            ->schema([
                Select::make('to_warehouse_id')
                    ->label(__('inventories.transfer.destination_warehouse'))
                    ->options(fn (Inventory $record) => Warehouse::query()->where('id', '!=', $record->warehouse_id)->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('quantity')->label(__('inventories.transfer.quantity'))->numeric()->minValue(1)->required(),
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
                    Notification::make()->title(__('inventories.transfer.notification'))->success()->send();
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

<?php

namespace App\Filament\Resources\PurchaseOrders;

use App\Filament\Resources\PurchaseOrders\Pages\ManagePurchaseOrders;
use App\Models\CashAccount;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PurchaseOrderService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Mutations go exclusively through PurchaseOrderService (create/receive/recordPayment/
 * cancel), same "never a raw edit form" convention as OrderResource/InventoryResource.
 */
class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
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
                TextColumn::make('po_number')->searchable(),
                TextColumn::make('supplier.name')->label('Supplier')->searchable(),
                TextColumn::make('warehouse.name')->label('Warehouse'),
                TextColumn::make('status')->badge(),
                TextColumn::make('total')->money('IDR')->label('Ordered Value'),
                TextColumn::make('received_total')->money('IDR')->label('Received Value'),
                TextColumn::make('balance_due')->money('IDR')->label('Balance Due'),
                TextColumn::make('ordered_at')->dateTime()->sortable(),
                TextColumn::make('received_at')->dateTime()->sortable(),
            ])
            ->defaultSort('ordered_at', 'desc')
            ->filters([
                SelectFilter::make('warehouse_id')->label('Warehouse')->relationship('warehouse', 'name'),
                SelectFilter::make('status')->options([
                    'ordered' => 'Ordered',
                    'partially_received' => 'Partially Received',
                    'received' => 'Received',
                    'cancelled' => 'Cancelled',
                ]),
            ])
            ->recordActions([
                self::receiveAction(),
                self::recordPaymentAction(),
                self::cancelAction(),
            ])
            ->toolbarActions([])
            ->headerActions([
                self::createPurchaseOrderAction(),
            ]);
    }

    private static function createPurchaseOrderAction(): Action
    {
        return Action::make('createPurchaseOrder')
            ->label('New Purchase Order')
            ->visible(fn () => auth()->user()?->hasAnyRole(['Admin', 'Manager']))
            ->schema([
                Select::make('supplier_id')->label('Supplier')->options(Supplier::active()->pluck('name', 'id'))->searchable()->required(),
                Select::make('warehouse_id')->label('Warehouse')->options(Warehouse::query()->pluck('name', 'id'))->searchable()->required(),
                Repeater::make('items')
                    ->schema([
                        Select::make('product_id')->label('Product')->options(Product::query()->pluck('name', 'id'))->searchable()->required(),
                        TextInput::make('quantity')->numeric()->minValue(1)->required(),
                        TextInput::make('unit_cost')->numeric()->minValue(0)->prefix('Rp')->required(),
                    ])
                    ->columns(3)
                    ->minItems(1)
                    ->required(),
                Textarea::make('notes')->columnSpanFull(),
            ])
            ->action(function (array $data) {
                try {
                    app(PurchaseOrderService::class)->create(
                        (int) $data['supplier_id'],
                        (int) $data['warehouse_id'],
                        $data['items'],
                        auth()->id(),
                        $data['notes'] ?? null,
                    );
                    Notification::make()->title('Purchase order created')->success()->send();
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    private static function receiveAction(): Action
    {
        return Action::make('receive')
            ->label('Receive')
            ->visible(fn (PurchaseOrder $record) => auth()->user()?->hasAnyRole(['Admin', 'Manager'])
                && in_array($record->status, ['ordered', 'partially_received'], true))
            ->schema([
                Repeater::make('items')
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->options(fn (PurchaseOrder $record) => collect($record->items)
                                ->mapWithKeys(fn ($line) => [$line['product_id'] => Product::find($line['product_id'])?->name.' ('.($line['quantity_ordered'] - $line['quantity_received']).' outstanding)']))
                            ->required(),
                        TextInput::make('quantity')->numeric()->minValue(1)->required(),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->required(),
            ])
            ->action(function (PurchaseOrder $record, array $data) {
                try {
                    app(PurchaseOrderService::class)->receive($record, $data['items'], auth()->id());
                    Notification::make()->title('Stock received against purchase order')->success()->send();
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    private static function recordPaymentAction(): Action
    {
        return Action::make('recordPayment')
            ->label('Record Payment')
            ->visible(fn (PurchaseOrder $record) => auth()->user()?->hasAnyRole(['Admin', 'Manager'])
                && (float) $record->balance_due > 0)
            ->schema([
                Select::make('cash_account_code')
                    ->label('Paid From')
                    ->options(CashAccount::active()->cash()->pluck('name', 'code'))
                    ->searchable()
                    ->required(),
                TextInput::make('amount')->numeric()->minValue(0.01)->prefix('Rp')->required(),
            ])
            ->action(function (PurchaseOrder $record, array $data) {
                try {
                    app(PurchaseOrderService::class)->recordPayment(
                        $record,
                        $data['cash_account_code'],
                        (float) $data['amount'],
                        auth()->id(),
                    );
                    Notification::make()->title('Payment recorded')->success()->send();
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    private static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->requiresConfirmation()
            ->visible(fn (PurchaseOrder $record) => auth()->user()?->hasAnyRole(['Admin', 'Manager'])
                && $record->status === 'ordered')
            ->action(function (PurchaseOrder $record) {
                try {
                    app(PurchaseOrderService::class)->cancel($record);
                    Notification::make()->title('Purchase order cancelled')->success()->send();
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePurchaseOrders::route('/'),
        ];
    }
}

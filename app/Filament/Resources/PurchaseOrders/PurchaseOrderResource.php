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

    public static function getNavigationGroup(): ?string
    {
        return __('nav.inventory');
    }

    public static function getNavigationLabel(): string
    {
        return __('purchase_orders.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('purchase_orders.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('purchase_orders.plural');
    }

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
                TextColumn::make('po_number')->label(__('purchase_orders.fields.po_number'))->searchable(),
                TextColumn::make('supplier.name')->label(__('purchase_orders.fields.supplier'))->searchable(),
                TextColumn::make('warehouse.name')->label(__('purchase_orders.fields.warehouse')),
                TextColumn::make('status')->label(__('purchase_orders.fields.status'))->badge(),
                TextColumn::make('total')->money('IDR')->label(__('purchase_orders.fields.total')),
                TextColumn::make('received_total')->money('IDR')->label(__('purchase_orders.fields.received_total')),
                TextColumn::make('balance_due')->money('IDR')->label(__('purchase_orders.fields.balance_due')),
                TextColumn::make('ordered_at')->label(__('purchase_orders.fields.ordered_at'))->dateTime()->sortable(),
                TextColumn::make('received_at')->label(__('purchase_orders.fields.received_at'))->dateTime()->sortable(),
            ])
            ->defaultSort('ordered_at', 'desc')
            ->filters([
                SelectFilter::make('warehouse_id')->label(__('purchase_orders.fields.warehouse'))->relationship('warehouse', 'name'),
                SelectFilter::make('status')->options([
                    'ordered' => __('purchase_orders.fields.status_ordered'),
                    'partially_received' => __('purchase_orders.fields.status_partially_received'),
                    'received' => __('purchase_orders.fields.status_received'),
                    'cancelled' => __('purchase_orders.fields.status_cancelled'),
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
            ->label(__('purchase_orders.create.action'))
            ->visible(fn () => auth()->user()?->hasAnyRole(['Admin', 'Manager']))
            ->schema([
                Select::make('supplier_id')->label(__('purchase_orders.fields.supplier'))->options(Supplier::active()->pluck('name', 'id'))->searchable()->required(),
                Select::make('warehouse_id')->label(__('purchase_orders.fields.warehouse'))->options(Warehouse::query()->pluck('name', 'id'))->searchable()->required(),
                Repeater::make('items')
                    ->schema([
                        Select::make('product_id')->label(__('purchase_orders.fields.product'))->options(Product::query()->pluck('name', 'id'))->searchable()->required(),
                        TextInput::make('quantity')->label(__('purchase_orders.fields.quantity'))->numeric()->minValue(1)->required(),
                        TextInput::make('unit_cost')->label(__('purchase_orders.fields.unit_cost'))->numeric()->minValue(0)->prefix('Rp')->required(),
                    ])
                    ->columns(3)
                    ->minItems(1)
                    ->required(),
                Textarea::make('notes')->label(__('purchase_orders.fields.notes'))->columnSpanFull(),
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
                    Notification::make()->title(__('purchase_orders.create.notification'))->success()->send();
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    private static function receiveAction(): Action
    {
        return Action::make('receive')
            ->label(__('purchase_orders.receive.action'))
            ->visible(fn (PurchaseOrder $record) => auth()->user()?->hasAnyRole(['Admin', 'Manager'])
                && in_array($record->status, ['ordered', 'partially_received'], true))
            ->schema([
                Repeater::make('items')
                    ->schema([
                        Select::make('product_id')
                            ->label(__('purchase_orders.fields.product'))
                            ->options(fn (PurchaseOrder $record) => collect($record->items)
                                ->mapWithKeys(fn ($line) => [$line['product_id'] => Product::find($line['product_id'])?->name.' ('.($line['quantity_ordered'] - $line['quantity_received']).' outstanding)']))
                            ->required(),
                        TextInput::make('quantity')->label(__('purchase_orders.fields.quantity'))->numeric()->minValue(1)->required(),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->required(),
            ])
            ->action(function (PurchaseOrder $record, array $data) {
                try {
                    app(PurchaseOrderService::class)->receive($record, $data['items'], auth()->id());
                    Notification::make()->title(__('purchase_orders.receive.notification'))->success()->send();
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    private static function recordPaymentAction(): Action
    {
        return Action::make('recordPayment')
            ->label(__('purchase_orders.record_payment.action'))
            ->visible(fn (PurchaseOrder $record) => auth()->user()?->hasAnyRole(['Admin', 'Manager'])
                && (float) $record->balance_due > 0)
            ->schema([
                Select::make('cash_account_code')
                    ->label(__('purchase_orders.record_payment.cash_account'))
                    ->options(CashAccount::active()->cash()->pluck('name', 'code'))
                    ->searchable()
                    ->required(),
                TextInput::make('amount')->label(__('purchase_orders.record_payment.amount'))->numeric()->minValue(0.01)->prefix('Rp')->required(),
            ])
            ->action(function (PurchaseOrder $record, array $data) {
                try {
                    app(PurchaseOrderService::class)->recordPayment(
                        $record,
                        $data['cash_account_code'],
                        (float) $data['amount'],
                        auth()->id(),
                    );
                    Notification::make()->title(__('purchase_orders.record_payment.notification'))->success()->send();
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    private static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label(__('purchase_orders.cancel.action'))
            ->requiresConfirmation()
            ->visible(fn (PurchaseOrder $record) => auth()->user()?->hasAnyRole(['Admin', 'Manager'])
                && $record->status === 'ordered')
            ->action(function (PurchaseOrder $record) {
                try {
                    app(PurchaseOrderService::class)->cancel($record);
                    Notification::make()->title(__('purchase_orders.cancel.notification'))->success()->send();
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

<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\ManageOrders;
use App\Models\Order;
use App\Models\Product;
use App\Services\SalesReturnService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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

    public static function getNavigationGroup(): ?string
    {
        return __('nav.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('orders.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('orders.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('orders.plural');
    }

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
            TextInput::make('order_number')->label(__('orders.fields.order_number')),
            TextInput::make('subtotal')->label(__('orders.fields.subtotal'))->prefix('Rp'),
            TextInput::make('discount_amount')->label(__('orders.fields.discount_amount'))->prefix('Rp'),
            TextInput::make('total')->label(__('orders.fields.total'))->prefix('Rp'),
            TextInput::make('payment_method')->label(__('orders.fields.payment_method')),
            TextInput::make('has_negative_stock_flag')->label(__('orders.fields.negative_stock_flag')),
            TextInput::make('completed_at')->label(__('orders.fields.completed_at')),
            Repeater::make('items')
                ->schema([
                    TextInput::make('product_id')->label(__('orders.fields.item_product')),
                    TextInput::make('quantity')->label(__('orders.fields.item_quantity')),
                    TextInput::make('unit_price')->label(__('orders.fields.item_unit_price'))->prefix('Rp'),
                    TextInput::make('subtotal')->label(__('orders.fields.item_subtotal'))->prefix('Rp'),
                ])
                ->columns(4)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->label(__('orders.fields.order_number'))->searchable(),
                TextColumn::make('warehouse.name')->label(__('orders.fields.warehouse'))->sortable(),
                TextColumn::make('cashier.name')->label(__('orders.fields.cashier')),
                TextColumn::make('discount.code')->label(__('orders.fields.discount')),
                TextColumn::make('subtotal')->label(__('orders.fields.subtotal'))->money('IDR'),
                TextColumn::make('discount_amount')->label(__('orders.fields.discount_amount'))->money('IDR'),
                TextColumn::make('total')->label(__('orders.fields.total'))->money('IDR')->sortable(),
                TextColumn::make('payment_method')->label(__('orders.fields.payment_method'))->badge(),
                IconColumn::make('has_negative_stock_flag')->label(__('orders.fields.negative_stock_short'))->boolean(),
                TextColumn::make('completed_at')->label(__('orders.fields.completed_at'))->dateTime()->sortable(),
            ])
            ->defaultSort('completed_at', 'desc')
            ->filters([
                SelectFilter::make('warehouse_id')->label(__('orders.fields.warehouse'))->relationship('warehouse', 'name'),
                SelectFilter::make('payment_method')->options([
                    'cash' => __('orders.fields.payment_method_cash'),
                    'qris' => __('orders.fields.payment_method_qris'),
                ]),
                TernaryFilter::make('has_negative_stock_flag')->label(__('orders.fields.negative_stock_flag')),
            ])
            ->recordActions([
                ViewAction::make(),
                self::processReturnAction(),
            ])
            ->toolbarActions([])
            ->headerActions([
                // No ->icon() here — deliberately avoiding an unverifiable Heroicon case
                // name (no vendor/ present in this checkout to confirm against).
                Action::make('exportCsv')
                    ->label(__('orders.export_csv'))
                    ->url(fn () => route('exports.orders'))
                    ->openUrlInNewTab(),
            ]);
    }

    /**
     * Return (Phase 4 ERP-gap follow-up) is initiated from the order being returned,
     * same shape as InventoryResource's receiveStock/PurchaseOrderResource's receive —
     * a record action whose schema closure reads the bound $record's own line items.
     */
    private static function processReturnAction(): Action
    {
        return Action::make('processReturn')
            ->label(__('orders.return.action'))
            ->visible(fn (Order $record) => auth()->user()?->hasAnyRole(['Admin', 'Manager', 'Supervisor'])
                && $record->status === 'completed')
            ->schema([
                Textarea::make('reason')->label(__('orders.return.reason'))->required()->minLength(5)->columnSpanFull(),
                Select::make('refund_method')
                    ->label(__('orders.return.refund_method'))
                    ->helperText(__('orders.return.refund_method_help'))
                    ->options(['cash' => __('orders.fields.payment_method_cash'), 'qris' => __('orders.fields.payment_method_qris')]),
                Repeater::make('items')
                    ->schema([
                        Select::make('product_id')
                            ->label(__('orders.return.item_product'))
                            ->options(fn (Order $record) => collect($record->items)
                                ->mapWithKeys(fn ($line) => [$line['product_id'] => Product::find($line['product_id'])?->name.' ('.$line['quantity'].' sold)']))
                            ->required(),
                        TextInput::make('quantity')->label(__('orders.return.item_quantity'))->numeric()->minValue(1)->required(),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->required(),
            ])
            ->action(function (Order $record, array $data) {
                try {
                    app(SalesReturnService::class)->process(
                        $record->id,
                        $data['items'],
                        $data['reason'],
                        auth()->id(),
                        $data['refund_method'] ?? null,
                    );
                    Notification::make()->title(__('orders.return.processed_notification'))->success()->send();
                } catch (\RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrders::route('/'),
        ];
    }
}

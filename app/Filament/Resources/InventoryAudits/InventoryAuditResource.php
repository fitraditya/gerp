<?php

namespace App\Filament\Resources\InventoryAudits;

use App\Filament\Resources\InventoryAudits\Pages\ManageInventoryAudits;
use App\Models\InventoryAudit;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use BackedEnum;
use Filament\Actions\Action;
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

class InventoryAuditResource extends Resource
{
    protected static ?string $model = InventoryAudit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.inventory');
    }

    public static function getNavigationLabel(): string
    {
        return __('inventory_audits.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('inventory_audits.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory_audits.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')->label(__('inventory_audits.fields.product'))->options(Product::query()->pluck('name', 'id'))->searchable()->required(),
                Select::make('warehouse_id')->label(__('inventory_audits.fields.warehouse'))->options(Warehouse::query()->pluck('name', 'id'))->searchable()->required(),
                TextInput::make('actual_qty')->label(__('inventory_audits.fields.actual_qty'))->numeric()->required(),
                Textarea::make('notes')
                    ->label(__('inventory_audits.fields.notes'))
                    ->required()
                    ->minLength(10)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->label(__('inventory_audits.fields.product'))->searchable(),
                TextColumn::make('warehouse.name')->label(__('inventory_audits.fields.warehouse')),
                TextColumn::make('expected_qty')->label(__('inventory_audits.fields.expected_qty')),
                TextColumn::make('actual_qty')->label(__('inventory_audits.fields.actual_qty_short')),
                TextColumn::make('variance')->label(__('inventory_audits.fields.variance'))->color(fn (InventoryAudit $record) => $record->variance < 0 ? 'danger' : ($record->variance > 0 ? 'warning' : 'success')),
                TextColumn::make('status')->label(__('inventory_audits.fields.status'))->badge(),
                TextColumn::make('createdBy.name')->label(__('inventory_audits.fields.submitted_by')),
                TextColumn::make('verifiedBy.name')->label(__('inventory_audits.fields.verified_by')),
                TextColumn::make('created_at')->label(__('inventory_audits.fields.created_at'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => __('inventory_audits.fields.status_pending'),
                    'verified' => __('inventory_audits.fields.status_verified'),
                    'rejected' => __('inventory_audits.fields.status_rejected'),
                ]),
                SelectFilter::make('warehouse_id')->label(__('inventory_audits.fields.warehouse'))->relationship('warehouse', 'name'),
            ])
            ->recordActions([
                self::verifyAction(),
            ])
            ->toolbarActions([]);
    }

    private static function verifyAction(): Action
    {
        return Action::make('verify')
            ->label(__('inventory_audits.verify.action'))
            ->requiresConfirmation()
            ->visible(fn (InventoryAudit $record) => $record->status === 'pending' && auth()->user()?->can('verify', $record))
            ->action(function (InventoryAudit $record) {
                app(InventoryService::class)->verifyOpname($record, auth()->id());

                Notification::make()->title(__('inventory_audits.verify.notification'))->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInventoryAudits::route('/'),
        ];
    }
}

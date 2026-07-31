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

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $modelLabel = 'Stock Opname';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')->label('Product')->options(Product::query()->pluck('name', 'id'))->searchable()->required(),
                Select::make('warehouse_id')->label('Warehouse')->options(Warehouse::query()->pluck('name', 'id'))->searchable()->required(),
                TextInput::make('actual_qty')->label('Physical Count')->numeric()->required(),
                Textarea::make('notes')
                    ->label('Justification (min 10 characters)')
                    ->required()
                    ->minLength(10)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->label('Product')->searchable(),
                TextColumn::make('warehouse.name')->label('Warehouse'),
                TextColumn::make('expected_qty')->label('Expected'),
                TextColumn::make('actual_qty')->label('Physical'),
                TextColumn::make('variance')->color(fn (InventoryAudit $record) => $record->variance < 0 ? 'danger' : ($record->variance > 0 ? 'warning' : 'success')),
                TextColumn::make('status')->badge(),
                TextColumn::make('createdBy.name')->label('Submitted By'),
                TextColumn::make('verifiedBy.name')->label('Verified By'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected']),
                SelectFilter::make('warehouse_id')->label('Warehouse')->relationship('warehouse', 'name'),
            ])
            ->recordActions([
                self::verifyAction(),
            ])
            ->toolbarActions([]);
    }

    private static function verifyAction(): Action
    {
        return Action::make('verify')
            ->label('Verify')
            ->requiresConfirmation()
            ->visible(fn (InventoryAudit $record) => $record->status === 'pending' && auth()->user()?->can('verify', $record))
            ->action(function (InventoryAudit $record) {
                app(InventoryService::class)->verifyOpname($record, auth()->id());

                Notification::make()->title('Opname verified, inventory updated')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInventoryAudits::route('/'),
        ];
    }
}

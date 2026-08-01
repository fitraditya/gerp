<?php

namespace App\Filament\Resources\Warehouses;

use App\Filament\Resources\Warehouses\Pages\ManageWarehouses;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.master_data');
    }

    public static function getNavigationLabel(): string
    {
        return __('warehouses.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('warehouses.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('warehouses.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->label(__('warehouses.fields.code'))->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('name')->label(__('warehouses.fields.name'))->required()->maxLength(255),
                Select::make('type')
                    ->label(__('warehouses.fields.type'))
                    ->options(['central' => __('warehouses.fields.type_central'), 'branch' => __('warehouses.fields.type_branch')])
                    ->default('central')
                    ->required(),
                Textarea::make('address')->label(__('warehouses.fields.address'))->columnSpanFull(),
                Toggle::make('is_active')->label(__('warehouses.fields.is_active'))->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('warehouses.fields.code'))->searchable()->sortable(),
                TextColumn::make('name')->label(__('warehouses.fields.name'))->searchable()->sortable(),
                TextColumn::make('type')->label(__('warehouses.fields.type'))->badge(),
                IconColumn::make('is_active')->label(__('warehouses.fields.is_active'))->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWarehouses::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}

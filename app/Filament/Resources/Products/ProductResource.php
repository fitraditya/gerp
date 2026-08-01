<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Product;
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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.master_data');
    }

    public static function getNavigationLabel(): string
    {
        return __('products.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('products.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('products.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')->label(__('products.fields.sku'))->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('name')->label(__('products.fields.name'))->required()->maxLength(255),
                Select::make('brand_id')->label(__('products.fields.brand'))->relationship('brand', 'name')->searchable()->preload(),
                TextInput::make('price')->label(__('products.fields.price'))->required()->numeric()->prefix('Rp'),
                TextInput::make('cost_price')
                    ->label(__('products.fields.cost_price'))
                    ->helperText(__('products.fields.cost_price_help'))
                    ->numeric()
                    ->minValue(0)
                    ->prefix('Rp'),
                TextInput::make('tier')->label(__('products.fields.tier'))->maxLength(255),
                Textarea::make('description')->label(__('products.fields.description'))->columnSpanFull(),
                Toggle::make('is_active')->label(__('products.fields.is_active'))->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->label(__('products.fields.sku'))->searchable()->sortable(),
                TextColumn::make('name')->label(__('products.fields.name'))->searchable()->sortable(),
                TextColumn::make('brand.name')->label(__('products.fields.brand'))->sortable(),
                TextColumn::make('price')->label(__('products.fields.price'))->money('IDR')->sortable(),
                TextColumn::make('cost_price')->label(__('products.fields.cost_price'))->money('IDR')->sortable()->toggleable(),
                TextColumn::make('tier')->label(__('products.fields.tier')),
                IconColumn::make('is_active')->label(__('products.fields.is_active'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('brand_id')->label(__('products.fields.brand'))->relationship('brand', 'name'),
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
            'index' => ManageProducts::route('/'),
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

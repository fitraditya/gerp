<?php

namespace App\Filament\Resources\Suppliers;

use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Models\Supplier;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
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

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    // Reuses the same verified-working icon as Brand/Product (no vendor/ present in
    // this checkout to confirm other Heroicon enum cases exist).
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.master_data');
    }

    public static function getNavigationLabel(): string
    {
        return __('suppliers.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('suppliers.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('suppliers.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('suppliers.fields.name'))->required()->maxLength(255),
                TextInput::make('contact_person')->label(__('suppliers.fields.contact_person'))->maxLength(255),
                TextInput::make('phone')->label(__('suppliers.fields.phone'))->tel()->maxLength(50),
                TextInput::make('email')->label(__('suppliers.fields.email'))->email()->maxLength(255),
                Textarea::make('address')->label(__('suppliers.fields.address'))->columnSpanFull(),
                Toggle::make('is_active')->label(__('suppliers.fields.is_active'))->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('suppliers.fields.name'))->searchable()->sortable(),
                TextColumn::make('contact_person')->label(__('suppliers.fields.contact_person'))->searchable(),
                TextColumn::make('phone')->label(__('suppliers.fields.phone')),
                TextColumn::make('email')->label(__('suppliers.fields.email')),
                IconColumn::make('is_active')->label(__('suppliers.fields.is_active'))->boolean(),
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
            'index' => ManageSuppliers::route('/'),
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

<?php

namespace App\Filament\Resources\Discounts;

use App\Filament\Resources\Discounts\Pages\ManageDiscounts;
use App\Models\Discount;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
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

class DiscountResource extends Resource
{
    protected static ?string $model = Discount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('discounts.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('discounts.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('discounts.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->label(__('discounts.fields.code'))->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('name')->label(__('discounts.fields.name'))->required()->maxLength(255),
                Select::make('type')
                    ->label(__('discounts.fields.type'))
                    ->options(['percentage' => __('discounts.fields.type_percentage'), 'fixed' => __('discounts.fields.type_fixed')])
                    ->default('percentage')
                    ->required(),
                TextInput::make('value')->label(__('discounts.fields.value'))->required()->numeric(),
                TextInput::make('min_purchase')->label(__('discounts.fields.min_purchase'))->numeric()->prefix('Rp'),
                TextInput::make('max_usage')->label(__('discounts.fields.max_usage'))->numeric()->helperText(__('discounts.fields.max_usage_help')),
                DateTimePicker::make('valid_from')->label(__('discounts.fields.valid_from')),
                DateTimePicker::make('valid_until')->label(__('discounts.fields.valid_until')),
                Textarea::make('description')->label(__('discounts.fields.description'))->columnSpanFull(),
                Toggle::make('is_active')->label(__('discounts.fields.is_active'))->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('discounts.fields.code'))->searchable()->sortable(),
                TextColumn::make('name')->label(__('discounts.fields.name'))->searchable(),
                TextColumn::make('type')->label(__('discounts.fields.type'))->badge(),
                TextColumn::make('value')->label(__('discounts.fields.value')),
                TextColumn::make('usage_count')->label(__('discounts.fields.usage_count')),
                TextColumn::make('max_usage')->label(__('discounts.fields.max_usage_short')),
                IconColumn::make('is_active')->label(__('discounts.fields.is_active'))->boolean(),
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
            'index' => ManageDiscounts::route('/'),
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

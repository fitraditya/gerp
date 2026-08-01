<?php

namespace App\Filament\Resources\Branches;

use App\Filament\Resources\Branches\Pages\ManageBranches;
use App\Models\Branch;
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

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.master_data');
    }

    public static function getNavigationLabel(): string
    {
        return __('branches.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('branches.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('branches.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->label(__('branches.fields.code'))->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('name')->label(__('branches.fields.name'))->required()->maxLength(255),
                Select::make('type')
                    ->label(__('branches.fields.type'))
                    ->options(['masjid' => __('branches.fields.type_masjid'), 'bazzar' => __('branches.fields.type_bazzar')])
                    ->required(),
                TextInput::make('pic_name')->label(__('branches.fields.pic_name'))->maxLength(255),
                Select::make('warehouse_id')->label(__('branches.fields.warehouse'))->relationship('warehouse', 'name')->required()->searchable()->preload(),
                TextInput::make('phone')->label(__('branches.fields.phone'))->tel()->maxLength(255),
                Textarea::make('address')->label(__('branches.fields.address'))->columnSpanFull(),
                Toggle::make('is_active')->label(__('branches.fields.is_active'))->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('branches.fields.code'))->searchable()->sortable(),
                TextColumn::make('name')->label(__('branches.fields.name'))->searchable()->sortable(),
                TextColumn::make('type')->label(__('branches.fields.type'))->badge(),
                TextColumn::make('pic_name')->label(__('branches.fields.pic_name')),
                TextColumn::make('warehouse.name')->label(__('branches.fields.warehouse'))->sortable(),
                TextColumn::make('phone')->label(__('branches.fields.phone')),
                IconColumn::make('is_active')->label(__('branches.fields.is_active'))->boolean(),
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
            'index' => ManageBranches::route('/'),
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

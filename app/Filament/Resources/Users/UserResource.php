<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.master_data');
    }

    public static function getNavigationLabel(): string
    {
        return __('users.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('users.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('users.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('users.fields.name'))->required()->maxLength(255),
                TextInput::make('email')->label(__('users.fields.email'))->email()->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('phone')->label(__('users.fields.phone'))->tel()->maxLength(255),
                TextInput::make('password')
                    ->label(__('users.fields.password'))
                    ->password()
                    ->revealable()
                    ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->required(fn (string $operation) => $operation === 'create'),
                Select::make('roles')
                    ->label(__('users.fields.role'))
                    ->relationship('roles', 'name')
                    ->options(fn () => Role::pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Select::make('warehouse_id')
                    ->label(__('users.fields.warehouse'))
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText(__('users.fields.warehouse_help')),
                Toggle::make('is_active')->label(__('users.fields.is_active'))->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('users.fields.name'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('users.fields.email'))->searchable()->sortable(),
                TextColumn::make('roles.name')->label(__('users.fields.role'))->badge(),
                TextColumn::make('warehouse.name')->label(__('users.fields.warehouse')),
                IconColumn::make('is_active')->label(__('users.fields.is_active'))->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Remittances;

use App\Filament\Resources\Remittances\Pages\ManageRemittances;
use App\Models\CashAccount;
use App\Models\Remittance;
use App\Models\Warehouse;
use App\Services\RemittanceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RemittanceResource extends Resource
{
    protected static ?string $model = Remittance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return __('nav.finance');
    }

    public static function getNavigationLabel(): string
    {
        return __('remittances.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('remittances.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('remittances.plural');
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && !$user->hasRole('Admin') && $user->warehouse_id) {
            $query->where(function ($q) use ($user) {
                $q->where('from_warehouse_id', $user->warehouse_id)
                    ->orWhere('to_warehouse_id', $user->warehouse_id);
            });
        }

        return $query;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('from_warehouse_id')
                    ->label(__('remittances.fields.from_warehouse'))
                    ->options(Warehouse::query()->where('type', 'branch')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('source_cash_account_id')
                    ->label(__('remittances.fields.source_cash_account'))
                    ->options(fn () => CashAccount::cash()->get()->mapWithKeys(fn ($a) => [$a->id => $a->name.($a->holder_name ? " ({$a->holder_name})" : '')]))
                    ->searchable()
                    ->required(),
                TextInput::make('amount')->label(__('remittances.fields.amount'))->numeric()->required()->prefix('Rp'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('remittance_number')->label(__('remittances.fields.remittance_number'))->searchable(),
                TextColumn::make('fromWarehouse.name')->label(__('remittances.fields.from')),
                TextColumn::make('toWarehouse.name')->label(__('remittances.fields.to')),
                TextColumn::make('amount')->label(__('remittances.fields.amount'))->money('IDR')->sortable(),
                TextColumn::make('status')->label(__('remittances.fields.status'))->badge(),
                TextColumn::make('submittedBy.name')->label(__('remittances.fields.submitted_by')),
                TextColumn::make('verifiedBy.name')->label(__('remittances.fields.verified_by')),
                TextColumn::make('created_at')->label(__('remittances.fields.created_at'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => __('remittances.fields.status_pending'),
                    'verified' => __('remittances.fields.status_verified'),
                    'rejected' => __('remittances.fields.status_rejected'),
                ]),
            ])
            ->recordActions([
                self::verifyAction(),
            ])
            ->toolbarActions([]);
    }

    private static function verifyAction(): Action
    {
        return Action::make('verify')
            ->label(__('remittances.verify.action'))
            ->visible(fn (Remittance $record) => $record->status === 'pending' && auth()->user()?->can('verify', $record))
            ->schema([
                Select::make('destination_cash_account_id')
                    ->label(__('remittances.verify.deposit_into'))
                    ->options(fn () => CashAccount::cash()->get()->mapWithKeys(fn ($a) => [$a->id => $a->name.($a->holder_name ? " ({$a->holder_name})" : '')]))
                    ->searchable()
                    ->required(),
            ])
            ->action(function (Remittance $record, array $data) {
                app(RemittanceService::class)->verify($record, (int) $data['destination_cash_account_id'], auth()->id());

                Notification::make()->title(__('remittances.verify.notification'))->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRemittances::route('/'),
        ];
    }
}

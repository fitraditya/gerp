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

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $modelLabel = 'Cash Remittance (Setoran Kas)';

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
                    ->label('Branch Warehouse')
                    ->options(Warehouse::query()->where('type', 'branch')->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
                Select::make('source_cash_account_id')
                    ->label('Cash Source')
                    ->options(fn () => CashAccount::cash()->get()->mapWithKeys(fn ($a) => [$a->id => $a->name.($a->holder_name ? " ({$a->holder_name})" : '')]))
                    ->searchable()
                    ->required(),
                TextInput::make('amount')->numeric()->required()->prefix('Rp'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('remittance_number')->searchable(),
                TextColumn::make('fromWarehouse.name')->label('From'),
                TextColumn::make('toWarehouse.name')->label('To'),
                TextColumn::make('amount')->money('IDR')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('submittedBy.name')->label('Submitted By'),
                TextColumn::make('verifiedBy.name')->label('Verified By'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected']),
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
            ->visible(fn (Remittance $record) => $record->status === 'pending' && auth()->user()?->can('verify', $record))
            ->schema([
                Select::make('destination_cash_account_id')
                    ->label('Deposit Into')
                    ->options(fn () => CashAccount::cash()->get()->mapWithKeys(fn ($a) => [$a->id => $a->name.($a->holder_name ? " ({$a->holder_name})" : '')]))
                    ->searchable()
                    ->required(),
            ])
            ->action(function (Remittance $record, array $data) {
                app(RemittanceService::class)->verify($record, (int) $data['destination_cash_account_id'], auth()->id());

                Notification::make()->title('Remittance verified, funds moved to treasury')->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRemittances::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Remittances\Pages;

use App\Filament\Resources\Remittances\RemittanceResource;
use App\Models\Warehouse;
use App\Services\RemittanceService;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Exceptions\Halt;

class ManageRemittances extends ManageRecords
{
    protected static string $resource = RemittanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Submit Remittance')
                ->using(function (array $data) {
                    $central = Warehouse::where('type', 'central')->firstOrFail();

                    try {
                        return app(RemittanceService::class)->submit(
                            (int) $data['from_warehouse_id'],
                            $central->id,
                            (int) $data['source_cash_account_id'],
                            (float) $data['amount'],
                            auth()->id(),
                        );
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                        throw new Halt();
                    }
                }),
        ];
    }
}

<?php

namespace App\Filament\Resources\InventoryAudits\Pages;

use App\Filament\Resources\InventoryAudits\InventoryAuditResource;
use App\Services\InventoryService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInventoryAudits extends ManageRecords
{
    protected static string $resource = InventoryAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('inventory_audits.submit'))
                ->using(fn (array $data) => app(InventoryService::class)->submitOpname(
                    (int) $data['product_id'],
                    (int) $data['warehouse_id'],
                    (int) $data['actual_qty'],
                    $data['notes'],
                    auth()->id(),
                )),
        ];
    }
}

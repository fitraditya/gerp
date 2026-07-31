<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Services\ExpenseService;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Exceptions\Halt;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data) {
                    try {
                        return app(ExpenseService::class)->recordExpense(
                            (int) $data['warehouse_id'],
                            (int) $data['source_cash_account_id'],
                            $data['category'],
                            $data['description'],
                            (float) $data['amount'],
                            $data['fund_pool'],
                            $data['payment_method'] ?? null,
                            $data['payee_name'] ?? null,
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

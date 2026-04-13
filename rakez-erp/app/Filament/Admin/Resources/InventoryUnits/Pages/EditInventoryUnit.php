<?php

namespace App\Filament\Admin\Resources\InventoryUnits\Pages;

use App\Filament\Admin\Resources\InventoryUnits\InventoryUnitResource;
use App\Models\ContractUnit;
use App\Models\User;
use App\Services\Contract\ContractUnitService;
use App\Services\Governance\GovernanceAuditLogger;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditInventoryUnit extends EditRecord
{
    protected static string $resource = InventoryUnitResource::class;

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        abort_unless($record instanceof ContractUnit, 404);

        return app(ContractUnitService::class)->updateUnit($record->id, $data);
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        $actor = auth()->user();

        if (! $record instanceof ContractUnit || ! $actor instanceof User) {
            return;
        }

        app(GovernanceAuditLogger::class)->log('governance.inventory.unit.updated', $record, [
            'after' => [
                'unit_number' => $record->unit_number,
                'status' => $record->status,
                'price' => $record->price,
            ],
        ], $actor);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deleteUnit')
                ->label('Delete')
                ->requiresConfirmation()
                ->color('danger')
                ->action(function (): void {
                    $record = $this->getRecord();

                    abort_unless($record instanceof ContractUnit, 404);

                    $actor = auth()->user();

                    abort_unless($actor instanceof User, 403);

                    app(GovernanceAuditLogger::class)->log('governance.inventory.unit.deleted', $record, [
                        'before' => [
                            'contract_id' => $record->contract_id,
                            'unit_number' => $record->unit_number,
                            'status' => $record->status,
                        ],
                    ], $actor);

                    app(ContractUnitService::class)->deleteUnit($record->id);

                    $this->redirect(InventoryUnitResource::getUrl());
                }),
        ];
    }
}

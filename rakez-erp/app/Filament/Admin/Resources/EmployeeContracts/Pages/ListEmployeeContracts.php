<?php

namespace App\Filament\Admin\Resources\EmployeeContracts\Pages;

use App\Filament\Admin\Resources\EmployeeContracts\EmployeeContractResource;
use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use App\Services\HR\EmployeeContractService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeContracts extends ListRecords
{
    protected static string $resource = EmployeeContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createContract')
                ->label(__('filament-admin.resources.employee_contracts.actions.create'))
                ->icon('heroicon-o-document-plus')
                ->visible(fn (): bool => EmployeeContractResource::canCreate())
                ->form([
                    Select::make('user_id')
                        ->label(__('filament-admin.resources.employee_contracts.columns.employee'))
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
                    ...EmployeeContractResource::contractForm(),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();

                    abort_unless($actor instanceof User, 403);

                    $contract = app(EmployeeContractService::class)->createContract(
                        (int) $data['user_id'],
                        EmployeeContractResource::contractPayload($data)
                    );

                    app(GovernanceAuditLogger::class)->log('governance.hr.contract.created', $contract, [
                        'after' => [
                            'user_id' => $contract->user_id,
                            'status' => $contract->status,
                            'start_date' => $contract->start_date?->format('Y-m-d'),
                        ],
                    ], $actor);

                    Notification::make()
                        ->success()
                        ->title(__('filament-admin.resources.employee_contracts.notifications.created'))
                        ->send();
                }),
        ];
    }
}

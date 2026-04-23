<?php

namespace App\Filament\Admin\Resources\Contracts\Pages;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\Contracts\ContractResource;
use App\Http\Requests\Contract\ApproveMontageDepartmentRequest;
use App\Http\Requests\Contract\ApprovePhotographyDepartmentRequest;
use App\Http\Requests\Contract\StoreBoardsDepartmentRequest;
use App\Http\Requests\Contract\StoreContractInfoRequest;
use App\Http\Requests\Contract\StoreSecondPartyDataRequest;
use App\Http\Requests\Contract\UpdateBoardsDepartmentRequest;
use App\Http\Requests\Contract\UpdateContractInfoRequest;
use App\Http\Requests\Contract\UpdateSecondPartyDataRequest;
use App\Http\Requests\Contract\UploadContractUnitsRequest;
use App\Models\Contract;
use App\Models\Team;
use App\Models\User;
use App\Services\Contract\BoardsDepartmentService;
use App\Services\Contract\ContractService;
use App\Services\Contract\ContractUnitService;
use App\Services\Contract\MontageDepartmentService;
use App\Services\Contract\PhotographyDepartmentService;
use App\Services\Contract\SecondPartyDataService;
use App\Services\Governance\GovernanceAuditLogger;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ViewContract extends ViewRecord
{
    use HasGovernanceAuthorization;

    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approvePendingContract')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => static::canGovernanceMutation('contracts.approve') && $this->getRecord()->isPending())
                ->action(fn () => $this->transitionContract(
                    'approved',
                    'governance.contracts.approved',
                    'Contract approved.',
                )),
            Action::make('rejectPendingContract')
                ->label('Reject Pending')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => static::canGovernanceMutation('contracts.approve') && $this->getRecord()->isPending())
                ->action(fn () => $this->transitionContract(
                    'rejected',
                    'governance.contracts.rejected',
                    'Contract rejected.',
                )),
            Action::make('rejectApprovedContract')
                ->label('Reject Approved')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => static::canGovernanceMutation('contracts.approve') && $this->getRecord()->isApprovedOrCompleted())
                ->action(fn () => $this->transitionContractByProjectManagement(
                    'rejected',
                    'governance.contracts.rejected_after_approval',
                    'Approved contract rejected.',
                )),
            Action::make('markReadyForMarketing')
                ->label('Mark Ready')
                ->icon('heroicon-o-rocket-launch')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (): bool => static::canGovernanceMutation('contracts.approve') && $this->getRecord()->isApprovedOrCompleted())
                ->action(fn () => $this->transitionContractByProjectManagement(
                    'ready',
                    'governance.contracts.marked_ready',
                    'Contract marked ready for marketing.',
                )),
            Action::make('manageTeams')
                ->label('Manage Teams')
                ->icon('heroicon-o-users')
                ->color('gray')
                ->visible(fn (): bool => static::canGovernanceMutation('projects.team.allocate'))
                ->fillForm(fn (): array => [
                    'team_ids' => $this->getRecord()->teams()->pluck('teams.id')->map(fn (int $id): string => (string) $id)->all(),
                ])
                ->schema([
                    Select::make('team_ids')
                        ->label('Assigned Teams')
                        ->multiple()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Team::query()
                            ->where('name', 'like', '%' . trim($search) . '%')
                            ->orderBy('name')
                            ->limit(50)
                            ->pluck('name', 'id')
                            ->all())
                        ->getOptionLabelsUsing(fn (array $values): array => Team::query()
                            ->whereIn('id', collect($values)->map(fn ($id): int => (int) $id)->all())
                            ->pluck('name', 'id')
                            ->all()),
                ])
                ->action(function (array $data): void {
                    $record = $this->contract();
                    $beforeTeamIds = $record->teams()->pluck('teams.id')->map(fn (int $id): string => (string) $id)->all();
                    $requestedTeamIds = collect($data['team_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->values()->all();
                    $toAttach = array_values(array_diff($requestedTeamIds, array_map('intval', $beforeTeamIds)));
                    $toDetach = array_values(array_diff(array_map('intval', $beforeTeamIds), $requestedTeamIds));

                    $this->runContractMutation(
                        'governance.contracts.teams_synced',
                        'Contract teams updated.',
                        function () use ($record, $toAttach, $toDetach): Contract {
                            $service = app(ContractService::class);

                            if ($toAttach !== []) {
                                $service->attachTeamsToContract($record->id, $toAttach);
                            }

                            if ($toDetach !== []) {
                                $service->detachTeamsFromContract($record->id, $toDetach);
                            }

                            return $this->contract()->fresh(['teams']) ?? $this->contract();
                        },
                        ['team_ids' => array_map('intval', $beforeTeamIds)],
                        fn (Contract $updated): array => [
                            'team_ids' => $updated->teams()->pluck('teams.id')->map(fn (int $id): int => $id)->all(),
                        ],
                    );
                }),
            Action::make('uploadUnitsCsv')
                ->label('Upload Units CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn (): bool => static::canGovernanceMutation('units.csv_upload') || static::canGovernanceMutation('units.edit'))
                ->schema([
                    FileUpload::make('csv_file')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required()
                        ->storeFiles(false),
                ])
                ->action(function (array $data): void {
                    $validated = $this->validatePayload($data, UploadContractUnitsRequest::class);
                    $record = $this->contract();

                    $this->runContractMutation(
                        'governance.contracts.units_uploaded',
                        'Contract units uploaded.',
                        fn (): array => app(ContractUnitService::class)->uploadCsvByContractId($record->id, $validated['csv_file']),
                        ['units_count' => $record->contractUnits()->count()],
                        fn (array $result): array => [
                            'units_count' => $this->contract()->fresh()->contractUnits()->count(),
                            'units_created' => $result['units_created'] ?? null,
                        ],
                    );
                }),
            Action::make('updateSecondPartyData')
                ->label('Update Second Party')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->visible(fn (): bool => static::canGovernanceMutation('second_party.edit'))
                ->fillForm(fn (): array => Arr::only($this->getRecord()->secondPartyData?->toArray() ?? [], [
                    'real_estate_papers_url',
                    'plans_equipment_docs_url',
                    'project_logo_url',
                    'prices_units_url',
                    'marketing_license_url',
                    'advertiser_section_url',
                ]))
                ->schema($this->secondPartyFormSchema())
                ->action(function (array $data): void {
                    $record = $this->contract();
                    $requestClass = $record->secondPartyData ? UpdateSecondPartyDataRequest::class : StoreSecondPartyDataRequest::class;
                    $validated = $this->validatePayload($data, $requestClass);

                    $this->runContractMutation(
                        'governance.contracts.second_party_updated',
                        'Second party data updated.',
                        function () use ($record, $validated) {
                            $service = app(SecondPartyDataService::class);

                            return $record->secondPartyData
                                ? $service->updateByContractId($record->id, $validated)
                                : $service->store($record->id, $validated);
                        },
                        $record->secondPartyData?->toArray() ?? [],
                        fn ($updated): array => $updated->toArray(),
                    );
                }),
            Action::make('updateBoardsDepartment')
                ->label('Update Boards')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->visible(fn (): bool => static::canGovernanceMutation('departments.boards.edit'))
                ->fillForm(fn (): array => [
                    'has_ads' => (bool) $this->getRecord()->boardsDepartment?->has_ads,
                ])
                ->schema([
                    Toggle::make('has_ads')
                        ->label('Has Ads')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $record = $this->contract();
                    $requestClass = $record->boardsDepartment ? UpdateBoardsDepartmentRequest::class : StoreBoardsDepartmentRequest::class;
                    $validated = $this->validatePayload($data, $requestClass);

                    $this->runContractMutation(
                        'governance.contracts.boards_updated',
                        'Boards department updated.',
                        function () use ($record, $validated) {
                            $service = app(BoardsDepartmentService::class);

                            return $record->boardsDepartment
                                ? $service->updateByContractId($record->id, $validated)
                                : $service->store($record->id, $validated);
                        },
                        $record->boardsDepartment?->toArray() ?? [],
                        fn ($updated): array => $updated->toArray(),
                    );
                }),
            Action::make('updateContractInfo')
                ->label('Update Contract Info')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->visible(fn (): bool => static::canGovernanceMutation('contracts.approve'))
                ->fillForm(fn (): array => Arr::only($this->getRecord()->info?->toArray() ?? [], [
                    'gregorian_date',
                    'hijri_date',
                    'contract_city',
                    'location_url',
                    'agreement_duration_days',
                    'agency_number',
                    'agency_date',
                    'avg_property_value',
                    'release_date',
                    'second_party_name',
                    'second_party_address',
                    'second_party_cr_number',
                    'second_party_signatory',
                    'second_party_id_number',
                    'second_party_role',
                    'second_party_phone',
                    'second_party_email',
                ]))
                ->schema($this->contractInfoFormSchema())
                ->action(function (array $data): void {
                    $record = $this->contract();
                    $requestClass = $record->info ? UpdateContractInfoRequest::class : StoreContractInfoRequest::class;
                    $validated = $this->validatePayload($data, $requestClass);

                    $this->runContractMutation(
                        'governance.contracts.info_updated',
                        'Contract info updated.',
                        function () use ($record, $validated) {
                            $service = app(ContractService::class);

                            return $record->info
                                ? $service->updateContractInfo($record->id, $validated, auth()->id())
                                : $service->storeContractInfo($record->id, $validated, $record);
                        },
                        $record->info?->toArray() ?? [],
                        fn ($updated): array => $updated->toArray(),
                    );
                }),
            Action::make('approvePhotography')
                ->label('Approve Photography')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => static::canGovernanceMutation('projects.media.approve') && $this->getRecord()->photographyDepartment !== null)
                ->schema([
                    Textarea::make('comment')
                        ->label('Approval Note')
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->reviewPhotography(true, $data)),
            Action::make('rejectPhotography')
                ->label('Reject Photography')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => static::canGovernanceMutation('projects.media.approve') && $this->getRecord()->photographyDepartment !== null)
                ->schema([
                    Textarea::make('comment')
                        ->label('Rejection Reason')
                        ->required()
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->reviewPhotography(false, $data)),
            Action::make('approveMontage')
                ->label('Approve Montage')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => static::canGovernanceMutation('projects.media.approve') && $this->getRecord()->montageDepartment !== null)
                ->schema([
                    Textarea::make('comment')
                        ->label('Approval Note')
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->reviewMontage(true, $data)),
            Action::make('rejectMontage')
                ->label('Reject Montage')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => static::canGovernanceMutation('projects.media.approve') && $this->getRecord()->montageDepartment !== null)
                ->schema([
                    Textarea::make('comment')
                        ->label('Rejection Reason')
                        ->required()
                        ->rows(3)
                        ->maxLength(2000),
                ])
                ->action(fn (array $data) => $this->reviewMontage(false, $data)),
            Action::make('downloadContractInfoPdf')
                ->label('Contract Info PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => static::canGovernance('contracts.view_all') && $this->getRecord()->info !== null)
                ->url(fn (): string => route('filament.pm.contracts.contract_info_pdf', ['contractId' => $this->getRecord()->id]))
                ->openUrlInNewTab(),
            Action::make('downloadSecondPartyPdf')
                ->label('Second Party PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => static::canGovernance('contracts.view_all') && $this->getRecord()->secondPartyData !== null)
                ->url(fn (): string => route('filament.pm.contracts.second_party_pdf', ['contractId' => $this->getRecord()->id]))
                ->openUrlInNewTab(),
        ];
    }

    private function transitionContract(string $status, string $event, string $successTitle): void
    {
        try {
            $actor = auth()->user();
            abort_unless($actor instanceof User, 403);

            app(ContractService::class)->transitionStatusForGovernance(
                $this->contract()->id,
                $status,
                $event,
                $actor,
            );

            Notification::make()
                ->success()
                ->title($successTitle)
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Action failed.')
                ->body($exception->getMessage())
                ->send();
        }
    }

    private function transitionContractByProjectManagement(string $status, string $event, string $successTitle): void
    {
        try {
            $actor = auth()->user();
            abort_unless($actor instanceof User, 403);

            app(ContractService::class)->transitionStatusForGovernance(
                $this->contract()->id,
                $status,
                $event,
                $actor,
                true,
            );

            Notification::make()
                ->success()
                ->title($successTitle)
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Action failed.')
                ->body($exception->getMessage())
                ->send();
        }
    }

    private function reviewPhotography(bool $approved, array $data): void
    {
        $validated = $this->validatePayload([
            'approved' => $approved,
            'comment' => $data['comment'] ?? null,
        ], ApprovePhotographyDepartmentRequest::class);

        $record = $this->contract();

        $this->runContractMutation(
            $approved ? 'governance.contracts.photography_approved' : 'governance.contracts.photography_rejected',
            $approved ? 'Photography approved.' : 'Photography rejected.',
            fn () => app(PhotographyDepartmentService::class)->approveByContractId(
                $record->id,
                $validated['approved'],
                $validated['comment'] ?? null,
            ),
            $record->photographyDepartment?->toArray() ?? [],
            fn ($updated): array => $updated->toArray(),
        );
    }

    private function reviewMontage(bool $approved, array $data): void
    {
        $validated = $this->validatePayload([
            'approved' => $approved,
            'comment' => $data['comment'] ?? null,
        ], ApproveMontageDepartmentRequest::class);

        $record = $this->contract();

        $this->runContractMutation(
            $approved ? 'governance.contracts.montage_approved' : 'governance.contracts.montage_rejected',
            $approved ? 'Montage approved.' : 'Montage rejected.',
            fn () => app(MontageDepartmentService::class)->approveByContractId(
                $record->id,
                $validated['approved'],
                $validated['comment'] ?? null,
            ),
            $record->montageDepartment?->toArray() ?? [],
            fn ($updated): array => $updated->toArray(),
        );
    }

    private function runContractMutation(
        string $event,
        string $successTitle,
        callable $callback,
        array $beforePayload = [],
        ?callable $afterPayloadResolver = null,
    ): void {
        try {
            $actor = auth()->user();
            abort_unless($actor instanceof User, 403);

            $result = $callback();
            $contract = $this->contract()->fresh([
                'teams',
                'info',
                'secondPartyData',
                'boardsDepartment',
                'photographyDepartment',
                'montageDepartment',
                'contractUnits',
            ]) ?? $this->contract();

            app(GovernanceAuditLogger::class)->log($event, $contract, [
                'before' => $beforePayload,
                'after' => $afterPayloadResolver ? $afterPayloadResolver($result) : [],
            ], $actor);

            Notification::make()
                ->success()
                ->title($successTitle)
                ->send();
        } catch (Throwable $exception) {
            Notification::make()
                ->danger()
                ->title('Action failed.')
                ->body($exception->getMessage())
                ->send();
        }
    }

    private function validatePayload(array $data, string $requestClass): array
    {
        $request = new $requestClass;

        return Validator::make($data, $request->rules(), method_exists($request, 'messages') ? $request->messages() : [])
            ->validate();
    }

    private function contract(): Contract
    {
        /** @var Contract $record */
        $record = $this->getRecord();

        return $record->loadMissing([
            'teams',
            'info',
            'secondPartyData',
            'boardsDepartment',
            'photographyDepartment',
            'montageDepartment',
            'contractUnits',
        ]);
    }

    private function contractInfoFormSchema(): array
    {
        return [
            DatePicker::make('gregorian_date')->label('Gregorian Date'),
            TextInput::make('hijri_date')->label('Hijri Date')->maxLength(50),
            TextInput::make('contract_city')->label('Contract City')->maxLength(255),
            TextInput::make('location_url')->label('Location URL')->url()->maxLength(500),
            TextInput::make('agreement_duration_days')->label('Agreement Duration (Days)')->numeric()->minValue(0),
            TextInput::make('agency_number')->label('Agency Number')->maxLength(255),
            DatePicker::make('agency_date')->label('Agency Date'),
            TextInput::make('avg_property_value')->label('Average Property Value')->numeric()->minValue(0),
            DatePicker::make('release_date')->label('Release Date'),
            TextInput::make('second_party_name')->label('Second Party Name')->maxLength(255),
            Textarea::make('second_party_address')->label('Second Party Address')->rows(2),
            TextInput::make('second_party_cr_number')->label('Second Party CR Number')->maxLength(255),
            TextInput::make('second_party_signatory')->label('Second Party Signatory')->maxLength(255),
            TextInput::make('second_party_id_number')->label('Second Party ID Number')->maxLength(255),
            TextInput::make('second_party_role')->label('Second Party Role')->maxLength(255),
            TextInput::make('second_party_phone')->label('Second Party Phone')->maxLength(255),
            TextInput::make('second_party_email')->label('Second Party Email')->email()->maxLength(255),
        ];
    }

    private function secondPartyFormSchema(): array
    {
        return [
            TextInput::make('real_estate_papers_url')->label('Real Estate Papers URL')->url()->maxLength(500),
            TextInput::make('plans_equipment_docs_url')->label('Plans & Equipment URL')->url()->maxLength(500),
            TextInput::make('project_logo_url')->label('Project Logo URL')->url()->maxLength(500),
            TextInput::make('prices_units_url')->label('Prices & Units URL')->url()->maxLength(500),
            TextInput::make('marketing_license_url')->label('Marketing License URL')->url()->maxLength(500),
            TextInput::make('advertiser_section_url')->label('Advertiser Section')->maxLength(50),
        ];
    }
}

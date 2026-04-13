<?php

namespace App\Filament\Admin\Resources\EmployeeContracts;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\EmployeeContracts\Pages\ListEmployeeContracts;
use App\Models\EmployeeContract;
use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use App\Services\HR\EmployeeContractService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeeContractResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = EmployeeContract::class;

    protected static string $viewPermission = 'hr.contracts.view';

    protected static ?string $slug = 'employee-contracts';

    protected static ?string $navigationLabel = 'Employee Contracts';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string | \UnitEnum | null $navigationGroup = 'HR Oversight';

    protected static ?int $navigationSort = 13;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('employee')->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('employee.name')->label('Employee')->searchable()->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('start_date')->date(),
                TextColumn::make('end_date')->date()->placeholder('-'),
                IconColumn::make('pdf_path')
                    ->label('PDF')
                    ->boolean()
                    ->state(fn (EmployeeContract $record): bool => filled($record->pdf_path)),
                TextColumn::make('remaining_days')
                    ->label('Remaining Days')
                    ->state(fn (EmployeeContract $record): string => $record->getRemainingDays() !== null ? (string) $record->getRemainingDays() : '-'),
                TextColumn::make('created_at')->since(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'expired' => 'Expired',
                        'terminated' => 'Terminated',
                    ]),
            ])
            ->actions([
                Action::make('editContract')
                    ->label('Edit')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (EmployeeContract $record): bool => static::canEdit($record))
                    ->fillForm(fn (EmployeeContract $record): array => [
                        'job_title' => $record->contract_data['job_title'] ?? null,
                        'department' => $record->contract_data['department'] ?? null,
                        'salary' => $record->contract_data['salary'] ?? null,
                        'work_type' => $record->contract_data['work_type'] ?? null,
                        'probation_period' => $record->contract_data['probation_period'] ?? null,
                        'terms' => $record->contract_data['terms'] ?? null,
                        'benefits' => $record->contract_data['benefits'] ?? null,
                        'start_date' => $record->start_date?->format('Y-m-d'),
                        'end_date' => $record->end_date?->format('Y-m-d'),
                        'status' => $record->status,
                    ])
                    ->form(static::contractForm())
                    ->action(function (EmployeeContract $record, array $data): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $before = [
                            'status' => $record->status,
                            'start_date' => $record->start_date?->format('Y-m-d'),
                            'end_date' => $record->end_date?->format('Y-m-d'),
                            'contract_data' => $record->contract_data,
                        ];

                        $updated = app(EmployeeContractService::class)->updateContract($record->id, static::contractPayload($data));

                        app(GovernanceAuditLogger::class)->log('governance.hr.contract.updated', $updated, [
                            'before' => $before,
                            'after' => [
                                'status' => $updated->status,
                                'start_date' => $updated->start_date?->format('Y-m-d'),
                                'end_date' => $updated->end_date?->format('Y-m-d'),
                                'contract_data' => $updated->contract_data,
                            ],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Employee contract updated.')
                            ->send();
                    }),
                Action::make('generatePdf')
                    ->label('Generate PDF')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->visible(fn (EmployeeContract $record): bool => static::canGovernanceMutation('hr.contracts.manage'))
                    ->action(function (EmployeeContract $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $beforePdf = $record->pdf_path;

                        $path = app(EmployeeContractService::class)->generatePdf($record->id);

                        $record->refresh();

                        app(GovernanceAuditLogger::class)->log('governance.hr.contract.pdf_generated', $record, [
                            'before' => ['pdf_path' => $beforePdf],
                            'after' => ['pdf_path' => $record->pdf_path, 'storage_path' => $path],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Employee contract PDF generated.')
                            ->send();
                    }),
                Action::make('activateContract')
                    ->label('Activate')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (EmployeeContract $record): bool => static::canGovernanceMutation('hr.contracts.manage') && $record->status === 'draft')
                    ->action(function (EmployeeContract $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $beforeStatus = $record->status;

                        $updated = app(EmployeeContractService::class)->activateContract($record->id);

                        app(GovernanceAuditLogger::class)->log('governance.hr.contract.activated', $updated, [
                            'before' => ['status' => $beforeStatus],
                            'after' => ['status' => $updated->status],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Employee contract activated.')
                            ->send();
                    }),
                Action::make('terminateContract')
                    ->label('Terminate')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (EmployeeContract $record): bool => static::canGovernanceMutation('hr.contracts.manage') && in_array($record->status, ['draft', 'active'], true))
                    ->action(function (EmployeeContract $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        $beforeStatus = $record->status;

                        $updated = app(EmployeeContractService::class)->terminateContract($record->id);

                        app(GovernanceAuditLogger::class)->log('governance.hr.contract.terminated', $updated, [
                            'before' => ['status' => $beforeStatus],
                            'after' => ['status' => $updated->status],
                        ], $actor);

                        Notification::make()
                            ->success()
                            ->title('Employee contract terminated.')
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployeeContracts::route('/'),
        ];
    }

    protected static function createPermission(): ?string
    {
        return 'hr.contracts.manage';
    }

    protected static function editPermission(): ?string
    {
        return 'hr.contracts.manage';
    }

    public static function contractForm(): array
    {
        return [
            TextInput::make('job_title')
                ->required()
                ->maxLength(100),
            TextInput::make('department')
                ->maxLength(100),
            TextInput::make('salary')
                ->required()
                ->numeric()
                ->minValue(0),
            TextInput::make('work_type'),
            TextInput::make('probation_period'),
            DatePicker::make('start_date')
                ->required(),
            DatePicker::make('end_date')
                ->afterOrEqual('start_date'),
            Select::make('status')
                ->required()
                ->default('draft')
                ->options([
                    'draft' => 'Draft',
                    'active' => 'Active',
                    'expired' => 'Expired',
                    'terminated' => 'Terminated',
                ]),
            Textarea::make('terms')
                ->rows(4)
                ->columnSpanFull(),
            Textarea::make('benefits')
                ->rows(4)
                ->columnSpanFull(),
        ];
    }

    public static function contractPayload(array $data): array
    {
        return [
            'contract_data' => [
                'job_title' => $data['job_title'],
                'department' => $data['department'] ?? null,
                'salary' => $data['salary'],
                'work_type' => $data['work_type'] ?? null,
                'probation_period' => $data['probation_period'] ?? null,
                'terms' => $data['terms'] ?? null,
                'benefits' => $data['benefits'] ?? null,
            ],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'status' => $data['status'] ?? 'draft',
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('HR Oversight', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('HR Oversight', static::$viewPermission);
    }
}

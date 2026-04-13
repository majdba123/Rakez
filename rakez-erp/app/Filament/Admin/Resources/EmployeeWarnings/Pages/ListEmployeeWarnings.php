<?php

namespace App\Filament\Admin\Resources\EmployeeWarnings\Pages;

use App\Filament\Admin\Resources\EmployeeWarnings\EmployeeWarningResource;
use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use App\Services\HR\EmployeeWarningService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeWarnings extends ListRecords
{
    protected static string $resource = EmployeeWarningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issueWarning')
                ->label('Issue Warning')
                ->icon('heroicon-o-exclamation-triangle')
                ->visible(fn (): bool => EmployeeWarningResource::canCreate())
                ->form([
                    Select::make('user_id')
                        ->label('Employee')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
                    Select::make('type')
                        ->required()
                        ->options([
                            'performance' => 'Performance',
                            'attendance' => 'Attendance',
                            'behavior' => 'Behavior',
                            'other' => 'Other',
                        ]),
                    TextInput::make('reason')
                        ->required()
                        ->maxLength(255),
                    DatePicker::make('warning_date'),
                    Textarea::make('details')
                        ->rows(4)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();

                    abort_unless($actor instanceof User, 403);

                    $warning = app(EmployeeWarningService::class)->createWarning((int) $data['user_id'], $data, $actor);

                    app(GovernanceAuditLogger::class)->log('governance.hr.warning.issued', $warning, [
                        'after' => [
                            'user_id' => $warning->user_id,
                            'type' => $warning->type,
                            'warning_date' => $warning->warning_date?->format('Y-m-d'),
                        ],
                    ], $actor);

                    Notification::make()
                        ->success()
                        ->title('Employee warning issued.')
                        ->send();
                }),
        ];
    }
}

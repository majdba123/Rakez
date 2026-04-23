<?php

namespace App\Filament\Admin\Resources\ExclusiveProjectRequests\Pages;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\ExclusiveProjectRequests\ExclusiveProjectRequestResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewExclusiveProjectRequest extends ViewRecord
{
    use HasGovernanceAuthorization;

    protected static string $resource = ExclusiveProjectRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadExclusiveContractPdf')
                ->label('Download Contract PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => static::canGovernance('exclusive_projects.view') && $this->getRecord()->isContractCompleted())
                ->url(fn (): string => route('filament.pm.exclusive.contract_pdf', ['requestId' => $this->getRecord()->id]))
                ->openUrlInNewTab(),
        ];
    }

}

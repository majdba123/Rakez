<?php

namespace App\Filament\Admin\Resources\HrTeams\Pages;

use App\Filament\Admin\Resources\HrTeams\HrTeamResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHrTeams extends ListRecords
{
    protected static string $resource = HrTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\EmployeePerformanceScores\Pages;

use App\Filament\Admin\Resources\EmployeePerformanceScores\EmployeePerformanceScoreResource;
use Filament\Resources\Pages\ListRecords;

class ListEmployeePerformanceScores extends ListRecords
{
    protected static string $resource = EmployeePerformanceScoreResource::class;
}

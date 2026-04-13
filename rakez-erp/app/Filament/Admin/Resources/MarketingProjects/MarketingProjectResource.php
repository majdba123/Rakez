<?php

namespace App\Filament\Admin\Resources\MarketingProjects;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\MarketingProjects\Pages\ListMarketingProjects;
use App\Models\MarketingProject;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MarketingProjectResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = MarketingProject::class;

    protected static string $viewPermission = 'marketing.projects.view';

    protected static ?string $slug = 'marketing-projects-admin';

    protected static ?string $navigationLabel = 'Marketing Projects';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string | \UnitEnum | null $navigationGroup = 'Marketing Oversight';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['contract', 'teamLeader'])->withCount(['teams', 'employeePlans', 'tasks'])->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('contract.project_name')->label('Project')->searchable()->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('teamLeader.name')->label('Leader')->placeholder('-'),
                TextColumn::make('teams_count')->label('Teams')->sortable(),
                TextColumn::make('employee_plans_count')->label('Employee Plans')->sortable(),
                TextColumn::make('tasks_count')->label('Tasks')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'on_hold' => 'On Hold',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingProjects::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Marketing Oversight', static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Marketing Oversight', static::$viewPermission);
    }
}

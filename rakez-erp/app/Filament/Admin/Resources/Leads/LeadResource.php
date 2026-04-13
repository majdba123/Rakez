<?php

namespace App\Filament\Admin\Resources\Leads;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\Leads\Pages\ListLeads;
use App\Models\Lead;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeadResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = Lead::class;

    protected static string $viewPermission = 'marketing.projects.view';

    protected static ?string $slug = 'marketing-leads';

    protected static ?string $navigationLabel = 'Leads';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string | \UnitEnum | null $navigationGroup = 'Marketing Oversight';

    protected static ?int $navigationSort = 14;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['project', 'assignedTo'])->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('project.project_name')->label('Project')->searchable()->placeholder('-'),
                TextColumn::make('name')->searchable(),
                TextColumn::make('source')->badge()->placeholder('-'),
                TextColumn::make('campaign_platform')->label('Platform')->badge()->placeholder('-'),
                TextColumn::make('status')->badge()->placeholder('-'),
                TextColumn::make('assignedTo.name')->label('Assigned To')->placeholder('-'),
                TextColumn::make('ai_qualification_status')->label('AI Status')->badge()->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'new' => 'New',
                        'qualified' => 'Qualified',
                        'contacted' => 'Contacted',
                        'converted' => 'Converted',
                        'lost' => 'Lost',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
        ];
    }
}

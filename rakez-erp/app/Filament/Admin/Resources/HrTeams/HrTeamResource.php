<?php

namespace App\Filament\Admin\Resources\HrTeams;

use App\Filament\Admin\Concerns\ChecksFilamentNavigationGroupGate;
use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Concerns\HasReadOnlyGovernanceResource;
use App\Filament\Admin\Resources\HrTeams\Pages\CreateHrTeam;
use App\Filament\Admin\Resources\HrTeams\Pages\EditHrTeam;
use App\Filament\Admin\Resources\HrTeams\Pages\ListHrTeams;
use App\Models\Team;
use App\Models\User;
use App\Services\Governance\GovernanceAuditLogger;
use App\Services\Team\TeamService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HrTeamResource extends Resource
{
    use ChecksFilamentNavigationGroupGate;
    use HasGovernanceAuthorization;
    use HasReadOnlyGovernanceResource;

    protected static ?string $model = Team::class;

    protected static string $viewPermission = 'hr.teams.view';

    protected static ?string $slug = 'hr-teams';

    protected static ?string $navigationLabel = 'Teams';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string | \UnitEnum | null $navigationGroup = 'HR Oversight';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->maxLength(255)
                ->helperText('Optional. Auto-generated when omitted.'),
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->rows(4)
                ->maxLength(1000)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withTrashed()->with(['creator'])->withCount(['members', 'contracts'])->latest())
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('code')->searchable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('members_count')->label('Members')->sortable(),
                TextColumn::make('contracts_count')->label('Projects')->sortable(),
                TextColumn::make('creator.name')->label('Created By')->placeholder('-'),
                TextColumn::make('deleted_at')->label('Deleted')->since()->placeholder('-'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
                Action::make('deleteTeam')
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Team $record): bool => static::canDelete($record))
                    ->action(function (Team $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof User, 403);

                        app(GovernanceAuditLogger::class)->log('governance.hr.team.deleted', $record, [
                            'before' => [
                                'name' => $record->name,
                                'code' => $record->code,
                                'deleted_at' => null,
                            ],
                        ], $actor);

                        app(TeamService::class)->deleteTeam($record->id);
                    }),
                RestoreAction::make()
                    ->after(function (Team $record): void {
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            return;
                        }

                        app(GovernanceAuditLogger::class)->log('governance.hr.team.restored', $record, [
                            'after' => [
                                'name' => $record->name,
                                'code' => $record->code,
                            ],
                        ], $actor);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHrTeams::route('/'),
            'create' => CreateHrTeam::route('/create'),
            'edit' => EditHrTeam::route('/{record}/edit'),
        ];
    }

    protected static function createPermission(): ?string
    {
        return 'hr.teams.manage';
    }

    protected static function editPermission(): ?string
    {
        return 'hr.teams.manage';
    }

    protected static function deletePermission(): ?string
    {
        return 'hr.teams.manage';
    }

    protected static function restorePermission(): ?string
    {
        return 'hr.teams.manage';
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

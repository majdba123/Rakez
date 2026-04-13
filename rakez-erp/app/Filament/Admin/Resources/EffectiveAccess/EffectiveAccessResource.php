<?php

namespace App\Filament\Admin\Resources\EffectiveAccess;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\EffectiveAccess\Pages\ListEffectiveAccess;
use App\Filament\Admin\Resources\EffectiveAccess\Pages\ViewEffectiveAccess;
use App\Models\User;
use App\Services\Governance\EffectiveAccessSnapshotService;
use App\Services\Governance\GovernanceAccessService;
use App\Services\Governance\GovernanceCatalog;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EffectiveAccessResource extends Resource
{
    use HasGovernanceAuthorization;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'effective-access';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedEye;

    protected static ?string $navigationLabel = 'Effective Access';

    protected static string | \UnitEnum | null $navigationGroup = 'Governance Observability';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        $catalog = app(GovernanceCatalog::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withTrashed()->with(['roles', 'team'])->withCount('permissions'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('governance_roles')
                    ->label('Governance Roles')
                    ->state(fn (User $record): string => $record->roles->pluck('name')->intersect($catalog->allGovernanceRoles())->implode(', ') ?: '-')
                    ->wrap(),
                TextColumn::make('permissions_count')
                    ->label('Direct Permissions')
                    ->sortable(),
                IconColumn::make('panel_eligible')
                    ->label('Panel Eligible')
                    ->boolean()
                    ->state(fn (User $record): bool => app(GovernanceAccessService::class)->canAccessPanel($record)),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                IconColumn::make('is_trashed')
                    ->label('Deleted')
                    ->boolean()
                    ->state(fn (User $record): bool => method_exists($record, 'trashed') && $record->trashed()),
            ])
            ->filters([
                SelectFilter::make('governance_role')
                    ->label('Governance Role')
                    ->options(fn (): array => $catalog->governanceRoleOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'])
                        ? $query->role($data['value'])
                        : $query),
                SelectFilter::make('type')
                    ->label('User Type')
                    ->options($catalog->userTypeOptions()),
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('panel_eligible')
                    ->label('Panel Eligible')
                    ->queries(
                        true: fn (Builder $query): Builder => static::applyPanelEligibleFilter($query),
                        false: fn (Builder $query): Builder => static::applyPanelIneligibleFilter($query),
                    ),
                TernaryFilter::make('has_direct_permissions')
                    ->label('Has Direct Permissions')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->has('permissions'),
                        false: fn (Builder $query): Builder => $query->doesntHave('permissions'),
                    ),
                TrashedFilter::make(),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $catalog = app(GovernanceCatalog::class);

        return $schema->components([
            Section::make('Identity')
                ->schema([
                    TextEntry::make('name'),
                    TextEntry::make('email'),
                    TextEntry::make('type')
                        ->badge(),
                    TextEntry::make('team.name')
                        ->label('Team')
                        ->placeholder('No team'),
                    IconEntry::make('is_active')
                        ->label('Active')
                        ->boolean(),
                    IconEntry::make('panel_eligible')
                        ->label('Panel Eligible')
                        ->state(fn (User $record): bool => app(GovernanceAccessService::class)->canAccessPanel($record))
                        ->boolean(),
                ])
                ->columns(2),
            Section::make('Access Summary')
                ->schema([
                    KeyValueEntry::make('effective_access_summary')
                        ->state(fn (User $record): array => static::summaryState($record))
                        ->columnSpanFull(),
                    TextEntry::make('snapshot_text')
                        ->label('Copyable Summary')
                        ->state(fn (User $record): string => app(EffectiveAccessSnapshotService::class)->summaryForUser($record))
                        ->copyable()
                        ->columnSpanFull(),
                ]),
            Section::make('Role Breakdown')
                ->schema([
                    TextEntry::make('legacy_roles')
                        ->label('Legacy Roles')
                        ->state(fn (User $record): array => app(EffectiveAccessSnapshotService::class)->forUser($record)['legacy_roles'])
                        ->listWithLineBreaks()
                        ->placeholder('None')
                        ->columnSpanFull(),
                    TextEntry::make('governance_roles')
                        ->label('Governance Roles')
                        ->state(fn (User $record): array => app(EffectiveAccessSnapshotService::class)->forUser($record)['governance_roles'])
                        ->listWithLineBreaks()
                        ->placeholder('None')
                        ->columnSpanFull(),
                ]),
            Section::make('Permission Breakdown')
                ->schema([
                    TextEntry::make('direct_permissions')
                        ->label('Direct Permissions (assigned explicitly)')
                        ->state(fn (User $record): array => static::describePermissions(
                            app(EffectiveAccessSnapshotService::class)->forUser($record)['direct_permissions'],
                            $catalog,
                        ))
                        ->listWithLineBreaks()
                        ->limitList(20)
                        ->expandableLimitedList()
                        ->placeholder('None')
                        ->columnSpanFull(),
                    TextEntry::make('inherited_permissions')
                        ->label('Inherited Permissions (from roles)')
                        ->state(fn (User $record): array => static::describePermissions(
                            app(EffectiveAccessSnapshotService::class)->forUser($record)['inherited_permissions'],
                            $catalog,
                        ))
                        ->listWithLineBreaks()
                        ->limitList(20)
                        ->expandableLimitedList()
                        ->placeholder('None')
                        ->columnSpanFull(),
                    TextEntry::make('dynamic_permissions')
                        ->label('Dynamic Permissions (manager / runtime)')
                        ->state(fn (User $record): array => static::describePermissions(
                            app(EffectiveAccessSnapshotService::class)->forUser($record)['dynamic_permissions'],
                            $catalog,
                        ))
                        ->listWithLineBreaks()
                        ->limitList(20)
                        ->expandableLimitedList()
                        ->placeholder('None')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEffectiveAccess::route('/'),
            'view' => ViewEffectiveAccess::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return static::canAccessGovernancePage('Governance Observability', 'admin.effective_access.view');
    }

    public static function canView(Model $record): bool
    {
        return static::canAccessGovernancePage('Governance Observability', 'admin.effective_access.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function applyPanelEligibleFilter(Builder $query): Builder
    {
        $managedRoles = config('governance.managed_panel_roles', []);
        $superAdminRole = config('governance.super_admin_role');
        $panelPermission = config('governance.panel_access_permission');

        return $query
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereHas('roles', fn (Builder $q) => $q->whereIn('name', $managedRoles))
            ->where(function (Builder $q) use ($superAdminRole, $panelPermission): void {
                $q->whereHas('roles', fn (Builder $r) => $r->where('name', $superAdminRole))
                    ->orWhereHas('permissions', fn (Builder $p) => $p->where('name', $panelPermission))
                    ->orWhereHas('roles.permissions', fn (Builder $p) => $p->where('name', $panelPermission));
            });
    }

    public static function applyPanelIneligibleFilter(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('is_active', false)
                ->orWhereNotNull('deleted_at')
                ->orWhereDoesntHave('roles', fn (Builder $r) => $r->whereIn('name', config('governance.managed_panel_roles', [])))
                ->orWhere(function (Builder $inner): void {
                    $inner->whereDoesntHave('roles', fn (Builder $r) => $r->where('name', config('governance.super_admin_role')))
                        ->whereDoesntHave('permissions', fn (Builder $p) => $p->where('name', config('governance.panel_access_permission')))
                        ->whereDoesntHave('roles.permissions', fn (Builder $p) => $p->where('name', config('governance.panel_access_permission')));
                });
        });
    }

    protected static function summaryState(User $record): array
    {
        $snapshot = app(EffectiveAccessSnapshotService::class)->forUser($record);

        return [
            'Legacy roles' => (string) count($snapshot['legacy_roles']),
            'Governance roles' => (string) count($snapshot['governance_roles']),
            'Direct permissions' => (string) count($snapshot['direct_permissions']),
            'Inherited permissions' => (string) count($snapshot['inherited_permissions']),
            'Dynamic permissions' => (string) count($snapshot['dynamic_permissions']),
            'Panel eligible' => $snapshot['panel_eligible'] ? 'Yes' : 'No',
        ];
    }

    /**
     * @param  string[]  $permissions
     * @return string[]
     */
    protected static function describePermissions(array $permissions, GovernanceCatalog $catalog): array
    {
        return array_map(function (string $perm) use ($catalog): string {
            $desc = $catalog->permissionDescription($perm);

            return $desc ? "{$perm} — {$desc}" : $perm;
        }, $permissions);
    }
}

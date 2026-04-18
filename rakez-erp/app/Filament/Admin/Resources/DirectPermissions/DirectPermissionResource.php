<?php

namespace App\Filament\Admin\Resources\DirectPermissions;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\DirectPermissions\Pages\EditDirectPermissions;
use App\Filament\Admin\Resources\DirectPermissions\Pages\ListDirectPermissions;
use App\Models\User;
use App\Services\Governance\EffectiveAccessSnapshotService;
use App\Services\Governance\GovernanceAccessService;
use App\Services\Governance\GovernanceCatalog;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DirectPermissionResource extends Resource
{
    use HasGovernanceAuthorization;

    protected static ?string $model = User::class;

    protected static ?string $slug = 'direct-permissions';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $navigationLabel = 'Direct Permissions';

    protected static string | \UnitEnum | null $navigationGroup = 'Access Governance';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Placeholder::make('user_name')
                ->label(__('filament-admin.resources.direct_permissions.fields.user'))
                ->content(fn (?User $record): string => $record?->name ?? '-'),
            Placeholder::make('user_email')
                ->label(__('filament-admin.resources.direct_permissions.fields.email'))
                ->content(fn (?User $record): string => $record?->email ?? '-'),
            Placeholder::make('role_summary')
                ->label(__('filament-admin.resources.direct_permissions.fields.current_roles'))
                ->content(fn (?User $record): string => $record
                    ? implode(', ', app(GovernanceCatalog::class)->displayRoleLabels($record->roles->pluck('name')->values()->all()))
                    : '-'),
            Select::make('direct_permissions')
                ->label(__('filament-admin.resources.direct_permissions.fields.direct_permissions'))
                ->multiple()
                ->searchable()
                ->preload()
                ->options(app(GovernanceCatalog::class)->groupedPermissionOptions()),
            Placeholder::make('effective_access_summary')
                ->label(__('filament-admin.resources.direct_permissions.fields.effective_access'))
                ->content(fn (?User $record): string => $record ? app(EffectiveAccessSnapshotService::class)->summaryForUser($record) : '-')
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withTrashed()->with(['roles'])->withCount('permissions'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-admin.resources.direct_permissions.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('filament-admin.resources.direct_permissions.columns.email'))
                    ->searchable(),
                TextColumn::make('permissions_count')
                    ->label(__('filament-admin.resources.direct_permissions.columns.direct_permissions'))
                    ->sortable(),
                TextColumn::make('roles_summary')
                    ->label(__('filament-admin.resources.direct_permissions.columns.roles'))
                    ->state(fn (User $record): string => implode(', ', app(GovernanceCatalog::class)->displayRoleLabels(
                        $record->roles->pluck('name')->values()->all()
                    )) ?: '-'),
                IconColumn::make('panel_eligible')
                    ->label(__('filament-admin.resources.direct_permissions.columns.panel_access'))
                    ->boolean()
                    ->state(fn (User $record): bool => app(GovernanceAccessService::class)->canAccessPanel($record)),
                IconColumn::make('is_trashed')
                    ->label(__('filament-admin.resources.direct_permissions.columns.deleted'))
                    ->boolean()
                    ->state(fn (User $record): bool => method_exists($record, 'trashed') && $record->trashed()),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDirectPermissions::route('/'),
            'edit' => EditDirectPermissions::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-admin.resources.direct_permissions.navigation_label');
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
        return static::canAccessGovernancePage('Access Governance', 'admin.direct_permissions.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        if (! static::canGovernanceMutation('admin.direct_permissions.manage')) {
            return false;
        }

        if (! $record instanceof User || (method_exists($record, 'trashed') && $record->trashed())) {
            return false;
        }

        $superAdminRole = config('governance.super_admin_role', 'super_admin');
        $actor = auth()->user();

        if ($record->hasRole($superAdminRole)) {
            return $actor instanceof User && $actor->hasRole($superAdminRole);
        }

        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}

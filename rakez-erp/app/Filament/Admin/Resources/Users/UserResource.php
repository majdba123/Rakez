<?php

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\Team;
use App\Models\User;
use App\Services\Governance\EffectiveAccessSnapshotService;
use App\Services\Governance\GovernanceCatalog;
use App\Services\Governance\UserGovernanceService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

class UserResource extends Resource
{
    use HasGovernanceAuthorization;

    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Users';

    protected static string | \UnitEnum | null $navigationGroup = 'Access Governance';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        $catalog = app(GovernanceCatalog::class);

        return $schema->components([
            TextInput::make('name')
                ->label(__('filament-admin.resources.users.fields.name'))
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label(__('filament-admin.resources.users.fields.email'))
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('phone')
                ->label(__('filament-admin.resources.users.fields.phone'))
                ->tel()
                ->maxLength(20)
                ->unique(ignoreRecord: true),
            TextInput::make('password')
                ->label(__('filament-admin.resources.users.fields.password'))
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->minLength(8)
                ->dehydrated(fn (?string $state): bool => filled($state)),
            Select::make('type')
                ->label(__('filament-admin.resources.users.fields.type'))
                ->required()
                ->searchable()
                ->options(fn (?User $record): array => $catalog->assignableUserTypeOptions($record?->type))
                ->helperText(__('filament-admin.resources.users.helper.legacy_admin')),
            Toggle::make('is_manager')
                ->label(__('filament-admin.resources.users.fields.manager'))
                ->default(false),
            Select::make('team_id')
                ->label(__('filament-admin.resources.users.fields.team'))
                ->searchable()
                ->preload()
                ->options(fn (): array => Team::query()->orderBy('name')->pluck('name', 'id')->all()),
            Toggle::make('is_active')
                ->label(__('filament-admin.resources.users.fields.active'))
                ->default(true),
            Select::make('additional_roles')
                ->label(__('filament-admin.resources.users.fields.additional_roles'))
                ->multiple()
                ->searchable()
                ->preload()
                ->options($catalog->assignableOperationalRoleOptions())
                ->helperText(__('filament-admin.resources.users.helper.additional_roles')),
            Select::make('governance_roles')
                ->label(__('filament-admin.resources.users.fields.admin_roles'))
                ->multiple()
                ->searchable()
                ->preload()
                ->options(fn (): array => $catalog->assignableGovernanceRoleOptions(auth()->user()))
                ->helperText(__('filament-admin.resources.users.helper.admin_roles')),
            Select::make('direct_permissions')
                ->label(__('filament-admin.resources.users.fields.direct_permissions'))
                ->multiple()
                ->searchable()
                ->preload()
                ->options($catalog->groupedPermissionOptions())
                ->helperText(__('filament-admin.resources.users.helper.direct_permissions'))
                ->columnSpanFull(),
            Placeholder::make('effective_access_summary')
                ->label(__('filament-admin.resources.users.fields.effective_access'))
                ->content(fn (?User $record): string => $record ? app(EffectiveAccessSnapshotService::class)->summaryForUser($record) : __('filament-admin.resources.users.helper.effective_access_after_create'))
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withTrashed()->with(['roles', 'team'])->withCount('permissions'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-admin.resources.users.columns.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('filament-admin.resources.users.columns.email'))
                    ->searchable(),
                TextColumn::make('type')
                    ->label(__('filament-admin.resources.users.columns.type'))
                    ->badge(),
                IconColumn::make('is_manager')
                    ->label(__('filament-admin.resources.users.columns.manager'))
                    ->boolean(),
                TextColumn::make('team.name')
                    ->label(__('filament-admin.resources.users.columns.team'))
                    ->toggleable(),
                TextColumn::make('governance_roles')
                    ->label(__('filament-admin.resources.users.columns.governance_roles'))
                    ->state(fn (User $record): string => implode(', ', app(GovernanceCatalog::class)->displayRoleLabels(
                        $record->roles->pluck('name')->intersect(config('governance.managed_governance_roles'))->values()->all()
                    )) ?: '-'),
                TextColumn::make('permissions_count')
                    ->label(__('filament-admin.resources.users.columns.direct_permissions'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('filament-admin.resources.users.columns.active'))
                    ->boolean(),
                TextColumn::make('deleted_at')
                    ->label(__('filament-admin.resources.users.columns.deleted'))
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (User $record): bool => static::canEdit($record)),
                DeleteAction::make('deleteUser')
                    ->visible(fn (User $record): bool => static::canDelete($record))
                    ->action(function (User $record): void {
                        app(UserGovernanceService::class)->delete($record);
                    }),
                RestoreAction::make('restoreUser')
                    ->visible(fn (User $record): bool => static::canRestore($record))
                    ->action(function (User $record): void {
                        app(UserGovernanceService::class)->restore($record);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-admin.resources.users.navigation_label');
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
        if (! static::canAccessGovernancePage('Access Governance', 'admin.users.view')) {
            return false;
        }

        $actor = auth()->user();
        $superAdminRole = config('governance.super_admin_role', 'super_admin');

        return $actor instanceof User && $actor->hasRole($superAdminRole);
    }

    public static function canCreate(): bool
    {
        return static::canGovernanceMutation('admin.users.manage');
    }

    public static function canEdit(Model $record): bool
    {
        if (! static::canGovernanceMutation('admin.users.manage')) {
            return false;
        }

        if ($record instanceof User && method_exists($record, 'trashed') && $record->trashed()) {
            return false;
        }

        return ! static::isProtectedSuperAdmin($record);
    }

    public static function canDelete(Model $record): bool
    {
        if (! static::canGovernanceMutation('admin.users.manage')) {
            return false;
        }

        if ($record instanceof User && method_exists($record, 'trashed') && $record->trashed()) {
            return false;
        }

        return ! static::isProtectedSuperAdmin($record);
    }

    public static function canRestore(Model $record): bool
    {
        if (! static::canGovernanceMutation('admin.users.manage')) {
            return false;
        }

        if (! ($record instanceof User) || ! method_exists($record, 'trashed') || ! $record->trashed()) {
            return false;
        }

        return ! static::isProtectedSuperAdmin($record);
    }

    protected static function isProtectedSuperAdmin(Model $record): bool
    {
        $superAdminRole = config('governance.super_admin_role', 'super_admin');

        if (! $record instanceof User || ! $record->hasRole($superAdminRole)) {
            return false;
        }

        $actor = auth()->user();

        return ! ($actor instanceof User && $actor->hasRole($superAdminRole));
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }
}

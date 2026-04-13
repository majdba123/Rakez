<?php

namespace App\Filament\Admin\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Default-deny mutations unless the concrete resource overrides
 * {@see createPermission()}, {@see editPermission()}, etc., with real panel permissions.
 */
trait HasReadOnlyGovernanceResource
{
    protected static function createPermission(): ?string
    {
        return null;
    }

    protected static function editPermission(): ?string
    {
        return null;
    }

    protected static function deletePermission(): ?string
    {
        return null;
    }

    protected static function deleteAnyPermission(): ?string
    {
        return null;
    }

    protected static function restorePermission(): ?string
    {
        return null;
    }

    public static function canViewAny(): bool
    {
        return static::canGovernance(static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canGovernance(static::$viewPermission);
    }

    public static function canCreate(): bool
    {
        return static::canGovernanceMutation(static::createPermission());
    }

    public static function canEdit(Model $record): bool
    {
        return static::canGovernanceMutation(static::editPermission());
    }

    public static function canDelete(Model $record): bool
    {
        return static::canGovernanceMutation(static::deletePermission());
    }

    public static function canDeleteAny(): bool
    {
        return static::canGovernanceMutation(static::deleteAnyPermission() ?? static::deletePermission());
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return static::canGovernanceMutation(static::restorePermission() ?? static::deletePermission());
    }
}

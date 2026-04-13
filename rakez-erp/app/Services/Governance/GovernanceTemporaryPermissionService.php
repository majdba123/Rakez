<?php

namespace App\Services\Governance;

use App\Models\GovernanceTemporaryPermission;
use App\Models\User;
use Illuminate\Support\Collection;

class GovernanceTemporaryPermissionService
{
    public function __construct(
        protected GovernanceAuditLogger $auditLogger,
        protected GovernanceCatalog $catalog,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('governance.temporary_permissions.enabled', false);
    }

    /**
     * @return Collection<int, string>
     */
    public function activePermissionNamesForUser(User $user): Collection
    {
        if (! $this->isEnabled()) {
            return collect();
        }

        return GovernanceTemporaryPermission::query()
            ->where('user_id', $user->getKey())
            ->active()
            ->pluck('permission');
    }

    public function userHasActiveTemporary(User $user, string $permission): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return GovernanceTemporaryPermission::query()
            ->where('user_id', $user->getKey())
            ->where('permission', $permission)
            ->active()
            ->exists();
    }

    public function grant(User $subject, string $permission, User $actor, \DateTimeInterface $expiresAt, ?string $reason = null): GovernanceTemporaryPermission
    {
        if (! $this->isEnabled()) {
            throw new \DomainException('Temporary governance permissions are disabled.');
        }

        if (! $this->catalog->isKnownPermission($permission)) {
            throw new \InvalidArgumentException('Unknown permission name: '.$permission);
        }

        if (! $this->catalog->isActivePanelPermission($permission)) {
            throw new \InvalidArgumentException(
                'Cannot grant a permission that is not an active panel permission: '.$permission
            );
        }

        $row = GovernanceTemporaryPermission::query()->create([
            'user_id' => $subject->getKey(),
            'permission' => $permission,
            'granted_by_id' => $actor->getKey(),
            'reason' => $reason,
            'expires_at' => $expiresAt,
        ]);

        $this->auditLogger->log('governance.temp_permission.granted', $row, [
            'subject_user_id' => $subject->getKey(),
            'permission' => $permission,
            'expires_at' => $expiresAt->format(\DateTimeInterface::ATOM),
        ], $actor);

        return $row;
    }

    public function revoke(GovernanceTemporaryPermission $row, User $actor): void
    {
        if (! $this->isEnabled()) {
            throw new \DomainException('Temporary governance permissions are disabled.');
        }

        if ($row->revoked_at !== null) {
            return;
        }

        $row->forceFill(['revoked_at' => now()])->save();

        $this->auditLogger->log('governance.temp_permission.revoked', $row, [
            'subject_user_id' => $row->user_id,
            'permission' => $row->permission,
        ], $actor);
    }

    public function expireDueRows(): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $ids = GovernanceTemporaryPermission::query()
            ->whereNull('revoked_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        $count = GovernanceTemporaryPermission::query()
            ->whereIn('id', $ids)
            ->update(['revoked_at' => now()]);

        $this->auditLogger->log('governance.temp_permission.expired_batch', 'GovernanceTemporaryPermission', [
            'count' => $count,
            'ids' => array_slice($ids, 0, 200),
        ], null);

        return $count;
    }
}

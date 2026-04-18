<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Access\FrontendAccessProfileService;
use App\Services\Governance\GovernanceCatalog;

class AuthenticatedUserPayloadService
{
    public function __construct(
        protected FrontendAccessProfileService $frontendAccess,
        protected GovernanceCatalog $catalog,
    ) {}

    public function contract(User $user): array
    {
        $roles = $user->getRoleNames()->values()->all();

        return [
            'user' => $this->safeUser($user),
            'roles' => $roles,
            'roles_display' => $this->catalog->displayRoles($roles),
            'permissions' => $this->getAllPermissionsForUser($user),
            // Preserved for compatibility with existing consumers while /api/access/profile exists.
            'access' => $this->frontendAccess->build($user),
        ];
    }

    public function currentUserResponse(User $user): array
    {
        $contract = $this->contract($user);

        return [
            ...$contract['user'],
            'user' => $contract['user'],
            'roles' => $contract['roles'],
            'roles_display' => $contract['roles_display'],
            'permissions' => $contract['permissions'],
            'access' => $contract['access'],
        ];
    }

    protected function safeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'type' => $user->type,
            'is_manager' => $user->is_manager,
            'team_id' => $user->team_id,
            'job_title' => $user->job_title,
            'department' => $user->department,
            'is_active' => $user->is_active,
        ];
    }

    /**
     * Get all permissions for a user, ensuring fresh data bypasses stale relations.
     * Directly queries permissions and roles to guarantee up-to-date results.
     */
    protected function getAllPermissionsForUser(User $user): array
    {
        // Refresh user to clear any stale cached relations
        $user = $user->fresh();

        // Get effective permissions (includes role + direct + dynamic + temporary)
        return $user->getEffectivePermissions();
    }
}

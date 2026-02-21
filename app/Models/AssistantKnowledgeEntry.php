<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantKnowledgeEntry extends Model
{
    protected $table = 'assistant_knowledge_entries';

    protected $fillable = [
        'module',
        'page_key',
        'title',
        'content_md',
        'tags',
        'roles',
        'permissions',
        'language',
        'is_active',
        'priority',
        'updated_by',
    ];

    protected $casts = [
        'tags' => 'array',
        'roles' => 'array',
        'permissions' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to filter only active entries.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter entries by module and page_key.
     * If page_key is provided, include entries where page_key is null OR equals provided page_key.
     * Also filter by module if provided.
     */
    public function scopeForPage(Builder $query, ?string $module, ?string $pageKey): Builder
    {
        if ($module) {
            $query->where('module', $module);
        }

        if ($pageKey) {
            $query->where(function (Builder $q) use ($pageKey) {
                $q->whereNull('page_key')
                  ->orWhere('page_key', $pageKey);
            });
        }

        return $query;
    }

    /**
     * Scope to filter entries visible to a given user based on roles and permissions.
     * - Roles: if roles is null or empty => pass; else user must have at least one role in roles.
     * - Permissions: if permissions is null or empty => pass; else user must have at least one permission in permissions.
     */
    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        $userRoles = $this->getUserRoles($user);
        $userPermissions = $this->getUserPermissions($user);

        return $query->where(function (Builder $q) use ($userRoles, $userPermissions) {
            // Roles check: null/empty OR user has at least one matching role
            $q->where(function (Builder $roleQuery) use ($userRoles) {
                $roleQuery->whereNull('roles')
                    ->orWhereJsonLength('roles', 0);

                foreach ($userRoles as $role) {
                    $roleQuery->orWhereJsonContains('roles', $role);
                }
            });

            // Permissions check: null/empty OR user has at least one matching permission
            $q->where(function (Builder $permQuery) use ($userPermissions) {
                $permQuery->whereNull('permissions')
                    ->orWhereJsonLength('permissions', 0);

                foreach ($userPermissions as $perm) {
                    $permQuery->orWhereJsonContains('permissions', $perm);
                }
            });
        });
    }

    /**
     * Get user role names safely.
     */
    private function getUserRoles(User $user): array
    {
        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->toArray();
        }

        return [];
    }

    /**
     * Get user permission names safely.
     */
    private function getUserPermissions(User $user): array
    {
        if (method_exists($user, 'getAllPermissions')) {
            return $user->getAllPermissions()->pluck('name')->toArray();
        }

        return [];
    }
}


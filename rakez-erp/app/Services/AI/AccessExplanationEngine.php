<?php

namespace App\Services\AI;

use App\Models\User;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Model;

class AccessExplanationEngine
{
    /**
     * Map keywords to model classes.
     */
    private array $modelMap = [
        'contract' => Contract::class,
        'unit' => ContractUnit::class,
        'reservation' => SalesReservation::class,
    ];

    /**
     * Detect intent and provide an access explanation if applicable.
     */
    public function explain(User $user, string $message): ?array
    {
        if (!$this->isAccessIntent($message)) {
            return null;
        }

        $parsed = $this->parseMessage($message);

        if (!$parsed['resource_type'] || !$parsed['resource_id']) {
            return $this->buildResult(
                false,
                'invalid_request',
                'I detected you are asking about an access issue, but I need a specific resource type (e.g., contract, unit, reservation) and an ID.',
                ['Please provide the resource type and ID.']
            );
        }

        $modelClass = $this->modelMap[$parsed['resource_type']] ?? null;
        if (!$modelClass) {
            return $this->buildResult(
                false,
                'invalid_request',
                "I don't recognize the resource type '{$parsed['resource_type']}'.",
                ['Please specify if it is a contract, unit, or reservation.']
            );
        }

        return $this->runAuthChecks($user, $modelClass, $parsed['resource_id'], $parsed['action']);
    }

    /**
     * Check if the message matches "why can't I" intent.
     */
    private function isAccessIntent(string $message): bool
    {
        $patterns = [
            '/why (?:can\'t|cannot) i/i',
            '/i (?:can\'t|cannot) (?:see|view|edit|update|delete|open)/i',
            '/403/i',
            '/access denied/i',
            '/لماذا لا أستطيع/u', // Arabic variant
            '/ليس لدي صلاحية/u', // Arabic variant
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract resource type, ID, and action from the message.
     */
    private function parseMessage(string $message): array
    {
        $type = null;
        $id = null;
        $action = 'view';

        // Extract ID (first integer found)
        if (preg_match('/\d+/', $message, $matches)) {
            $id = (int) $matches[0];
        }

        // Extract Type
        foreach ($this->modelMap as $keyword => $class) {
            if (stripos($message, $keyword) !== false) {
                $type = $keyword;
                break;
            }
        }

        // Extract Action
        if (preg_match('/edit|update|change|تعديل/i', $message)) {
            $action = 'update';
        } elseif (preg_match('/delete|remove|حذف/i', $message)) {
            $action = 'delete';
        }

        return [
            'resource_type' => $type,
            'resource_id' => $id,
            'action' => $action,
        ];
    }

    /**
     * Run the layered authorization checks.
     */
    private function runAuthChecks(User $user, string $modelClass, int $id, string $action): array
    {
        // 1. Type-level check (Neutral denial if fails)
        if (!$this->hasTypeLevelAccess($user, $modelClass)) {
            return $this->buildResult(
                false,
                'resource_not_found_or_not_allowed',
                "We can't verify this resource or you don't have access to it.",
                ['Request manager access if you believe this is an error.']
            );
        }

        // 2. Existence check
        $instance = $modelClass::find($id);
        if (!$instance) {
            return $this->buildResult(
                false,
                'resource_not_found',
                'The requested resource could not be found.',
                ['Double check the ID provided.']
            );
        }

        // 3. Instance Policy Check
        if (Gate::forUser($user)->allows($action, $instance)) {
            return $this->buildResult(
                true,
                'allowed',
                'You have permission to perform this action.',
                ['You can proceed with your request.']
            );
        }

        // 4. Ownership/Scope Inference
        $reasonCode = 'policy_denied';
        $steps = ['Contact your administrator to request access.'];

        if ($this->isOwnershipMismatch($user, $instance)) {
            $reasonCode = 'ownership_mismatch';
            $steps = ['Confirm you are assigned to this resource or on the correct team.'];
        }

        return $this->buildResult(
            false,
            $reasonCode,
            'Your current permissions do not allow this action on this specific resource.',
            $steps
        );
    }

    /**
     * Check if user has general access to the model type.
     */
    private function hasTypeLevelAccess(User $user, string $modelClass): bool
    {
        // Check viewAny policy if it exists
        if (Gate::forUser($user)->check('viewAny', $modelClass)) {
            return true;
        }

        // Fallback to model-specific permissions based on project patterns
        $permissionMap = [
            Contract::class => ['contracts.view', 'contracts.view_all'],
            ContractUnit::class => ['units.view'],
            SalesReservation::class => ['sales.reservations.view'],
        ];

        $perms = $permissionMap[$modelClass] ?? [];
        foreach ($perms as $perm) {
            if ($user->can($perm)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the denial is likely due to ownership mismatch.
     */
    private function isOwnershipMismatch(User $user, Model $instance): bool
    {
        // Contract ownership
        if ($instance instanceof Contract && isset($instance->user_id)) {
            return $instance->user_id !== $user->id;
        }

        // Reservation ownership
        if ($instance instanceof SalesReservation && isset($instance->marketing_employee_id)) {
            return $instance->marketing_employee_id !== $user->id;
        }

        return false;
    }

    /**
     * Build a standardized result array.
     */
    private function buildResult(bool $allowed, string $reasonCode, string $message, array $steps): array
    {
        return [
            'allowed' => $allowed,
            'reason_code' => $reasonCode,
            'message' => $message,
            'steps' => $steps,
        ];
    }
}

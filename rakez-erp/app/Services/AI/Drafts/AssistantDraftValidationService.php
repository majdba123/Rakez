<?php

namespace App\Services\AI\Drafts;

use App\Http\Requests\Credit\StoreCreditClientContactRequest;
use App\Http\Requests\Marketing\StoreLeadRequest;
use App\Http\Requests\Marketing\StoreMarketingTaskRequest;
use App\Http\Requests\Sales\StoreReservationActionRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as LaravelValidator;

class AssistantDraftValidationService
{
    /**
     * @param  array<string, mixed>  $flow
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function preview(User $user, array $flow, array $payload): array
    {
        $requestClass = $flow['validation_request'];
        $request = new $requestClass;

        $validator = Validator::make(
            $payload,
            $request->rules(),
            method_exists($request, 'messages') ? $request->messages() : []
        );

        if ($requestClass === StoreTaskRequest::class) {
            $this->applyTaskCrossFieldChecks($validator, $payload);
        }

        if ($requestClass === StoreReservationActionRequest::class) {
            $this->applyReservationActionNormalization($validator, $payload);
        }

        if ($requestClass === StoreLeadRequest::class) {
            $this->applyLeadCrossFieldChecks($validator, $payload);
        }

        if ($requestClass === StoreCreditClientContactRequest::class) {
            $this->applyCreditClientContactChecks($validator, $payload);
        }

        $errors = $validator->fails() ? $validator->errors()->toArray() : [];

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'validated_fields' => array_keys($request->rules()),
            'validation_source' => $requestClass,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyTaskCrossFieldChecks(LaravelValidator $validator, array $payload): void
    {
        $validator->after(function (LaravelValidator $validator) use ($payload): void {
            $assignedTo = $payload['assigned_to'] ?? null;
            $section = $payload['section'] ?? null;

            if (! $assignedTo || ! $section) {
                return;
            }

            $assignee = User::find($assignedTo);
            if (! $assignee) {
                return;
            }

            if ($assignee->type === null) {
                $validator->errors()->add('assigned_to', 'Selected user does not have a section/type.');

                return;
            }

            if ($assignee->type !== $section) {
                $validator->errors()->add('assigned_to', 'Selected user does not belong to the chosen section.');
            }
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyReservationActionNormalization(LaravelValidator $validator, array &$payload): void
    {
        $map = [
            'استقطاب' => 'lead_acquisition',
            'اكتساب العملاء' => 'lead_acquisition',
            'إقناع' => 'persuasion',
            'الإقناع' => 'persuasion',
            'إغلاق' => 'closing',
            'الإغلاق' => 'closing',
            'اغلاق الصفقة' => 'closing',
        ];

        $actionType = $payload['action_type'] ?? null;
        if (is_string($actionType) && isset($map[$actionType])) {
            $payload['action_type'] = $map[$actionType];
            $validator->setData($payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyLeadCrossFieldChecks(LaravelValidator $validator, array $payload): void
    {
        $validator->after(function (LaravelValidator $validator) use ($payload): void {
            $assignedTo = $payload['assigned_to'] ?? null;

            if (! $assignedTo) {
                return;
            }

            $assignee = User::find($assignedTo);
            if (! $assignee) {
                return;
            }

            if ($assignee->type !== 'marketing') {
                $validator->errors()->add('assigned_to', 'Selected lead assignee must be an active marketing user.');
            }
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyCreditClientContactChecks(LaravelValidator $validator, array $payload): void
    {
        $validator->after(function (LaravelValidator $validator) use ($payload): void {
            if (empty($payload['sales_reservation_id'])) {
                $validator->errors()->add('sales_reservation_id', 'An explicit reservation id is required for credit follow-up drafts.');
            }
        });
    }
}

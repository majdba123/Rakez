<?php

namespace App\Services\AI\Skills\Handlers;

use App\Models\User;
use App\Services\AI\Skills\Contracts\SkillHandlerContract;
use App\Services\Credit\CreditFinancingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CreditFinancingSkillHandler implements SkillHandlerContract
{
    public function __construct(
        private readonly CreditFinancingService $financingService,
    ) {}

    public function execute(User $user, array $definition, array $input, array $context): array
    {
        $reservationId = (int) ($input['reservation_id'] ?? 0);

        try {
            $details = $this->financingService->getTrackerDetailsByReservationIdForAiSkill($reservationId, $user);
        } catch (ModelNotFoundException) {
            return [
                'status' => 'not_found',
                'message' => 'The requested financing tracker could not be found.',
                'reason' => 'credit_financing.not_found',
            ];
        } catch (AuthorizationException) {
            return [
                'status' => 'denied',
                'message' => 'You do not have access to this financing tracker.',
                'reason' => 'credit_financing.forbidden',
                'access_notes' => [
                    'had_denied_request' => true,
                    'reason' => 'credit_financing.forbidden',
                ],
            ];
        }

        $tracker = $details['financing'];
        $reservation = $tracker->reservation;
        $data = [
            'reservation_id' => $reservation?->id,
            'tracker_id' => $tracker->id,
            'overall_status' => $tracker->overall_status,
            'current_stage' => $details['current_stage'],
            'remaining_days' => $details['remaining_days'],
            'all_completed' => $details['all_completed'],
            'assigned_to' => $tracker->assignedUser?->name,
            'bank_name' => $tracker->bank_name,
            'client_salary' => $tracker->client_salary,
            'employment_type' => $tracker->employment_type,
            'is_supported_bank' => $tracker->is_supported_bank,
            'rejection_reason' => $tracker->rejection_reason,
            'reservation_status' => $reservation?->status,
            'credit_status' => $reservation?->credit_status,
            'client_name' => $reservation?->client_name,
            'client_mobile' => $reservation?->client_mobile,
            'project_name' => $reservation?->contract?->project_name,
            'unit_number' => $reservation?->contractUnit?->unit_number,
            'progress_summary' => $details['progress_summary'],
            'created_at' => $tracker->created_at?->toDateTimeString(),
        ];

        return [
            'status' => 'ok',
            'data' => $data,
            'sources' => [
                [
                    'type' => 'record',
                    'title' => 'Credit Financing Reservation #'.$reservationId,
                    'ref' => 'credit_financing:'.$reservationId,
                ],
            ],
            'confidence' => 'high',
            'access_notes' => [
                'had_denied_request' => false,
                'reason' => '',
            ],
        ];
    }
}

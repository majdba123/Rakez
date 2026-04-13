<?php

namespace App\Services\AI\Skills\Handlers;

use App\Models\User;
use App\Services\AI\Skills\Contracts\SkillHandlerContract;
use App\Services\Sales\SalesReservationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SalesReservationSkillHandler implements SkillHandlerContract
{
    public function __construct(
        private readonly SalesReservationService $reservationService,
    ) {}

    public function execute(User $user, array $definition, array $input, array $context): array
    {
        $reservationId = (int) ($input['reservation_id'] ?? 0);

        try {
            $reservation = $this->reservationService->getReservationForAiSkill($reservationId, $user);
        } catch (ModelNotFoundException) {
            return [
                'status' => 'not_found',
                'message' => 'The requested reservation could not be found.',
                'reason' => 'reservation.not_found',
            ];
        } catch (AuthorizationException) {
            return [
                'status' => 'denied',
                'message' => 'You do not have access to this reservation.',
                'reason' => 'reservation.forbidden',
                'access_notes' => [
                    'had_denied_request' => true,
                    'reason' => 'reservation.forbidden',
                ],
            ];
        }

        $data = [
            'id' => $reservation->id,
            'status' => $reservation->status,
            'reservation_type' => $reservation->reservation_type,
            'project_name' => $reservation->contract?->project_name,
            'unit_number' => $reservation->contractUnit?->unit_number,
            'marketer_name' => $reservation->marketingEmployee?->name,
            'contract_date' => $reservation->contract_date?->toDateString(),
            'approval_deadline' => $reservation->approval_deadline?->toDateTimeString(),
            'client_name' => $reservation->client_name,
            'client_mobile' => $reservation->client_mobile,
            'client_iban' => $reservation->client_iban,
            'payment_method' => $reservation->payment_method,
            'down_payment_amount' => $reservation->down_payment_amount,
            'down_payment_status' => $reservation->down_payment_status,
            'purchase_mechanism' => $reservation->purchase_mechanism,
            'credit_status' => $reservation->credit_status,
            'proposed_price' => $reservation->proposed_price,
            'negotiation_reason' => $reservation->negotiation_reason,
            'requires_accounting_confirmation' => $reservation->requiresAccountingConfirmation(),
            'has_financing_tracker' => $reservation->hasFinancingTracker(),
            'has_title_transfer' => $reservation->hasTitleTransfer(),
            'payment_plan_total' => $reservation->getPaymentPlanTotal(),
            'payment_plan_remaining' => $reservation->getPaymentPlanRemaining(),
            'created_at' => $reservation->created_at?->toDateTimeString(),
        ];

        if ($reservation->relationLoaded('negotiationApproval') && $reservation->negotiationApproval) {
            $data['negotiation_approval_status'] = $reservation->negotiationApproval->status;
        }

        return [
            'status' => 'ok',
            'data' => $data,
            'sources' => [
                [
                    'type' => 'record',
                    'title' => 'Sales Reservation #'.$reservation->id,
                    'ref' => 'sales_reservation:'.$reservation->id,
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

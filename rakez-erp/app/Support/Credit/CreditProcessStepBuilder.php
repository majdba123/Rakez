<?php

namespace App\Support\Credit;

use App\Models\SalesReservation;
use App\Models\TitleTransfer;

class CreditProcessStepBuilder
{
    /**
     * @return array<int, array{key:string,state:string}>
     */
    public function reservationLifecycleSteps(SalesReservation $reservation): array
    {
        $status = $reservation->credit_status ?? 'pending';

        return [
            ['key' => 'confirmed', 'state' => in_array($status, ['pending', 'in_progress', 'title_transfer', 'sold', 'rejected'], true) ? 'completed' : 'pending'],
            ['key' => 'financing', 'state' => $this->financingLifecycleState($reservation)],
            ['key' => 'title_transfer', 'state' => in_array($status, ['title_transfer', 'sold'], true) ? 'completed' : ($status === 'in_progress' || $status === 'pending' ? 'pending' : 'failed')],
            ['key' => 'sold', 'state' => $status === 'sold' ? 'completed' : ($status === 'rejected' ? 'failed' : 'pending')],
        ];
    }

    /**
     * @return array<int, array{key:string,state:string,description:?string}>
     */
    public function financingStepsForReservation(SalesReservation $reservation): array
    {
        if (! $reservation->isBankFinancing()) {
            return [[
                'key' => 'not_required',
                'state' => 'skipped',
                'description' => null,
            ]];
        }

        $tracker = $reservation->financingTracker;
        if (! $tracker) {
            return [[
                'key' => 'not_started',
                'state' => 'pending',
                'description' => null,
            ]];
        }

        $steps = [];
        $stageNumbers = $tracker->is_cash_workflow ? [1, 6] : range(1, 6);

        foreach ($stageNumbers as $i) {
            $status = $tracker->getStageStatus($i);

            $steps[] = [
                'key' => 'stage_'.$i,
                'state' => match ($status) {
                    'completed' => 'completed',
                    'in_progress' => 'current',
                    'overdue' => 'failed',
                    default => 'pending',
                },
                'description' => $tracker->getStageDeadline($i)?->format('Y-m-d H:i'),
            ];
        }

        return $steps;
    }

    /**
     * @return array<int, array{key:string,state:string}>
     */
    public function titleTransferStepsForReservation(SalesReservation $reservation): array
    {
        return $this->titleTransferSteps($reservation->titleTransfer);
    }

    /**
     * @return array<int, array{key:string,state:string}>
     */
    public function titleTransferSteps(?TitleTransfer $transfer): array
    {
        if (! $transfer) {
            return [[
                'key' => 'not_started',
                'state' => 'pending',
            ]];
        }

        $status = $transfer->status;

        return [
            ['key' => 'preparation', 'state' => $status === 'preparation' ? 'current' : (in_array($status, ['scheduled', 'completed'], true) ? 'completed' : 'pending')],
            ['key' => 'scheduled', 'state' => $status === 'scheduled' ? 'current' : ($status === 'completed' ? 'completed' : 'pending')],
            ['key' => 'completed', 'state' => $status === 'completed' ? 'completed' : 'pending'],
        ];
    }

    /**
     * API-oriented credit procedure timeline.
     *
     * @return array<int, array{key:string,label_ar:string,status:string,date:?string}>
     */
    public function creditProcedureStepsForApi(SalesReservation $reservation): array
    {
        $tracker = $reservation->financingTracker;
        $titleTransfer = $reservation->titleTransfer;

        $steps = [
            ['key' => 'contact_client', 'label_ar' => 'التواصل مع العميل', 'status' => 'pending', 'date' => null],
            ['key' => 'submit_to_bank', 'label_ar' => 'رفع الطلب للبنك', 'status' => 'pending', 'date' => null],
            ['key' => 'valuation', 'label_ar' => 'صدور التقييم', 'status' => 'pending', 'date' => null],
            ['key' => 'appraiser_visit', 'label_ar' => 'زيارة المقيم للمشروع', 'status' => 'pending', 'date' => null],
            ['key' => 'bank_contracts', 'label_ar' => 'الإجراءات البنكية والعقود', 'status' => 'pending', 'date' => null],
            ['key' => 'contract_execution', 'label_ar' => 'تنفيذ العقود', 'status' => 'pending', 'date' => null],
            ['key' => 'pre_evacuation', 'label_ar' => 'فترة التجهيز قبل الإفراغ', 'status' => 'pending', 'date' => null],
        ];

        if ($tracker) {
            $stageMap = [
                1 => 0,
                2 => 1,
                3 => 2,
                4 => 3,
                5 => 4,
            ];

            foreach ($stageMap as $stageNum => $stepIndex) {
                $status = $tracker->{"stage_{$stageNum}_status"};
                $date = $tracker->{"stage_{$stageNum}_completed_at"} ?? $tracker->{"stage_{$stageNum}_deadline"};

                $steps[$stepIndex]['status'] = $status;
                $steps[$stepIndex]['date'] = $date ? \Carbon\Carbon::parse($date)->format('Y-m-d') : null;
            }
        }

        if ($titleTransfer) {
            $ttStatus = $titleTransfer->status;
            $steps[5]['status'] = in_array($ttStatus, ['scheduled', 'completed'], true) ? $ttStatus : 'pending';
            $steps[5]['date'] = $titleTransfer->scheduled_date?->format('Y-m-d') ?? $titleTransfer->completed_date?->format('Y-m-d');
            $steps[6]['status'] = $ttStatus === 'preparation' ? 'in_progress' : ($ttStatus === 'completed' ? 'completed' : 'pending');
            $steps[6]['date'] = $titleTransfer->completed_date?->format('Y-m-d');
        }

        if ($reservation->credit_status === 'sold') {
            $steps[5]['status'] = 'completed';
            $steps[6]['status'] = 'completed';
        }

        return $steps;
    }

    protected function financingLifecycleState(SalesReservation $reservation): string
    {
        if (! $reservation->isBankFinancing()) {
            return 'skipped';
        }

        return match ($reservation->credit_status) {
            'in_progress' => 'current',
            'title_transfer', 'sold' => 'completed',
            'rejected' => 'failed',
            default => 'pending',
        };
    }
}

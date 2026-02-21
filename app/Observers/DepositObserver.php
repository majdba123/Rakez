<?php

namespace App\Observers;

use App\Constants\DepositStatus;
use App\Models\Deposit;
use App\Models\DailyDeposit;

/**
 * When a deposit is confirmed (accounting), create a DailyDeposit record
 * so the marketing dashboard KPIs (daily deposits count, deposit cost) stay in sync.
 */
class DepositObserver
{
    public function updated(Deposit $deposit): void
    {
        if ($deposit->status !== DepositStatus::CONFIRMED || ! $deposit->confirmed_at) {
            return;
        }

        $wasConfirmed = $deposit->getOriginal('status') === DepositStatus::CONFIRMED;
        if ($wasConfirmed) {
            return;
        }

        if (DailyDeposit::where('deposit_id', $deposit->id)->exists()) {
            return;
        }

        DailyDeposit::create([
            'deposit_id' => $deposit->id,
            'date' => $deposit->confirmed_at->toDateString(),
            'amount' => $deposit->amount,
            'booking_id' => $deposit->sales_reservation_id ? (string) $deposit->sales_reservation_id : null,
            'project_id' => $deposit->contract_id,
        ]);
    }
}

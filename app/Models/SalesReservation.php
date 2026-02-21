<?php

namespace App\Models;

use App\Constants\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'contract_unit_id',
        'marketing_employee_id',
        'status',
        'reservation_type',
        'contract_date',
        'negotiation_notes',
        'negotiation_reason',
        'proposed_price',
        'evacuation_date',
        'approval_deadline',
        'client_name',
        'client_mobile',
        'client_nationality',
        'client_iban',
        'payment_method',
        'down_payment_amount',
        'down_payment_status',
        'down_payment_confirmed',
        'down_payment_confirmed_by',
        'down_payment_confirmed_at',
        'brokerage_commission_percent',
        'commission_payer',
        'tax_amount',
        'credit_status',
        'purchase_mechanism',
        'voucher_pdf_path',
        'snapshot',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'evacuation_date' => 'date',
        'snapshot' => 'array',
        'down_payment_amount' => 'decimal:2',
        'proposed_price' => 'decimal:2',
        'brokerage_commission_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'down_payment_confirmed' => 'boolean',
        'down_payment_confirmed_at' => 'datetime',
        'approval_deadline' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the contract (project) that owns this reservation.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the contract unit that owns this reservation.
     */
    public function contractUnit()
    {
        return $this->belongsTo(ContractUnit::class);
    }

    /**
     * Get the marketing employee who created this reservation.
     */
    public function marketingEmployee()
    {
        return $this->belongsTo(User::class, 'marketing_employee_id');
    }

    /**
     * Get the actions logged for this reservation.
     */
    public function actions()
    {
        return $this->hasMany(SalesReservationAction::class);
    }

    /**
     * Get the negotiation approval for this reservation.
     */
    public function negotiationApproval(): HasOne
    {
        return $this->hasOne(NegotiationApproval::class);
    }

    /**
     * Get the payment installments for this reservation.
     */
    public function paymentInstallments(): HasMany
    {
        return $this->hasMany(ReservationPaymentInstallment::class)->orderBy('due_date');
    }

    /**
     * Get the financing tracker for this reservation.
     */
    public function financingTracker(): HasOne
    {
        return $this->hasOne(CreditFinancingTracker::class);
    }

    /**
     * Get the title transfer for this reservation.
     */
    public function titleTransfer(): HasOne
    {
        return $this->hasOne(TitleTransfer::class);
    }

    /**
     * Get the claim file for this reservation.
     */
    public function claimFile(): HasOne
    {
        return $this->hasOne(ClaimFile::class);
    }

    /**
     * Get the user who confirmed the down payment.
     */
    public function downPaymentConfirmedBy()
    {
        return $this->belongsTo(User::class, 'down_payment_confirmed_by');
    }

    /**
     * Scope a query to only include active reservations.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ReservationStatus::active());
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by employee.
     */
    public function scopeByEmployee($query, $userId)
    {
        return $query->where('marketing_employee_id', $userId);
    }

    /**
     * Scope a query to filter by date range (filters by created_at).
     */
    public function scopeDateRange($query, $from = null, $to = null)
    {
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }
        return $query;
    }

    /**
     * Check if reservation is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ReservationStatus::active(), true);
    }

    /**
     * Check if reservation can be confirmed.
     */
    public function canConfirm(): bool
    {
        return $this->status === ReservationStatus::UNDER_NEGOTIATION;
    }

    /**
     * Check if reservation can be cancelled.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, ReservationStatus::active(), true);
    }

    /**
     * Check if reservation has a payment plan.
     */
    public function hasPaymentPlan(): bool
    {
        return $this->paymentInstallments()->exists();
    }

    /**
     * Check if reservation is pending approval.
     */
    public function isPendingApproval(): bool
    {
        return $this->negotiationApproval()
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * Check if the associated project is off-plan.
     */
    public function isOffPlan(): bool
    {
        return $this->contract && $this->contract->is_off_plan;
    }

    /**
     * Get the total payment plan amount.
     */
    public function getPaymentPlanTotal(): float
    {
        return (float) $this->paymentInstallments()->sum('amount');
    }

    /**
     * Get the remaining payment plan amount.
     */
    public function getPaymentPlanRemaining(): float
    {
        return (float) $this->paymentInstallments()
            ->whereIn('status', ['pending', 'overdue'])
            ->sum('amount');
    }

    /**
     * Scope for confirmed reservations ready for Credit.
     */
    public function scopeConfirmedForCredit($query)
    {
        return $query->where('status', ReservationStatus::CONFIRMED);
    }

    /**
     * Scope for reservations pending accounting confirmation.
     */
    public function scopePendingAccountingConfirmation($query)
    {
        return $query->where('status', ReservationStatus::CONFIRMED)
            ->where('down_payment_confirmed', false)
            ->whereIn('payment_method', ['bank_transfer', 'bank_financing']);
    }

    /**
     * Scope for sold projects (completed title transfers).
     */
    public function scopeSoldProjects($query)
    {
        return $query->where('credit_status', 'sold');
    }

    /**
     * Scope by credit status.
     */
    public function scopeByCreditStatus($query, $status)
    {
        return $query->where('credit_status', $status);
    }

    /**
     * Check if down payment requires accounting confirmation.
     */
    public function requiresAccountingConfirmation(): bool
    {
        return in_array($this->payment_method, ['bank_transfer', 'bank_financing'])
            && !$this->down_payment_confirmed;
    }

    /**
     * Check if this is a cash purchase.
     */
    public function isCashPurchase(): bool
    {
        return $this->purchase_mechanism === 'cash';
    }

    /**
     * Check if this is a bank financing purchase.
     */
    public function isBankFinancing(): bool
    {
        return in_array($this->purchase_mechanism, ['supported_bank', 'unsupported_bank']);
    }

    /**
     * Check if this is a supported bank financing.
     */
    public function isSupportedBank(): bool
    {
        return $this->purchase_mechanism === 'supported_bank';
    }

    /**
     * Check if reservation has financing tracker.
     */
    public function hasFinancingTracker(): bool
    {
        return $this->financingTracker()->exists();
    }

    /**
     * Check if reservation has title transfer.
     */
    public function hasTitleTransfer(): bool
    {
        return $this->titleTransfer()->exists();
    }

    /**
     * Check if title transfer is completed.
     */
    public function isTitleTransferCompleted(): bool
    {
        return $this->titleTransfer()
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Get the commission for this reservation.
     */
    public function commission()
    {
        return $this->hasOne(\App\Models\Commission::class);
    }

    /**
     * Get the deposits for this reservation.
     */
    public function deposits()
    {
        return $this->hasMany(\App\Models\Deposit::class);
    }
}

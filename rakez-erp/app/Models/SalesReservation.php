<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'client_name',
        'client_mobile',
        'client_nationality',
        'client_iban',
        'payment_method',
        'down_payment_amount',
        'down_payment_status',
        'purchase_mechanism',
        'voucher_pdf_path',
        'snapshot',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'snapshot' => 'array',
        'down_payment_amount' => 'decimal:2',
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
     * Scope a query to only include active reservations.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['under_negotiation', 'confirmed']);
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
        return in_array($this->status, ['under_negotiation', 'confirmed']);
    }

    /**
     * Check if reservation can be confirmed.
     */
    public function canConfirm(): bool
    {
        return $this->status === 'under_negotiation';
    }

    /**
     * Check if reservation can be cancelled.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, ['under_negotiation', 'confirmed']);
    }
}

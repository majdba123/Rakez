<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClaimFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_reservation_id',
        'generated_by',
        'pdf_path',
        'file_data',
        'is_combined',
        'claim_type',
        'notes',
        'total_claim_amount',
    ];

    protected $casts = [
        'file_data' => 'array',
        'is_combined' => 'boolean',
        'total_claim_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the single reservation for a non-combined claim file.
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(SalesReservation::class, 'sales_reservation_id');
    }

    /**
     * Get all reservations for a combined claim file (pivot table).
     */
    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(
            SalesReservation::class,
            'claim_file_reservations',
            'claim_file_id',
            'sales_reservation_id'
        )->withTimestamps();
    }

    /**
     * Get the user who generated this file.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function isCombined(): bool
    {
        return (bool) $this->is_combined;
    }

    /**
     * Check if PDF has been generated.
     */
    public function hasPdf(): bool
    {
        return !empty($this->pdf_path);
    }

    /**
     * Get project name from file data.
     */
    public function getProjectName(): ?string
    {
        return $this->file_data['project_name'] ?? null;
    }

    /**
     * Get unit number from file data.
     */
    public function getUnitNumber(): ?string
    {
        return $this->file_data['unit_number'] ?? null;
    }

    /**
     * Get commission percentage from file data.
     */
    public function getCommissionPercent(): ?float
    {
        return $this->file_data['brokerage_commission_percent'] ?? null;
    }

    /**
     * Get tax amount from file data.
     */
    public function getTaxAmount(): ?float
    {
        return $this->file_data['tax_amount'] ?? null;
    }
}




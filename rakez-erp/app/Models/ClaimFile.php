<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_reservation_id',
        'generated_by',
        'pdf_path',
        'file_data',
    ];

    protected $casts = [
        'file_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the reservation for this claim file.
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(SalesReservation::class, 'sales_reservation_id');
    }

    /**
     * Get the user who generated this file.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
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


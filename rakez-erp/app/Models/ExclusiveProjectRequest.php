<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExclusiveProjectRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'requested_by',
        'project_name',
        'developer_name',
        'developer_contact',
        'project_description',
        'estimated_units',
        'location_city',
        'location_district',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'contract_id',
        'contract_completed_at',
        'contract_pdf_path',
    ];

    protected $casts = [
        'estimated_units' => 'integer',
        'approved_at' => 'datetime',
        'contract_completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who requested this exclusive project.
     */
    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved this exclusive project.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the contract associated with this exclusive project.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Scope: Get pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get approved requests.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Get rejected requests.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope: Get completed requests.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'contract_completed');
    }

    /**
     * Check if request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if request is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if request is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if contract is completed.
     */
    public function isContractCompleted(): bool
    {
        return $this->status === 'contract_completed';
    }

    /**
     * Approve the request.
     */
    public function approve(User $approver): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    /**
     * Reject the request.
     */
    public function reject(User $approver, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Mark contract as completed.
     */
    public function completeContract(Contract $contract, ?string $pdfPath = null): void
    {
        $this->update([
            'status' => 'contract_completed',
            'contract_id' => $contract->id,
            'contract_completed_at' => now(),
            'contract_pdf_path' => $pdfPath,
        ]);
    }
}

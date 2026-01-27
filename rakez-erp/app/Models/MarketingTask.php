<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'marketing_project_id',
        'task_name',
        'marketer_id',
        'participating_marketers_count',
        'design_link',
        'design_number',
        'design_description',
        'status',
        'due_date',
        'created_by',
    ];

    protected $casts = [
        'participating_marketers_count' => 'integer',
        'due_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the marketing project for this task.
     */
    public function marketingProject()
    {
        return $this->belongsTo(MarketingProject::class);
    }

    /**
     * Get the contract (project) for this task.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the marketer assigned to this task.
     */
    public function marketer()
    {
        return $this->belongsTo(User::class, 'marketer_id');
    }

    /**
     * Get the user who created this task.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by contract.
     */
    public function scopeByContract($query, $contractId)
    {
        return $query->where('contract_id', $contractId);
    }

    /**
     * Scope a query to filter by marketer.
     */
    public function scopeByMarketer($query, $marketerId)
    {
        return $query->where('marketer_id', $marketerId);
    }
}

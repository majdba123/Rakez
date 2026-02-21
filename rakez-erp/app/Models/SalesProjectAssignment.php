<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesProjectAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'leader_id',
        'contract_id',
        'assigned_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the leader assigned to this project.
     */
    public function leader()
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    /**
     * Get the contract (project) for this assignment.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the user who assigned this project.
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scope a query to filter by leader.
     */
    public function scopeByLeader($query, $leaderId)
    {
        return $query->where('leader_id', $leaderId);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contract_units';

    protected $fillable = [
        'second_party_data_id',
        'unit_type',
        'unit_number',
        'status',
        'price',
        'area',
        'description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the second party data that owns this unit.
     */
    public function secondPartyData()
    {
        return $this->belongsTo(SecondPartyData::class);
    }

    /**
     * Accessor: get the parent contract via secondPartyData.
     */
    public function getContractAttribute()
    {
        return $this->secondPartyData?->contract;
    }

    /**
     * Get the sales reservations for this unit.
     */
    public function salesReservations()
    {
        return $this->hasMany(\App\Models\SalesReservation::class);
    }

    /**
     * Get active sales reservations for this unit.
     */
    public function activeSalesReservations()
    {
        return $this->hasMany(\App\Models\SalesReservation::class)
            ->whereIn('status', ['under_negotiation', 'confirmed']);
    }

    /**
     * Get the sales targets for this unit.
     */
    public function salesTargets()
    {
        return $this->hasMany(\App\Models\SalesTarget::class);
    }

    /**
     * Get the commission for this unit.
     */
    public function commission()
    {
        return $this->hasOne(\App\Models\Commission::class);
    }

    /**
     * Get the deposits for this unit.
     */
    public function deposits()
    {
        return $this->hasMany(\App\Models\Deposit::class);
    }
}


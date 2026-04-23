<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read string|null $description Legacy DB column (not in $fillable); prefer description_en / description_ar.
 */
class ContractUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contract_units';

    protected $fillable = [
        'contract_id',
        'unit_type',
        'unit_number',
        'status',
        'price',
        'area',
        'floor',
        'bedrooms',
        'bathrooms',
        'private_area_m2',
        'street_width',
        // total_area_m2 is computed as area + private_area_m2 (set in boot saving, accessor below)
        'facade', // DB column (view/facade/orientation)
        'description_en',
        'description_ar',
        'diagrames',

    ];

    protected $casts = [
        'price' => 'decimal:2',
        // DB column is string(255) per migration; numeric sorting uses CAST in queries where needed.
        'area' => 'string',
        'private_area_m2' => 'decimal:2',
        'street_width' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (ContractUnit $model) {
            $model->total_area_m2 = (float) ($model->area ?? 0) + (float) ($model->private_area_m2 ?? 0);
        });
    }

    /**
     * Alias for facade (API and resources use "view" / "orientation").
     */
    public function getViewAttribute(): ?string
    {
        return $this->attributes['facade'] ?? null;
    }

    /**
     * Total area in m² = area + private_area_m2 (always computed for consistency).
     */
    public function getTotalAreaM2Attribute(): float
    {
        return (float) ($this->attributes['area'] ?? 0) + (float) ($this->attributes['private_area_m2'] ?? 0);
    }

    /**
     * Contract that owns this unit (CSV rows belong to the project/contract).
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Second party row for the same contract (optional; same contract_id).
     */
    public function secondPartyData()
    {
        return $this->hasOne(SecondPartyData::class, 'contract_id', 'contract_id');
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


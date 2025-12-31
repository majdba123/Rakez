<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ContractInfo;
use App\Models\SecondPartyData;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'project_name',
        'developer_name',
        'developer_number',
        'city',
        'district',
        'units',
        'project_image_url',
        'developer_requiment',
        'status',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'units' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user that owns the contract.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the contract info (one-to-one).
     */
    public function info()
    {
        return $this->hasOne(ContractInfo::class);
    }

    /**
     * Get the second party data (one-to-one).
     * بيانات الطرف الثاني
     */
    public function secondPartyData()
    {
        return $this->hasOne(SecondPartyData::class);
    }

    /**
     * Scope to filter contracts by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter contracts by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if contract is pending.
     */
    public function isPending()
    {
        return $this->status === 'pending';
    }

    /**
     * Check if contract is approved.
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if user owns this contract.
     */
    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    /**
     * Normalize units array (ensure all required fields and proper types)
     */
    public function normalizeUnits(): void
    {
        if (!$this->units || !is_array($this->units)) {
            $this->units = [];
            return;
        }

        $normalized = [];
        foreach ($this->units as $unit) {
            if (isset($unit['type'], $unit['count'], $unit['price'])) {
                $normalized[] = [
                    'type' => trim((string) $unit['type']),
                    'count' => (int) $unit['count'],
                    'price' => (float) $unit['price'],
                ];
            }
        }
        $this->units = $normalized;
    }

    /**
     * Calculate totals from units array
     * Adds calculated values (subtotal, percentage) for each unit
     */
    public function calculateUnitTotals(): void
    {
        // Normalize units first
        $this->normalizeUnits();

        // Initialize totals
        $unitsCount = 0;
        $totalValue = 0;

        // First pass: calculate total values
        if (is_array($this->units) && count($this->units) > 0) {
            foreach ($this->units as $unit) {
                $count = (int) ($unit['count'] ?? 0);
                $price = (float) ($unit['price'] ?? 0);

                $unitsCount += $count;
                $totalValue += ($count * $price);
            }
        }

        // Second pass: add calculated values to each unit
        $calculatedUnits = [];
        if (is_array($this->units) && count($this->units) > 0) {
            foreach ($this->units as $unit) {
                $count = (int) ($unit['count'] ?? 0);
                $price = (float) ($unit['price'] ?? 0);
                $subtotal = $count * $price;

                // Add calculated values to unit
                $unit['subtotal'] = $subtotal;
                $unit['percentage'] = $totalValue > 0
                    ? round(($subtotal / $totalValue) * 100, 2)
                    : 0;
                $unit['unit_average'] = round($price, 2);

                $calculatedUnits[] = $unit;
            }
        }

        // Assign the modified array back
        $this->units = $calculatedUnits;
    }

    /**
     * Scope: Get pending contracts
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get approved contracts
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope: Get contracts in specific city
     */
    public function scopeInCity($query, $city)
    {
        return $query->where('city', $city);
    }

    /**
     * Scope: Get contracts by developer
     */
    public function scopeByDeveloper($query, $developerName)
    {
        return $query->where('developer_name', 'like', '%' . $developerName . '%');
    }

    /**
     * Scope: Get contracts with minimum value
     */
    public function scopeMinimumValue($query, $amount)
    {
        return $query->where('total_units_value', '>=', $amount);
    }
}

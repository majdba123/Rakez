<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * قسم اللوحات - Boards Department
 * Stores boards/signs-related media for contracts
 */
class BoardsDepartment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'boards_departments';

    protected $fillable = [
        'contract_id',
        'has_ads',             // هل يوجد إعلانات
        'processed_by',        // معالج بواسطة
        'processed_at',        // تاريخ المعالجة
    ];

    protected $casts = [
        'has_ads' => 'boolean',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Default attributes
     */
    protected $attributes = [
        'has_ads' => false,
    ];

    /**
     * Get the contract that owns this boards department data.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the user who processed this record.
     * الموظف الذي قام بالمعالجة
     */
    public function processedByUser()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }


    public function hasAdsEnabled(): bool
    {
        return $this->has_ads === true;
    }
}


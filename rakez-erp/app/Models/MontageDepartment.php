<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * قسم المونتاج - Montage Department
 * Stores montage-related media for contracts (Editor)
 */
class MontageDepartment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'montage_departments';

    protected $fillable = [
        'contract_id',
        'image_url',           // رابط الصورة
        'video_url',           // رابط الفيديو
        'description',         // الوصف
        'processed_by',        // معالج بواسطة
        'processed_at',        // تاريخ المعالجة
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the contract that owns this montage department data.
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
}


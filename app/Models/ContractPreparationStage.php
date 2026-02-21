<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractPreparationStage extends Model
{
    use HasFactory;

    public const STAGE_LABELS_AR = [
        1 => 'الصكوك و الرخصه',
        2 => 'المخطاطات و التصميمات',
        3 => 'السجل و الهويه',
        4 => 'شهادة اتمام و اخرى',
        5 => 'الاسعار و الوحدات',
        6 => 'الضمانات و اخرى',
        7 => 'رقم المعلن',
    ];

    public const TOTAL_STAGES = 7;

    protected $fillable = [
        'contract_id',
        'stage_number',
        'document_link',
        'entry_date',
        'completed_at',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the contract that owns the stage.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get Arabic label for this stage.
     */
    public function getLabelArAttribute(): string
    {
        return self::STAGE_LABELS_AR[$this->stage_number] ?? "المرحلة {$this->stage_number}";
    }

    /**
     * Check if stage is completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}

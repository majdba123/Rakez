<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SecondPartyData extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'second_party_data';

    protected $fillable = [
        'contract_id',
        'real_estate_papers_url',      // رابط اوراق العقار
        'plans_equipment_docs_url',    // رابط مستندات المخطاطات والتجهيزات
        'project_logo_url',            // رابط شعار المشروع
        'prices_units_url',            // رابط الاسعار والوحرات
        'marketing_license_url',       // رخصة التسويق
        'advertiser_section_url',      // رقم قسم المعلن (مثل: 125712612)
        'processed_by',                // معالج بواسطة
        'processed_at',                // تاريخ المعالجة
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * All six second-party fields must be non-empty for the contract flag {@see Contract::$is_complete_second}.
     *
     * @return list<string>
     */
    public static function fieldNamesRequiredForContractCompletion(): array
    {
        return [
            'real_estate_papers_url',
            'plans_equipment_docs_url',
            'project_logo_url',
            'prices_units_url',
            'marketing_license_url',
            'advertiser_section_url',
        ];
    }

    public static function hasAllCompletionFieldsFilled(?self $record): bool
    {
        if (!$record) {
            return false;
        }

        foreach (self::fieldNamesRequiredForContractCompletion() as $field) {
            $val = $record->getAttribute($field);
            if ($val === null || (is_string($val) && trim($val) === '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * For keys present in $data, turn empty strings into null so updates actually clear columns
     * and {@see syncIsCompleteSecondOnContract()} can set is_complete_second to false.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeCompletionFieldsInPayload(array $data): array
    {
        foreach (self::fieldNamesRequiredForContractCompletion() as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $v = $data[$field];
            if ($v === null || (is_string($v) && trim($v) === '')) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    /**
     * Keep Contract::is_complete_second in sync with this row.
     */
    public function syncIsCompleteSecondOnContract(): void
    {
        if (!$this->contract_id) {
            return;
        }

        Contract::whereKey($this->contract_id)->update([
            'is_complete_second' => self::hasAllCompletionFieldsFilled($this),
        ]);
    }

    /**
     * Get the contract that owns this second party data.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Contract units for the same contract (units belong to contract, not to this row).
     */
    public function contractUnits()
    {
        return $this->hasMany(ContractUnit::class, 'contract_id', 'contract_id');
    }

    /**
     * Get the user who processed this record.
     * الموظف الذي قام بالمعالجة
     */
    public function processedByUser()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get all URL fields as an array.
     */
    public function getUrlsArray(): array
    {
        return [
            'real_estate_papers_url' => $this->real_estate_papers_url,
            'plans_equipment_docs_url' => $this->plans_equipment_docs_url,
            'project_logo_url' => $this->project_logo_url,
            'prices_units_url' => $this->prices_units_url,
            'marketing_license_url' => $this->marketing_license_url,
            'advertiser_section_url' => $this->advertiser_section_url,
        ];
    }

    /**
     * Get advertiser section number
     * رقم قسم المعلن
     */
    public function getAdvertiserSectionNumber(): ?string
    {
        return $this->advertiser_section_url;
    }
}


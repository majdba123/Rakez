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
        'advertiser_section_url',      // قسم معلن
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
     * Get the contract that owns this second party data.
     */
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get all contract units (CSV units) for this second party data.
     */
    public function contractUnits()
    {
        return $this->hasMany(ContractUnit::class);
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
}


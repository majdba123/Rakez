<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractInfo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contract_infos';

    protected $fillable = [
        'contract_id',
        'contract_number',
        'first_party_name',
        'first_party_cr_number',
        'first_party_signatory',
        'first_party_phone',
        'first_party_email',
        'gregorian_date',
        'hijri_date',
        'contract_city',
        'agreement_duration_days',
        'commission_percent',
        'commission_from',
        'agency_number',
        'agency_date',
        'avg_property_value',
        'release_date',
        'second_party_name',
        'second_party_address',
        'second_party_cr_number',
        'second_party_signatory',
        'second_party_id_number',
        'second_party_role',
        'second_party_phone',
        'second_party_email'
    ];

    protected $casts = [
        'agreement_duration_days' => 'integer',
        'commission_percent' => 'decimal:2',
        'avg_property_value' => 'decimal:2',
        'units_count' => 'integer',
        'area' => 'decimal:2',
        'gregorian_date' => 'datetime',
        'agency_date' => 'datetime',
        'release_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
}

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
}


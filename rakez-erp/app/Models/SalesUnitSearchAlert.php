<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesUnitSearchAlert extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'sales_staff_id',
        'client_name',
        'client_mobile',
        'client_email',
        'client_sms_opt_in',
        'client_sms_opted_in_at',
        'client_sms_locale',
        'city_id',
        'district_id',
        'project_id',
        'unit_type',
        'floor',
        'min_price',
        'max_price',
        'min_area',
        'max_area',
        'min_bedrooms',
        'max_bedrooms',
        'query_text',
        'status',
        'last_notified_at',
        'last_system_notified_at',
        'last_sms_attempted_at',
        'last_sms_sent_at',
        'last_sms_error',
        'last_matched_unit_id',
        'expires_at',
        'last_twilio_sid',
        'last_delivery_error',
    ];

    protected $casts = [
        'client_sms_opt_in' => 'boolean',
        'client_sms_opted_in_at' => 'datetime',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'min_area' => 'decimal:2',
        'max_area' => 'decimal:2',
        'min_bedrooms' => 'integer',
        'max_bedrooms' => 'integer',
        'last_notified_at' => 'datetime',
        'last_system_notified_at' => 'datetime',
        'last_sms_attempted_at' => 'datetime',
        'last_sms_sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function salesStaff()
    {
        return $this->belongsTo(User::class, 'sales_staff_id');
    }

    public function lastMatchedUnit()
    {
        return $this->belongsTo(ContractUnit::class, 'last_matched_unit_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function project()
    {
        return $this->belongsTo(Contract::class, 'project_id');
    }

    public function deliveries()
    {
        return $this->hasMany(SalesUnitSearchAlertDelivery::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}

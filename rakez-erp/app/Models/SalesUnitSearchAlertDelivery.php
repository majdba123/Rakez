<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesUnitSearchAlertDelivery extends Model
{
    use HasFactory;

    public const CHANNEL_SYSTEM_NOTIFICATION = 'system_notification';

    public const CHANNEL_SMS = 'sms';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'sales_unit_search_alert_id',
        'contract_unit_id',
        'user_notification_id',
        'client_mobile',
        'delivery_channel',
        'status',
        'twilio_sid',
        'skip_reason',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function alert()
    {
        return $this->belongsTo(SalesUnitSearchAlert::class, 'sales_unit_search_alert_id');
    }

    public function contractUnit()
    {
        return $this->belongsTo(ContractUnit::class);
    }

    public function userNotification()
    {
        return $this->belongsTo(UserNotification::class);
    }
}

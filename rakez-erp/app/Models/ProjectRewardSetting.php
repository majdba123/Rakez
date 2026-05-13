<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectRewardSetting extends Model
{
    use SoftDeletes;

    public const MODE_PERCENTAGE_OF_SALE = 'percentage_of_sale';
    public const MODE_MANUAL_AMOUNT = 'manual_amount';

    public const SOURCE_DEVELOPER = 'developer';
    public const SOURCE_COMPANY = 'company';

    protected $fillable = [
        'contract_id',
        'calculation_mode',
        'reward_percentage',
        'source',
        'tax_enabled',
        'vat_percentage',
        'assigned_bring_percentage',
        'assigned_convince_percentage',
        'assigned_close_percentage',
        'outside_bring_percentage',
        'outside_convince_percentage',
        'outside_close_percentage',
        'ceo_user_id',
        'ceo_percentage',
        'sales_manager_user_id',
        'sales_manager_percentage',
        'sales_leader_user_id',
        'sales_leader_percentage',
        'group_leader_user_id',
        'group_leader_percentage',
        'external_marketer_user_id',
        'external_marketer_percentage',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'reward_percentage' => 'decimal:2',
        'tax_enabled' => 'boolean',
        'vat_percentage' => 'decimal:2',
        'assigned_bring_percentage' => 'decimal:2',
        'assigned_convince_percentage' => 'decimal:2',
        'assigned_close_percentage' => 'decimal:2',
        'outside_bring_percentage' => 'decimal:2',
        'outside_convince_percentage' => 'decimal:2',
        'outside_close_percentage' => 'decimal:2',
        'ceo_percentage' => 'decimal:2',
        'sales_manager_percentage' => 'decimal:2',
        'sales_leader_percentage' => 'decimal:2',
        'group_leader_percentage' => 'decimal:2',
        'external_marketer_percentage' => 'decimal:2',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ceoUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ceo_user_id');
    }

    public function salesManagerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_manager_user_id');
    }

    public function salesLeaderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_leader_user_id');
    }

    public function groupLeaderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'group_leader_user_id');
    }

    public function externalMarketerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'external_marketer_user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvImport extends Model
{
    protected $fillable = [
        'type',
        'uploaded_by',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'row_errors',
        'error_message',
        'completed_at',
    ];

    protected $casts = [
        'row_errors' => 'array',
        'completed_at' => 'datetime',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'successful_rows' => 'integer',
        'failed_rows' => 'integer',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';

    const TYPE_EMPLOYEES = 'employees';
    const TYPE_CONTRACTS = 'contracts';
    const TYPE_CITIES_DISTRICTS = 'cities_districts';
    const TYPE_DISTRICTS = 'districts';
    const TYPE_CONTRACT_INFO = 'contract_info';
    const TYPE_SECOND_PARTY_DATA = 'second_party_data';
    const TYPE_TEAMS = 'teams';

    /**
     * @return list<string>
     */
    public static function allTypes(): array
    {
        return [
            self::TYPE_EMPLOYEES,
            self::TYPE_CONTRACTS,
            self::TYPE_CITIES_DISTRICTS,
            self::TYPE_DISTRICTS,
            self::TYPE_CONTRACT_INFO,
            self::TYPE_SECOND_PARTY_DATA,
            self::TYPE_TEAMS,
        ];
    }

    public static function labelForType(string $type): string
    {
        return match ($type) {
            self::TYPE_EMPLOYEES => 'استيراد الموظفين',
            self::TYPE_CONTRACTS => 'استيراد العقود',
            self::TYPE_CITIES_DISTRICTS => 'استيراد المدن والأحياء',
            self::TYPE_DISTRICTS => 'استيراد الأحياء',
            self::TYPE_CONTRACT_INFO => 'استيراد معلومات العقد',
            self::TYPE_SECOND_PARTY_DATA => 'استيراد بيانات الطرف الثاني',
            self::TYPE_TEAMS => 'استيراد الفرق',
            default => $type,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function typesCatalog(): array
    {
        return array_map(
            fn (string $value) => ['value' => $value, 'label' => self::labelForType($value)],
            self::allTypes()
        );
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function markProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    public function markCompleted(): void
    {
        $status = $this->failed_rows > 0
            ? self::STATUS_COMPLETED_WITH_ERRORS
            : self::STATUS_COMPLETED;

        $this->update([
            'status' => $status,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }
}

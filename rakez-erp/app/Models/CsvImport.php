<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

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

    /**
     * Preflight / validation failed: nothing is written to domain tables; status is failed with reasons in row_errors.
     *
     * @param  array<string, mixed>  $rowErrors
     */
    public function markImportFailedWithRowErrors(string $summaryMessage, array $rowErrors): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $summaryMessage,
            'row_errors' => $rowErrors,
            'successful_rows' => 0,
            'failed_rows' => count($rowErrors),
            'processed_rows' => 0,
            'completed_at' => now(),
        ]);
    }

    /**
     * After per-row validation and optional DB work: save counts, row_errors, optional Arabic summary in error_message, then completed / completed_with_errors.
     *
     * @param  array<string, mixed>|null  $rowErrors
     */
    public function recordImportOutcome(int $successful, int $failed, ?array $rowErrors, ?int $processedRows = null): void
    {
        $processedRows ??= $successful + $failed;

        $summary = null;
        if ($failed > 0) {
            $summary = $successful === 0
                ? "لم يُستورد أي صف بنجاح ({$failed} صف بأخطاء). راجع الحقول row_errors و mistakes_description."
                : "تم استيراد {$successful} صفاً بنجاح وفشل {$failed} صفاً. راجع row_errors و mistakes_description.";
        }

        $this->update([
            'successful_rows' => $successful,
            'failed_rows' => $failed,
            'processed_rows' => $processedRows,
            'row_errors' => ! empty($rowErrors) ? $rowErrors : null,
            'error_message' => $summary,
        ]);

        $this->refresh();
        $this->markCompleted();
    }

    /**
     * Human-readable summary of fatal errors and/or per-row validation failures (Arabic labels).
     */
    public function mistakesDescription(): ?string
    {
        $rowErrors = is_array($this->row_errors) ? $this->row_errors : [];

        $rowErrorsBody = null;
        if ($rowErrors !== []) {
            $parts = [];
            $i = 0;
            foreach ($rowErrors as $rowKey => $errs) {
                if ($i >= 8) {
                    $remaining = count($rowErrors) - 8;
                    if ($remaining > 0) {
                        $parts[] = sprintf('و%d صفاً إضافياً بأخطاء (راجع حقل row_errors)', $remaining);
                    }
                    break;
                }
                $label = match (true) {
                    $rowKey === 'import' => 'حفظ البيانات',
                    is_string($rowKey) && preg_match('/^row_(\d+)$/', $rowKey, $m) => 'الصف '.$m[1],
                    default => (string) $rowKey,
                };
                $parts[] = $label.': '.$this->flattenErrorsToString($errs);
                $i++;
            }
            $rowErrorsBody = implode(' — ', $parts);
        }

        if ($this->status === self::STATUS_FAILED && filled($this->error_message)) {
            return $rowErrorsBody !== null
                ? $this->error_message.' — '.$rowErrorsBody
                : $this->error_message;
        }

        if ($rowErrorsBody === null) {
            if ((int) $this->failed_rows > 0 && filled($this->error_message)) {
                return $this->error_message;
            }

            return null;
        }

        if (filled($this->error_message) && $this->status !== self::STATUS_FAILED) {
            return $this->error_message.' — '.$rowErrorsBody;
        }

        return $rowErrorsBody;
    }

    private function flattenErrorsToString(mixed $errs): string
    {
        if (is_string($errs)) {
            return $errs;
        }
        if (! is_array($errs)) {
            return '';
        }

        $out = [];
        foreach ($errs as $k => $v) {
            if (is_array($v)) {
                $flat = array_filter(array_map('strval', Arr::flatten($v)));
                $prefix = is_string($k) ? $k.': ' : '';
                $out[] = $prefix.implode(', ', $flat);
            } else {
                $out[] = (string) $v;
            }
        }

        return implode('; ', $out);
    }
}

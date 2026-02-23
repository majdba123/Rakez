<?php

namespace App\Exceptions;

use Exception;

class CommissionException extends Exception
{
    protected string $errorCode;

    public function __construct(
        string $message,
        string $errorCode,
        int $statusCode = 400
    ) {
        parent::__construct($message, $statusCode);
        $this->errorCode = $errorCode;
    }

    /**
     * Get the error code.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], $this->getCode() ?: 400);
    }

    /**
     * Commission already exists for this unit.
     */
    public static function alreadyExists(): self
    {
        return new self(
            'عمولة موجودة بالفعل لهذه الوحدة',
            'COMM_001',
            409
        );
    }

    /**
     * Invalid commission percentage.
     */
    public static function invalidPercentage(): self
    {
        return new self(
            'نسبة العمولة غير صحيحة',
            'COMM_002',
            422
        );
    }

    /**
     * Distribution total does not equal 100%.
     */
    public static function invalidDistributionTotal(float $total): self
    {
        return new self(
            "مجموع نسب التوزيع يجب أن يساوي 100% (المجموع الحالي: {$total}%)",
            'COMM_003',
            422
        );
    }

    /**
     * Cannot modify approved commission.
     */
    public static function cannotModifyApproved(): self
    {
        return new self(
            'لا يمكن تعديل عمولة تم اعتمادها',
            'COMM_004',
            403
        );
    }

    /**
     * Duplicate distribution for same user.
     */
    public static function duplicateDistribution(): self
    {
        return new self(
            'لا يمكن توزيع العمولة على نفس الموظف أكثر من مرة',
            'COMM_005',
            422
        );
    }

    /**
     * Invalid distribution type.
     */
    public static function invalidDistributionType(string $type): self
    {
        return new self(
            "نوع التوزيع غير صحيح: {$type}",
            'COMM_006',
            422
        );
    }

    /**
     * Commission calculation error.
     */
    public static function calculationError(string $details = ''): self
    {
        $message = 'خطأ في حساب العمولة';
        if ($details) {
            $message .= ": {$details}";
        }
        
        return new self(
            $message,
            'COMM_007',
            500
        );
    }

    /**
     * Expenses exceed commission amount.
     */
    public static function expensesExceedAmount(): self
    {
        return new self(
            'إجمالي المصاريف لا يمكن أن يتجاوز مبلغ العمولة',
            'COMM_008',
            422
        );
    }

    /**
     * Commission not found.
     */
    public static function notFound(): self
    {
        return new self(
            'العمولة غير موجودة',
            'COMM_009',
            404
        );
    }

    /**
     * Distribution not found.
     */
    public static function distributionNotFound(): self
    {
        return new self(
            'التوزيع غير موجود',
            'COMM_010',
            404
        );
    }

    /**
     * Cannot delete approved distribution.
     */
    public static function cannotDeleteApprovedDistribution(): self
    {
        return new self(
            'لا يمكن حذف توزيع تم اعتماده',
            'COMM_011',
            403
        );
    }

    /**
     * External marketer requires bank account.
     */
    public static function externalMarketerRequiresBankAccount(): self
    {
        return new self(
            'رقم الحساب البنكي مطلوب للمسوق الخارجي',
            'COMM_012',
            422
        );
    }

    /**
     * Minimum commission amount not met.
     */
    public static function minimumAmountNotMet(float $minimum): self
    {
        return new self(
            "مبلغ العمولة يجب أن يكون {$minimum} ريال على الأقل",
            'COMM_013',
            422
        );
    }
}

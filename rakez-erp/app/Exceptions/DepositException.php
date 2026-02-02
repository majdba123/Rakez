<?php

namespace App\Exceptions;

use Exception;

class DepositException extends Exception
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
     * Deposit already exists.
     */
    public static function alreadyExists(): self
    {
        return new self(
            'وديعة موجودة بالفعل لهذا الحجز',
            'DEP_001',
            409
        );
    }

    /**
     * Cannot refund buyer-source deposit.
     */
    public static function cannotRefundBuyerSource(): self
    {
        return new self(
            'لا يمكن استرداد وديعة من مصدر المشتري',
            'DEP_002',
            403
        );
    }

    /**
     * Deposit already refunded.
     */
    public static function alreadyRefunded(): self
    {
        return new self(
            'تم استرداد الوديعة بالفعل',
            'DEP_003',
            409
        );
    }

    /**
     * Invalid payment method.
     */
    public static function invalidPaymentMethod(string $method): self
    {
        return new self(
            "طريقة الدفع غير صحيحة: {$method}",
            'DEP_004',
            422
        );
    }

    /**
     * Cannot modify confirmed deposit.
     */
    public static function cannotModifyConfirmed(): self
    {
        return new self(
            'لا يمكن تعديل وديعة تم تأكيدها',
            'DEP_005',
            403
        );
    }

    /**
     * Negative amount not allowed.
     */
    public static function negativeAmount(): self
    {
        return new self(
            'مبلغ الوديعة يجب أن يكون أكبر من صفر',
            'DEP_006',
            422
        );
    }

    /**
     * Invalid status transition.
     */
    public static function invalidStatusTransition(string $from, string $to): self
    {
        return new self(
            "لا يمكن تغيير حالة الوديعة من {$from} إلى {$to}",
            'DEP_007',
            422
        );
    }

    /**
     * Deposit not found.
     */
    public static function notFound(): self
    {
        return new self(
            'الوديعة غير موجودة',
            'DEP_008',
            404
        );
    }

    /**
     * Cannot confirm pending deposit.
     */
    public static function cannotConfirmNonPending(): self
    {
        return new self(
            'يمكن تأكيد الودائع المعلقة فقط',
            'DEP_009',
            422
        );
    }

    /**
     * Cannot refund pending deposit.
     */
    public static function cannotRefundPending(): self
    {
        return new self(
            'لا يمكن استرداد وديعة معلقة. يجب تأكيدها أولاً',
            'DEP_010',
            422
        );
    }

    /**
     * Payment date in future.
     */
    public static function paymentDateInFuture(): self
    {
        return new self(
            'تاريخ الدفع لا يمكن أن يكون في المستقبل',
            'DEP_011',
            422
        );
    }

    /**
     * Reservation already has deposit.
     */
    public static function reservationHasDeposit(): self
    {
        return new self(
            'يوجد وديعة بالفعل لهذا الحجز',
            'DEP_012',
            409
        );
    }

    /**
     * Unit does not belong to contract.
     */
    public static function unitNotInContract(): self
    {
        return new self(
            'الوحدة المحددة لا تنتمي إلى هذا المشروع',
            'DEP_013',
            422
        );
    }

    /**
     * Reservation does not belong to unit.
     */
    public static function reservationNotInUnit(): self
    {
        return new self(
            'الحجز المحدد لا ينتمي إلى هذه الوحدة',
            'DEP_014',
            422
        );
    }
}

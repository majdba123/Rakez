<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مطالبة عربون - {{ $deposit->id }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            direction: rtl;
            text-align: right;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px;
            background-color: #f5f5f5;
        }
        .label {
            font-weight: bold;
            color: #555;
        }
        .value {
            color: #000;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 5px;
            font-weight: bold;
        }
        .status-pending { background-color: #FFC107; color: #000; }
        .status-received { background-color: #4CAF50; color: #fff; }
        .status-confirmed { background-color: #2196F3; color: #fff; }
        .status-refunded { background-color: #F44336; color: #fff; }
        .amount-box {
            background-color: #e8f5e9;
            border: 2px solid #4CAF50;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
            border-radius: 10px;
        }
        .amount-box .amount {
            font-size: 32px;
            font-weight: bold;
            color: #4CAF50;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>مطالبة عربون</h1>
        <p>رقم العربون: {{ $deposit->id }}</p>
        <p>تاريخ الإصدار: {{ $generated_at }}</p>
    </div>

    <div class="info-section">
        <h3>معلومات العربون</h3>
        <div class="info-row">
            <span class="label">المشروع:</span>
            <span class="value">{{ $deposit->contract->project_name ?? 'غير محدد' }}</span>
        </div>
        @if($deposit->contractUnit)
        <div class="info-row">
            <span class="label">الوحدة:</span>
            <span class="value">{{ $deposit->contractUnit->unit_type }} - {{ $deposit->contractUnit->unit_number }}</span>
        </div>
        @endif
        <div class="info-row">
            <span class="label">مصدر العمولة:</span>
            <span class="value">
                @if($deposit->commission_source === 'owner') المالك
                @elseif($deposit->commission_source === 'buyer') المشتري
                @else غير محدد
                @endif
            </span>
        </div>
        <div class="info-row">
            <span class="label">طريقة الدفع:</span>
            <span class="value">{{ $deposit->payment_method }}</span>
        </div>
        <div class="info-row">
            <span class="label">تاريخ الدفع:</span>
            <span class="value">{{ $deposit->payment_date }}</span>
        </div>
        <div class="info-row">
            <span class="label">الحالة:</span>
            <span class="status-badge status-{{ $deposit->status }}">
                @if($deposit->status === 'pending') معلق
                @elseif($deposit->status === 'received') مستلم
                @elseif($deposit->status === 'confirmed') مؤكد
                @elseif($deposit->status === 'refunded') مسترد
                @endif
            </span>
        </div>
    </div>

    <div class="amount-box">
        <p style="margin: 0; font-size: 18px; color: #666;">المبلغ</p>
        <div class="amount">{{ number_format($deposit->amount, 2) }} ريال</div>
    </div>

    @if($deposit->confirmedBy)
    <div class="info-section">
        <h3>معلومات التأكيد</h3>
        <div class="info-row">
            <span class="label">تم التأكيد بواسطة:</span>
            <span class="value">{{ $deposit->confirmedBy->name }}</span>
        </div>
        <div class="info-row">
            <span class="label">تاريخ التأكيد:</span>
            <span class="value">{{ $deposit->confirmed_at }}</span>
        </div>
    </div>
    @endif

    @if($deposit->refund_reason)
    <div class="info-section">
        <h3>معلومات الاسترداد</h3>
        <div class="info-row">
            <span class="label">سبب الاسترداد:</span>
            <span class="value">{{ $deposit->refund_reason }}</span>
        </div>
        <div class="info-row">
            <span class="label">تاريخ الاسترداد:</span>
            <span class="value">{{ $deposit->refunded_at }}</span>
        </div>
    </div>
    @endif

    @if($deposit->notes)
    <div class="info-section">
        <h3>ملاحظات</h3>
        <div class="info-row">
            <span class="value">{{ $deposit->notes }}</span>
        </div>
    </div>
    @endif

    <div class="footer">
        <p>هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز العقاري</p>
        <p>{{ $generated_at }}</p>
    </div>
</body>
</html>

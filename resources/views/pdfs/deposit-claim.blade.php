<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>مطالبة عربون - {{ $deposit->id }}</title>
    <style>
        @page { margin: 30px 40px 80px 40px; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 11px;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .logo-area { text-align: center; padding: 10px 0 15px 0; margin-bottom: 15px; }
        .logo-area img { height: 90px; width: auto; }
        .doc-title { text-align: center; font-size: 16px; font-weight: bold; color: #1B2A4A; margin: 10px 0 5px 0; }
        .doc-subtitle { text-align: center; font-size: 10px; color: #666; margin: 0 0 15px 0; }
        .section-title { font-size: 13px; font-weight: bold; color: #222; margin: 20px 0 10px 0; padding-bottom: 5px; border-bottom: 1px solid #999; }
        .info-table { width: 100%; border-collapse: collapse; margin: 5px 0; }
        .info-table td { padding: 7px 8px; border: 1px solid #ddd; font-size: 10px; }
        .info-table td:first-child { width: 40%; background-color: #f5f5f5; font-weight: bold; color: #444; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .status-pending { background-color: #FFC107; color: #000; }
        .status-received { background-color: #4CAF50; color: #fff; }
        .status-confirmed { background-color: #2196F3; color: #fff; }
        .status-refunded { background-color: #F44336; color: #fff; }
        .amount-highlight { text-align: center; margin: 20px 0; padding: 15px; border: 2px solid #1B2A4A; background-color: #f0f4f8; }
        .amount-highlight .amount-val { font-size: 28px; font-weight: bold; color: #1B2A4A; }
        .amount-highlight .amount-label { font-size: 12px; color: #666; margin-bottom: 5px; }
        .footer-area { position: fixed; bottom: 0; left: 0; right: 0; border-top: 2px solid #1B2A4A; padding: 8px 30px; font-size: 7px; color: #666; }
        .footer-tbl { width: 100%; border-collapse: collapse; }
        .footer-tbl td { padding: 2px 5px; vertical-align: top; font-size: 7px; color: #666; }
    </style>
</head>
<body>
    <div class="footer-area">
        <table class="footer-tbl">
            <tr>
                <td style="text-align: right; width: 30%;"><strong>شركة راكز العقارية</strong><br>RAKEZ REAL ESTATE CO.</td>
                <td style="text-align: center; width: 40%;">C.R. 1010691801<br>المملكة العربية السعودية - الرياض 3130 شارع أنس بن مالك، حي الملقا</td>
                <td style="text-align: left; width: 30%;">920015711<br>www.rakez.sa</td>
            </tr>
        </table>
    </div>

    <div class="logo-area">
        <img src="{{ public_path('images/rakez-logo.png') }}" alt="راكز">
    </div>

    <p class="doc-title">مطالبة عربون</p>
    <p class="doc-subtitle">رقم العربون: {{ $deposit->id }} | تاريخ الإصدار: {{ $generated_at }}</p>

    <div class="amount-highlight">
        <div class="amount-label">المبلغ / Amount</div>
        <div class="amount-val">{{ number_format($deposit->amount, 2) }} ريال</div>
    </div>

    <p class="section-title">&#9670; معلومات العربون</p>
    <table class="info-table">
        <tr>
            <td>المشروع</td>
            <td>{{ $deposit->contract->project_name ?? 'غير محدد' }}</td>
        </tr>
        @if($deposit->contractUnit)
        <tr>
            <td>الوحدة</td>
            <td>{{ $deposit->contractUnit->unit_type }} - {{ $deposit->contractUnit->unit_number }}</td>
        </tr>
        @endif
        <tr>
            <td>مصدر العمولة</td>
            <td>
                @if($deposit->commission_source === 'owner') المالك
                @elseif($deposit->commission_source === 'buyer') المشتري
                @else غير محدد
                @endif
            </td>
        </tr>
        <tr>
            <td>طريقة الدفع</td>
            <td>{{ $deposit->payment_method }}</td>
        </tr>
        <tr>
            <td>تاريخ الدفع</td>
            <td>{{ $deposit->payment_date }}</td>
        </tr>
        <tr>
            <td>الحالة</td>
            <td>
                <span class="status-badge status-{{ $deposit->status }}">
                    @if($deposit->status === 'pending') معلق
                    @elseif($deposit->status === 'received') مستلم
                    @elseif($deposit->status === 'confirmed') مؤكد
                    @elseif($deposit->status === 'refunded') مسترد
                    @endif
                </span>
            </td>
        </tr>
    </table>

    @if($deposit->confirmedBy)
    <p class="section-title">&#9670; معلومات التأكيد</p>
    <table class="info-table">
        <tr>
            <td>تم التأكيد بواسطة</td>
            <td>{{ $deposit->confirmedBy->name }}</td>
        </tr>
        <tr>
            <td>تاريخ التأكيد</td>
            <td>{{ $deposit->confirmed_at }}</td>
        </tr>
    </table>
    @endif

    @if($deposit->refund_reason)
    <p class="section-title">&#9670; معلومات الاسترداد</p>
    <table class="info-table">
        <tr>
            <td>سبب الاسترداد</td>
            <td>{{ $deposit->refund_reason }}</td>
        </tr>
        <tr>
            <td>تاريخ الاسترداد</td>
            <td>{{ $deposit->refunded_at }}</td>
        </tr>
    </table>
    @endif

    @if($deposit->notes)
    <p class="section-title">&#9670; ملاحظات</p>
    <p style="font-size: 10px; padding: 5px; background: #f9f9f9; border: 1px solid #eee;">{{ $deposit->notes }}</p>
    @endif

    <p style="text-align: center; font-size: 9px; color: #999; margin-top: 30px;">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز العقاري | {{ $generated_at }}</p>
</body>
</html>

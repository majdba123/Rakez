<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>مطالبة عمولة - {{ $commission->id }}</title>
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
        .data-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .data-table th { background-color: #8B8B8B; color: #fff; padding: 8px 6px; border: 1px solid #7B7B7B; font-size: 10px; text-align: center; font-weight: bold; }
        .data-table td { padding: 8px 6px; border: 1px solid #ddd; text-align: center; font-size: 10px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 5px 0; }
        .info-table td { padding: 7px 8px; border: 1px solid #ddd; font-size: 10px; }
        .info-table td:first-child { width: 40%; background-color: #f5f5f5; font-weight: bold; color: #444; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .status-pending { background-color: #FFC107; color: #000; }
        .status-approved { background-color: #4CAF50; color: #fff; }
        .status-paid { background-color: #2196F3; color: #fff; }
        .status-rejected { background-color: #F44336; color: #fff; }
        .totals-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .totals-table td { padding: 8px; border: 1px solid #ddd; font-size: 11px; }
        .totals-table td:first-child { width: 60%; background-color: #f5f5f5; font-weight: bold; }
        .totals-table tr:last-child td { background-color: #1B2A4A; color: #fff; font-size: 13px; font-weight: bold; }
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

    <p class="doc-title">مطالبة عمولة</p>
    <p class="doc-subtitle">رقم العمولة: {{ $commission->id }} | تاريخ الإصدار: {{ $generated_at }}</p>

    <p class="section-title">&#9670; معلومات العمولة</p>
    <table class="info-table">
        <tr>
            <td>سعر البيع النهائي</td>
            <td>{{ number_format($commission->final_selling_price, 2) }} ريال</td>
        </tr>
        <tr>
            <td>نسبة العمولة</td>
            <td>{{ $commission->commission_percentage }}%</td>
        </tr>
        <tr>
            <td>الحالة</td>
            <td>
                <span class="status-badge status-{{ $commission->status }}">
                    @if($commission->status === 'pending') معلق
                    @elseif($commission->status === 'approved') معتمد
                    @elseif($commission->status === 'paid') مدفوع
                    @endif
                </span>
            </td>
        </tr>
    </table>

    <p class="section-title">&#9670; توزيع العمولة</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>المستلم</th>
                <th>النوع</th>
                <th>النسبة</th>
                <th>المبلغ</th>
                <th>الحالة</th>
            </tr>
        </thead>
        <tbody>
            @foreach($distributions as $dist)
            <tr>
                <td>
                    @if($dist->recipient)
                        {{ $dist->recipient->name }}
                    @else
                        {{ $dist->external_marketer_name ?? 'غير محدد' }}
                    @endif
                </td>
                <td>{{ $dist->type }}</td>
                <td>{{ $dist->percentage }}%</td>
                <td>{{ number_format($dist->amount, 2) }} ريال</td>
                <td>
                    <span class="status-badge status-{{ $dist->status }}">
                        @if($dist->status === 'pending') معلق
                        @elseif($dist->status === 'approved') معتمد
                        @elseif($dist->status === 'rejected') مرفوض
                        @elseif($dist->status === 'paid') مدفوع
                        @endif
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p class="section-title">&#9670; الإجماليات</p>
    <table class="totals-table">
        <tr>
            <td>المبلغ الإجمالي</td>
            <td>{{ number_format($commission->total_amount, 2) }} ريال</td>
        </tr>
        <tr>
            <td>ضريبة القيمة المضافة (15%)</td>
            <td>{{ number_format($commission->vat, 2) }} ريال</td>
        </tr>
        <tr>
            <td>مصاريف التسويق</td>
            <td>{{ number_format($commission->marketing_expenses, 2) }} ريال</td>
        </tr>
        <tr>
            <td>رسوم البنك</td>
            <td>{{ number_format($commission->bank_fees, 2) }} ريال</td>
        </tr>
        <tr>
            <td>صافي العمولة</td>
            <td>{{ number_format($commission->net_amount, 2) }} ريال</td>
        </tr>
    </table>

    <p style="text-align: center; font-size: 9px; color: #999; margin-top: 30px;">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز العقاري | {{ $generated_at }}</p>
</body>
</html>

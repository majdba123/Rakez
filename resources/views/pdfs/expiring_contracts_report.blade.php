<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>تقرير العقود القريبة من الانتهاء</title>
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
        .doc-title { text-align: center; font-size: 14px; font-weight: bold; color: #1B2A4A; margin: 10px 0 5px 0; }
        .doc-subtitle { text-align: center; font-size: 10px; color: #666; margin: 0 0 15px 0; }
        .section-title { font-size: 13px; font-weight: bold; color: #222; margin: 18px 0 8px 0; padding-bottom: 5px; border-bottom: 1px solid #999; }
        .data-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .data-table th { background-color: #8B8B8B; color: #fff; padding: 6px 4px; border: 1px solid #7B7B7B; font-size: 9px; text-align: center; font-weight: bold; }
        .data-table td { padding: 6px 4px; border: 1px solid #ddd; text-align: center; font-size: 9px; }
        .data-table tr:nth-child(even) { background-color: #f9f9f9; }
        .summary-box { margin: 15px 0; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd; }
        .summary-box p { margin: 4px 0; font-size: 10px; }
        .empty-msg { color: #777; font-style: italic; padding: 10px; font-size: 10px; }
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

    <p class="doc-title">تقرير العقود القريبة من الانتهاء وقرب انتهاء فترة التجربة</p>
    <p class="doc-subtitle">الفترة: خلال {{ $days }} يوماً | تاريخ التقرير: {{ $report['generated_at'] ?? '' }}</p>

    <p class="section-title">&#9670; عقود قريبة من الانتهاء</p>
    @if(!empty($report['expiring_contracts']) && count($report['expiring_contracts']) > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>رقم العقد</th>
                <th>اسم الموظف</th>
                <th>البريد الإلكتروني</th>
                <th>تاريخ الانتهاء</th>
                <th>الأيام المتبقية</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['expiring_contracts'] as $row)
            <tr>
                <td>{{ $row['contract_id'] ?? '-' }}</td>
                <td>{{ $row['employee_name'] ?? '-' }}</td>
                <td>{{ $row['employee_email'] ?? '-' }}</td>
                <td>{{ $row['end_date'] ?? '-' }}</td>
                <td>{{ $row['days_remaining'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="empty-msg">لا توجد عقود تنتهي خلال الفترة المحددة.</p>
    @endif

    <p class="section-title">&#9670; موظفون قرب انتهاء فترة التجربة</p>
    @if(!empty($report['probation_ending']) && count($report['probation_ending']) > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>الاسم</th>
                <th>البريد الإلكتروني</th>
                <th>تاريخ انتهاء التجربة</th>
                <th>الأيام المتبقية</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['probation_ending'] as $row)
            <tr>
                <td>{{ $row['name'] ?? '-' }}</td>
                <td>{{ $row['email'] ?? '-' }}</td>
                <td>{{ $row['probation_end_date'] ?? '-' }}</td>
                <td>{{ $row['days_remaining'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="empty-msg">لا يوجد موظفون قرب انتهاء فترة التجربة خلال الفترة المحددة.</p>
    @endif

    @if(!empty($report['summary']))
    <p class="section-title">&#9670; الملخص</p>
    <div class="summary-box">
        <p><strong>عدد العقود القريبة من الانتهاء:</strong> {{ $report['summary']['contracts_expiring_count'] ?? 0 }}</p>
        <p><strong>عدد الموظفين قرب انتهاء التجربة:</strong> {{ $report['summary']['probation_ending_count'] ?? 0 }}</p>
        <p><strong>الفترة المعتمدة (أيام):</strong> {{ $report['summary']['days_checked'] ?? $days }}</p>
    </div>
    @endif

    <p style="text-align: center; font-size: 9px; color: #999; margin-top: 25px;">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز | {{ $report['generated_at'] ?? '' }}</p>
</body>
</html>

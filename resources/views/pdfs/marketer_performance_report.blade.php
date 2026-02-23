<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>تقرير أداء المسوقين</title>
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

    <p class="doc-title">تقرير أداء المسوقين</p>
    <p class="doc-subtitle">الفترة: {{ $report['period']['year'] ?? '' }} / {{ $report['period']['month'] ?? '' }} | تاريخ التقرير: {{ $generated_at }}</p>

    <p class="section-title">&#9670; أداء المسوقين</p>
    @if(!empty($report['marketers']) && count($report['marketers']) > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>الاسم</th>
                <th>البريد الإلكتروني</th>
                <th>الفريق</th>
                <th>نسبة تحقيق الهدف %</th>
                <th>عدد الودائع</th>
                <th>عدد الإنذارات</th>
                <th>فترة التجربة</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['marketers'] as $row)
            <tr>
                <td>{{ $row['name'] ?? '-' }}</td>
                <td>{{ $row['email'] ?? '-' }}</td>
                <td>{{ $row['team_name'] ?? '-' }}</td>
                <td>{{ is_numeric($row['target_achievement_rate'] ?? null) ? number_format((float)$row['target_achievement_rate'], 1) : ($row['target_achievement_rate'] ?? '-') }}</td>
                <td>{{ $row['deposits_count'] ?? 0 }}</td>
                <td>{{ $row['warnings_count'] ?? 0 }}</td>
                <td>{{ !empty($row['is_in_probation']) ? 'نعم' : 'لا' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="empty-msg">لا يوجد مسوقون في الفترة المحددة.</p>
    @endif

    @if(!empty($report['totals']))
    <p class="section-title">&#9670; الملخص</p>
    <div class="summary-box">
        <p><strong>عدد المسوقين:</strong> {{ $report['totals']['marketers_count'] ?? 0 }}</p>
        <p><strong>متوسط تحقيق الهدف %:</strong> {{ isset($report['totals']['avg_achievement']) ? number_format((float)$report['totals']['avg_achievement'], 1) : '-' }}</p>
        <p><strong>إجمالي الودائع:</strong> {{ $report['totals']['total_deposits'] ?? 0 }}</p>
        <p><strong>إجمالي الإنذارات:</strong> {{ $report['totals']['total_warnings'] ?? 0 }}</p>
    </div>
    @endif

    <p style="text-align: center; font-size: 9px; color: #999; margin-top: 25px;">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز | {{ $generated_at }}</p>
</body>
</html>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>خطة تسويق - Marketing Plan #{{ $plan->id }}</title>
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
        .info-table { width: 100%; border-collapse: collapse; margin: 5px 0; }
        .info-table td { padding: 7px 8px; border: 1px solid #ddd; font-size: 10px; }
        .info-table td:first-child { width: 40%; background-color: #f5f5f5; font-weight: bold; color: #444; }
        .data-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .data-table th { background-color: #8B8B8B; color: #fff; padding: 8px 6px; border: 1px solid #7B7B7B; font-size: 10px; text-align: center; font-weight: bold; }
        .data-table td { padding: 8px 6px; border: 1px solid #ddd; text-align: center; font-size: 10px; }
        .data-table tr:nth-child(even) { background-color: #f9f9f9; }
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

    <p class="doc-title">خطة تسويق / Marketing Plan #{{ $plan->id }}</p>
    <p class="doc-subtitle">المشروع: {{ $plan->marketingProject->contract->project_name ?? '-' }}</p>

    <p class="section-title">&#9670; معلومات الخطة / Plan Information</p>
    <table class="info-table">
        <tr><td>المستخدم / User</td><td>{{ $plan->user->name ?? '-' }}</td></tr>
        <tr><td>قيمة العمولة / Commission Value</td><td>{{ $plan->commission_value }}</td></tr>
        <tr><td>قيمة التسويق / Marketing Value</td><td>{{ $plan->marketing_value }}</td></tr>
    </table>

    @if(!empty($plan->platform_distribution))
    <p class="section-title">&#9670; توزيع المنصات / Platform Distribution</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>المنصة / Platform</th>
                <th>النسبة / Percentage</th>
            </tr>
        </thead>
        <tbody>
            @foreach($plan->platform_distribution as $platform => $percentage)
            <tr>
                <td>{{ $platform }}</td>
                <td>{{ $percentage }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(!empty($plan->campaign_distribution))
    <p class="section-title">&#9670; توزيع الحملات / Campaign Distribution</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>الحملة / Campaign</th>
                <th>النسبة / Percentage</th>
            </tr>
        </thead>
        <tbody>
            @foreach($plan->campaign_distribution as $campaign => $percentage)
            <tr>
                <td>{{ $campaign }}</td>
                <td>{{ $percentage }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <p style="text-align: center; font-size: 9px; color: #999; margin-top: 25px;">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز</p>
</body>
</html>

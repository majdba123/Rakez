<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>عقد مشروع حصري - Exclusive Project Contract</title>
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
            line-height: 1.6;
        }
        .logo-area { text-align: center; padding: 10px 0 15px 0; margin-bottom: 15px; }
        .logo-area img { height: 90px; width: auto; }
        .doc-title { text-align: center; font-size: 16px; font-weight: bold; color: #1B2A4A; margin: 10px 0 3px 0; }
        .doc-title-en { text-align: center; font-size: 12px; color: #666; margin: 0 0 5px 0; }
        .doc-subtitle { text-align: center; font-size: 10px; color: #666; margin: 0 0 15px 0; }
        .section-title { font-size: 13px; font-weight: bold; color: #222; margin: 18px 0 8px 0; padding-bottom: 5px; border-bottom: 1px solid #999; }
        .info-table { width: 100%; border-collapse: collapse; margin: 5px 0; }
        .info-table td { padding: 7px 8px; border: 1px solid #ddd; font-size: 10px; }
        .info-table td:first-child { width: 40%; background-color: #f5f5f5; font-weight: bold; color: #444; }
        .data-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .data-table th { background-color: #8B8B8B; color: #fff; padding: 8px 6px; border: 1px solid #7B7B7B; font-size: 10px; text-align: center; font-weight: bold; }
        .data-table td { padding: 8px 6px; border: 1px solid #ddd; text-align: center; font-size: 10px; }
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

    <p class="doc-title">عقد مشروع حصري</p>
    <p class="doc-title-en">Exclusive Project Contract</p>
    <p class="doc-subtitle">رقم الطلب: {{ $request->id }} | التاريخ: {{ now()->format('Y-m-d') }}</p>

    <p class="section-title">&#9670; معلومات المشروع / Project Information</p>
    <table class="info-table">
        <tr><td>اسم المشروع / Project Name</td><td>{{ $request->project_name }}</td></tr>
        <tr><td>اسم المطور / Developer Name</td><td>{{ $request->developer_name }}</td></tr>
        <tr><td>رقم التواصل / Contact Number</td><td>{{ $request->developer_contact }}</td></tr>
        <tr><td>المدينة / City</td><td>{{ $request->location_city }}</td></tr>
        @if($request->location_district)
        <tr><td>الحي / District</td><td>{{ $request->location_district }}</td></tr>
        @endif
        @if($request->estimated_units)
        <tr><td>عدد الوحدات المتوقع / Estimated Units</td><td>{{ $request->estimated_units }}</td></tr>
        @endif
    </table>

    @if($request->project_description)
    <p class="section-title">&#9670; وصف المشروع / Project Description</p>
    <p style="font-size: 10px; padding: 5px; background: #f9f9f9; border: 1px solid #eee;">{{ $request->project_description }}</p>
    @endif

    @if($contract && $contract->units)
    <p class="section-title">&#9670; الوحدات / Units</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>النوع / Type</th>
                <th>العدد / Count</th>
                <th>السعر / Price</th>
                <th>الإجمالي / Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($contract->units as $unit)
            <tr>
                <td>{{ $unit['type'] }}</td>
                <td>{{ $unit['count'] }}</td>
                <td>{{ number_format($unit['price'], 2) }} ريال</td>
                <td>{{ number_format($unit['count'] * $unit['price'], 2) }} ريال</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <p class="section-title">&#9670; معلومات الموافقة / Approval Information</p>
    <table class="info-table">
        <tr><td>تم الطلب بواسطة / Requested By</td><td>{{ $request->requestedBy->name }}</td></tr>
        @if($request->approvedBy)
        <tr><td>تمت الموافقة بواسطة / Approved By</td><td>{{ $request->approvedBy->name }}</td></tr>
        <tr><td>تاريخ الموافقة / Approval Date</td><td>{{ $request->approved_at->format('Y-m-d') }}</td></tr>
        @endif
        @if($request->contract_completed_at)
        <tr><td>تاريخ إكمال العقد / Completion Date</td><td>{{ $request->contract_completed_at->format('Y-m-d') }}</td></tr>
        @endif
    </table>

    <p style="text-align: center; font-size: 9px; color: #999; margin-top: 30px;">هذا المستند تم إنشاؤه تلقائياً من نظام إدارة المشاريع | تاريخ الطباعة: {{ now()->format('Y-m-d H:i:s') }}</p>
</body>
</html>

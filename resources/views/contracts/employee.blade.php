<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>عقد موظف - {{ $employee->name ?? '' }}</title>
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
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .status-draft { background-color: #FFC107; color: #000; }
        .status-active { background-color: #4CAF50; color: #fff; }
        .status-expired { background-color: #9E9E9E; color: #fff; }
        .status-terminated { background-color: #F44336; color: #fff; }
        .contract-terms { margin: 5px 0; padding: 8px; background: #fafafa; border: 1px solid #eee; }
        .contract-terms table { width: 100%; border-collapse: collapse; }
        .contract-terms table td { padding: 5px 8px; border: 1px solid #eee; font-size: 10px; }
        .contract-terms table td:first-child { width: 40%; font-weight: bold; color: #555; background: #f5f5f5; }
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 40px; }
        .sig-table td { padding: 10px; text-align: center; font-size: 10px; width: 50%; }
        .sig-line { border-top: 1px solid #333; margin-top: 45px; padding-top: 5px; }
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

    <p class="doc-title">عقد عمل موظف</p>
    <p class="doc-title-en">Employee Contract</p>
    <p class="doc-subtitle">رقم العقد: {{ $contract->id }} | تاريخ الإصدار: {{ $generated_at }}</p>

    <p class="section-title">&#9670; بيانات الموظف / Employee Information</p>
    <table class="info-table">
        <tr><td>اسم الموظف / Name</td><td>{{ $employee->name ?? '-' }}</td></tr>
        <tr><td>البريد الإلكتروني / Email</td><td>{{ $employee->email ?? '-' }}</td></tr>
        @if($employee->phone ?? null)
        <tr><td>رقم الجوال / Phone</td><td>{{ $employee->phone }}</td></tr>
        @endif
        @if($employee->type ?? null)
        <tr><td>القسم / Department</td><td>{{ $employee->type }}</td></tr>
        @endif
    </table>

    <p class="section-title">&#9670; بيانات العقد / Contract Details</p>
    <table class="info-table">
        <tr><td>تاريخ بداية العقد / Start Date</td><td>{{ $contract->start_date?->format('Y-m-d') ?? '-' }}</td></tr>
        <tr><td>تاريخ نهاية العقد / End Date</td><td>{{ $contract->end_date?->format('Y-m-d') ?? 'غير محدد / Open-ended' }}</td></tr>
        <tr>
            <td>حالة العقد / Status</td>
            <td>
                <span class="status-badge status-{{ $contract->status }}">
                    @switch($contract->status)
                        @case('draft') مسودة / Draft @break
                        @case('active') نشط / Active @break
                        @case('expired') منتهي / Expired @break
                        @case('terminated') ملغي / Terminated @break
                        @default {{ $contract->status }}
                    @endswitch
                </span>
            </td>
        </tr>
    </table>

    @if(!empty($contract_data))
    <p class="section-title">&#9670; تفاصيل العقد / Contract Terms</p>
    <div class="contract-terms">
        <table>
            @foreach($contract_data as $key => $value)
                @if(!is_array($value) && !is_null($value))
                <tr>
                    <td>{{ str_replace('_', ' ', $key) }}</td>
                    <td>{{ $value }}</td>
                </tr>
                @endif
            @endforeach
        </table>
    </div>
    @endif

    <table class="sig-table">
        <tr>
            <td>توقيع الموظف / Employee Signature</td>
            <td>توقيع المسؤول / Manager Signature</td>
        </tr>
        <tr>
            <td><div class="sig-line">&nbsp;</div></td>
            <td><div class="sig-line">&nbsp;</div></td>
        </tr>
    </table>

    <p style="text-align: center; font-size: 9px; color: #999; margin-top: 25px;">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز | {{ $generated_at }}</p>
</body>
</html>

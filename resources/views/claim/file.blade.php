<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>ملف المطالبة - {{ $file_data['project_name'] ?? 'غير محدد' }}</title>
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
        .ref-box { text-align: center; padding: 8px; background: #f0f0f0; font-size: 12px; font-weight: bold; margin-bottom: 15px; border: 1px solid #ddd; }
        .section-title { font-size: 13px; font-weight: bold; color: #222; margin: 18px 0 8px 0; padding-bottom: 5px; border-bottom: 1px solid #999; }
        .info-table { width: 100%; border-collapse: collapse; margin: 5px 0; }
        .info-table td { padding: 6px 8px; border: 1px solid #ddd; font-size: 10px; }
        .info-table td:first-child { width: 35%; background-color: #f5f5f5; font-weight: bold; color: #444; }
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

    <p class="doc-title">ملف المطالبة / Claim File</p>
    <p class="doc-subtitle">{{ $generated_at }}</p>

    <div class="ref-box">
        رقم المطالبة: {{ $claim_file->id }} | رقم الحجز: {{ $file_data['reservation_id'] }}
    </div>

    <p class="section-title">&#9670; بيانات المشروع</p>
    <table class="info-table">
        <tr><td>اسم المشروع</td><td>{{ $file_data['project_name'] ?? '-' }}</td></tr>
        <tr><td>الموقع</td><td>{{ $file_data['project_location'] ?? '-' }}</td></tr>
    </table>

    <p class="section-title">&#9670; بيانات الوحدة</p>
    <table class="info-table">
        <tr><td>رقم الوحدة</td><td>{{ $file_data['unit_number'] ?? '-' }}</td></tr>
        <tr><td>نوع الوحدة</td><td>{{ $file_data['unit_type'] ?? '-' }}</td></tr>
        <tr><td>المساحة</td><td>{{ $file_data['unit_area'] ? $file_data['unit_area'] . ' م²' : '-' }}</td></tr>
        <tr><td>السعر</td><td>{{ $file_data['unit_price'] ? number_format($file_data['unit_price'], 2) . ' ريال' : '-' }}</td></tr>
    </table>

    <p class="section-title">&#9670; بيانات العميل</p>
    <table class="info-table">
        <tr><td>اسم العميل</td><td>{{ $file_data['client_name'] ?? '-' }}</td></tr>
        <tr><td>رقم الجوال</td><td>{{ $file_data['client_mobile'] ?? '-' }}</td></tr>
        <tr><td>الجنسية</td><td>{{ $file_data['client_nationality'] ?? '-' }}</td></tr>
        <tr><td>رقم الآيبان</td><td>{{ $file_data['client_iban'] ?? '-' }}</td></tr>
    </table>

    <p class="section-title">&#9670; البيانات المالية</p>
    <table class="info-table">
        <tr><td>مبلغ العربون</td><td>{{ $file_data['down_payment_amount'] ? number_format($file_data['down_payment_amount'], 2) . ' ريال' : '-' }}</td></tr>
        <tr><td>حالة العربون</td><td>{{ $file_data['down_payment_status'] === 'refundable' ? 'مسترد' : 'غير مسترد' }}</td></tr>
        <tr>
            <td>طريقة الدفع</td>
            <td>
                @switch($file_data['payment_method'])
                    @case('cash') نقدي @break
                    @case('bank_transfer') تحويل بنكي @break
                    @case('bank_financing') تمويل بنكي @break
                    @default {{ $file_data['payment_method'] ?? '-' }}
                @endswitch
            </td>
        </tr>
        <tr>
            <td>آلية الشراء</td>
            <td>
                @switch($file_data['purchase_mechanism'])
                    @case('cash') كاش @break
                    @case('supported_bank') بنك مدعوم @break
                    @case('unsupported_bank') بنك غير مدعوم @break
                    @default {{ $file_data['purchase_mechanism'] ?? '-' }}
                @endswitch
            </td>
        </tr>
        <tr><td>نسبة عمولة السمسرة</td><td>{{ $file_data['brokerage_commission_percent'] ? $file_data['brokerage_commission_percent'] . '%' : '-' }}</td></tr>
        <tr>
            <td>العمولة على</td>
            <td>
                @if($file_data['commission_payer'] === 'seller') البائع
                @elseif($file_data['commission_payer'] === 'buyer') المشتري
                @else -
                @endif
            </td>
        </tr>
        <tr><td>مبلغ الضريبة</td><td>{{ $file_data['tax_amount'] ? number_format($file_data['tax_amount'], 2) . ' ريال' : '-' }}</td></tr>
    </table>

    <p class="section-title">&#9670; بيانات التسويق</p>
    <table class="info-table">
        <tr><td>اسم الفريق</td><td>{{ $file_data['team_name'] ?? '-' }}</td></tr>
        <tr><td>اسم المسوق</td><td>{{ $file_data['marketer_name'] ?? '-' }}</td></tr>
    </table>

    <p class="section-title">&#9670; التواريخ</p>
    <table class="info-table">
        <tr><td>تاريخ العقد</td><td>{{ $file_data['contract_date'] ?? '-' }}</td></tr>
        <tr><td>تاريخ تأكيد الحجز</td><td>{{ $file_data['confirmed_at'] ?? '-' }}</td></tr>
        <tr><td>تاريخ نقل الملكية</td><td>{{ $file_data['title_transfer_date'] ?? '-' }}</td></tr>
    </table>

    <p style="text-align: center; font-size: 9px; color: #999; margin-top: 25px;">تم إنشاء هذا الملف بواسطة نظام إدارة الحجوزات والائتمان | {{ $generated_at }}</p>
</body>
</html>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>ملف المطالبة - {{ $file_data['project_name'] ?? 'غير محدد' }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            direction: rtl;
            text-align: right;
            padding: 20px;
            font-size: 12px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            background-color: #f5f5f5;
            padding: 8px;
            margin-bottom: 10px;
            border-right: 4px solid #333;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .info-table td:first-child {
            width: 40%;
            background-color: #fafafa;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
        .claim-number {
            background-color: #f0f0f0;
            padding: 10px;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>ملف المطالبة</h1>
        <p>Claim File</p>
    </div>

    <div class="claim-number">
        رقم المطالبة: {{ $claim_file->id }}
        |
        رقم الحجز: {{ $file_data['reservation_id'] }}
    </div>

    <div class="section">
        <div class="section-title">بيانات المشروع</div>
        <table class="info-table">
            <tr>
                <td>اسم المشروع</td>
                <td>{{ $file_data['project_name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>الموقع</td>
                <td>{{ $file_data['project_location'] ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">بيانات الوحدة</div>
        <table class="info-table">
            <tr>
                <td>رقم الوحدة</td>
                <td>{{ $file_data['unit_number'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>نوع الوحدة</td>
                <td>{{ $file_data['unit_type'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>المساحة</td>
                <td>{{ $file_data['unit_area'] ? $file_data['unit_area'] . ' م²' : '-' }}</td>
            </tr>
            <tr>
                <td>السعر</td>
                <td>{{ $file_data['unit_price'] ? number_format($file_data['unit_price'], 2) . ' ريال' : '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">بيانات العميل</div>
        <table class="info-table">
            <tr>
                <td>اسم العميل</td>
                <td>{{ $file_data['client_name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>رقم الجوال</td>
                <td>{{ $file_data['client_mobile'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>الجنسية</td>
                <td>{{ $file_data['client_nationality'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>رقم الآيبان</td>
                <td>{{ $file_data['client_iban'] ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">البيانات المالية</div>
        <table class="info-table">
            <tr>
                <td>مبلغ العربون</td>
                <td>{{ $file_data['down_payment_amount'] ? number_format($file_data['down_payment_amount'], 2) . ' ريال' : '-' }}</td>
            </tr>
            <tr>
                <td>حالة العربون</td>
                <td>{{ $file_data['down_payment_status'] === 'refundable' ? 'مسترد' : 'غير مسترد' }}</td>
            </tr>
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
            <tr>
                <td>نسبة عمولة السمسرة</td>
                <td>{{ $file_data['brokerage_commission_percent'] ? $file_data['brokerage_commission_percent'] . '%' : '-' }}</td>
            </tr>
            <tr>
                <td>العمولة على</td>
                <td>
                    @if($file_data['commission_payer'] === 'seller')
                        البائع
                    @elseif($file_data['commission_payer'] === 'buyer')
                        المشتري
                    @else
                        -
                    @endif
                </td>
            </tr>
            <tr>
                <td>مبلغ الضريبة</td>
                <td>{{ $file_data['tax_amount'] ? number_format($file_data['tax_amount'], 2) . ' ريال' : '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">بيانات التسويق</div>
        <table class="info-table">
            <tr>
                <td>اسم الفريق</td>
                <td>{{ $file_data['team_name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>اسم المسوق</td>
                <td>{{ $file_data['marketer_name'] ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">التواريخ</div>
        <table class="info-table">
            <tr>
                <td>تاريخ العقد</td>
                <td>{{ $file_data['contract_date'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>تاريخ تأكيد الحجز</td>
                <td>{{ $file_data['confirmed_at'] ?? '-' }}</td>
            </tr>
            <tr>
                <td>تاريخ نقل الملكية</td>
                <td>{{ $file_data['title_transfer_date'] ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>تم إنشاء هذا الملف بواسطة نظام إدارة الحجوزات والائتمان</p>
        <p>{{ $generated_at }}</p>
    </div>
</body>
</html>


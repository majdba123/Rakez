@extends('layouts.pdf')

@section('title', 'ملف المطالبة - ' . ($file_data['project_name'] ?? 'غير محدد'))

@section('extra-styles')
    .ref-box { text-align: center; padding: 8px; background: #f0f0f0; font-size: 12px; font-weight: bold; margin-bottom: 15px; border: 1px solid #ddd; }
@endsection

@section('content')
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

    <p class="auto-msg">تم إنشاء هذا الملف بواسطة نظام إدارة الحجوزات والائتمان | {{ $generated_at }}</p>
@endsection

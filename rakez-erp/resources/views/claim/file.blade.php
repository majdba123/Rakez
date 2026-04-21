@php
    $formatClaimValue = static function ($key, $val) {
        if ($val === null || $val === '') {
            return '—';
        }
        $k = (string) $key;
        if (str_contains($k, 'commission_percent') || str_ends_with($k, '_percent')) {
            return is_numeric($val) ? $val.'%' : (string) $val;
        }
        if (is_numeric($val) && (str_contains($k, 'price') || str_contains($k, 'amount') || str_contains($k, 'tax'))) {
            return number_format((float) $val, 2).' ريال';
        }
        if ($k === 'unit_area' && is_numeric($val)) {
            return number_format((float) $val, 2);
        }

        return is_scalar($val) ? (string) $val : json_encode($val, JSON_UNESCAPED_UNICODE);
    };
    $fd = $file_data ?? [];
    $isCombined = !empty($fd['summary']) && isset($fd['items']) && is_array($fd['items']);
    $labels = [
        'project_name' => 'اسم المشروع',
        'project_location' => 'المدينة',
        'project_district' => 'الحي',
        'unit_number' => 'رقم الوحدة',
        'unit_type' => 'نوع الوحدة',
        'unit_area' => 'المساحة',
        'unit_price' => 'سعر الوحدة',
        'client_name' => 'اسم العميل',
        'client_mobile' => 'جوال العميل',
        'client_nationality' => 'الجنسية',
        'client_iban' => 'الآيبان',
        'down_payment_amount' => 'مبلغ العربون',
        'down_payment_status' => 'حالة العربون',
        'payment_method' => 'طريقة الدفع',
        'purchase_mechanism' => 'آلية الشراء',
        'brokerage_commission_percent' => 'نسبة عمولة الوساطة',
        'commission_payer' => 'دافع العمولة',
        'tax_amount' => 'مبلغ الضريبة',
        'team_name' => 'فريق المبيعات',
        'marketer_name' => 'مسوّق',
        'contract_date' => 'تاريخ العقد',
        'confirmed_at' => 'تاريخ التأكيد',
        'title_transfer_date' => 'تاريخ نقل الملكية',
        'reservation_id' => 'رقم الحجز',
        'reservation_type' => 'نوع الحجز',
    ];
@endphp

@extends('layouts.pdf')

@section('title', 'ملف مطالبة - ' . ($claim_file->id ?? ''))

@section('extra-styles')
    .totals-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    .totals-table td { padding: 8px; border: 1px solid #ddd; font-size: 11px; }
    .totals-table td:first-child { width: 45%; background-color: #f5f5f5; font-weight: bold; }
@endsection

@section('content')
    <p class="doc-title">ملف مطالبة عمولة</p>
    <p class="doc-subtitle">
        رقم الملف: {{ $claim_file->id ?? '—' }}
        @if(!empty($claim_file->is_combined))
            | <strong>ملف مجمّع</strong>
        @endif
        | تاريخ الإصدار: {{ $generated_at ?? '' }}
    </p>

    @if($isCombined)
        <p class="section-title first">&#9670; ملخص المشروع</p>
        @php $s = $fd['summary'] ?? []; @endphp
        <table class="info-table">
            <tr><td>اسم المشروع</td><td>{{ $s['project_name'] ?? '—' }}</td></tr>
            <tr><td>عدد الوحدات</td><td>{{ $s['reservation_count'] ?? '—' }}</td></tr>
            <tr><td>إجمالي أسعار الوحدات</td><td>{{ isset($s['total_unit_price']) ? number_format((float) $s['total_unit_price'], 2) . ' ريال' : '—' }}</td></tr>
            <tr><td>إجمالي المطالبة</td><td>{{ isset($s['total_claim_amount']) ? number_format((float) $s['total_claim_amount'], 2) . ' ريال' : '—' }}</td></tr>
            @if(!empty($claim_file->claim_type))
                <tr><td>نوع المطالبة</td><td>{{ $claim_file->claim_type }}</td></tr>
            @endif
            @if(!empty($claim_file->notes))
                <tr><td>ملاحظات</td><td>{{ $claim_file->notes }}</td></tr>
            @endif
        </table>

        <p class="section-title">&#9670; تفاصيل الوحدات</p>
        @foreach($fd['items'] as $idx => $item)
            <p style="font-weight:bold; margin:12px 0 6px 0; font-size:10pt;">وحدة {{ $idx + 1 }}</p>
            <table class="info-table">
                @foreach($item as $key => $val)
                    @continue(in_array($key, ['summary', 'items'], true))
                    <tr>
                        <td>{{ $labels[$key] ?? $key }}</td>
                        <td>{{ $formatClaimValue($key, $val) }}</td>
                    </tr>
                @endforeach
            </table>
        @endforeach
    @else
        <p class="section-title first">&#9670; بيانات المطالبة</p>
        <table class="info-table">
            @foreach($fd as $key => $val)
                @if($key === 'summary' || $key === 'items')
                    @continue
                @endif
                <tr>
                    <td>{{ $labels[$key] ?? $key }}</td>
                    <td>{{ $formatClaimValue($key, $val) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if(!empty($claim_file->generatedBy))
        <p class="section-title">&#9670; الإصدار</p>
        <table class="info-table">
            <tr><td>أصدر بواسطة</td><td>{{ $claim_file->generatedBy->name ?? '—' }}</td></tr>
        </table>
    @endif

    <p class="auto-msg">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز العقاري | {{ $generated_at ?? '' }}</p>
@endsection

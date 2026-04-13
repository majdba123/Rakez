@extends('layouts.pdf')

@php
    /** @var int|string $contractId */
    /** @var string|null $projectName */
    /** @var array<string,mixed>|iterable|null $plan payload from getPlanForDeveloper, or legacy flat key-value array */
    $pd = (is_array($plan ?? null) && array_key_exists('contract', $plan)) ? $plan : null;
@endphp

@section('title', 'خطة تسويق المطور - ' . ($projectName ?? ''))

@section('content')
    <div class="doc-title-wrap">
        <p class="doc-title">خطة تسويق المطور</p>
        <p class="doc-title-en" style="direction: ltr;">Developer Marketing Plan</p>
    </div>

    <p class="section-title first">معلومات المشروع</p>
    <table class="info-table">
        <tr><td>المشروع</td><td>{{ $projectName ?? '—' }}</td></tr>
        <tr><td>رقم العقد</td><td class="ltr">{{ $contractId }}</td></tr>
    </table>

    @if($pd)
        @php
            $c = $pd['contract'] ?? [];
        @endphp
        @php $pb = $c['pricing_basis'] ?? []; @endphp
        <p class="section-title">بيانات العقد (السعي والتسعير)</p>
        <table class="info-table">
            <tr><td>نسبة السعي %</td><td class="ltr">{{ $c['commission_percent'] ?? '—' }}</td></tr>
            <tr><td>مصدر التسعير</td><td class="ltr">{{ $pb['source'] ?? '—' }}</td></tr>
            <tr><td>إجمالي سعر الوحدات (أساس العمولة)</td><td class="ltr">{{ isset($pb['total_unit_price']) ? number_format((float) $pb['total_unit_price'], 2, '.', ',') : '—' }}</td></tr>
            <tr><td>متوسط سعر الوحدة (متاح)</td><td class="ltr">{{ isset($pb['average_unit_price']) ? number_format((float) $pb['average_unit_price'], 2, '.', ',') : '—' }}</td></tr>
            <tr><td>avg_property_value (مخزن)</td><td class="ltr">{{ isset($pb['avg_property_value_stored']) ? number_format((float) $pb['avg_property_value_stored'], 2, '.', ',') : '—' }}</td></tr>
            <tr><td>وحدات متاحة / إجمالي</td><td class="ltr">{{ ($pb['available_units_count'] ?? '—') }} / {{ ($pb['all_units_count'] ?? '—') }}</td></tr>
            <tr><td>متوسط سعر الوحدة (قديم)</td><td class="ltr">{{ isset($c['average_unit_price']) ? number_format((float) $c['average_unit_price'], 2, '.', ',') : '—' }}</td></tr>
        </table>

        @if(!empty($pd['plan']))
            <p class="section-title">ملخص خطة التسويق</p>
            <table class="info-table">
                <tr><td>ميزانية التسويق (ريال)</td><td class="ltr">{{ $pd['total_budget_display'] ?? (isset($pd['total_budget']) ? number_format((float) $pd['total_budget'], 2, '.', ',') : '—') }}</td></tr>
                <tr><td>مدة التسويق</td><td>{{ $pd['marketing_duration_ar'] ?? '—' }}</td></tr>
                <tr><td>الظهور المتوقع</td><td>{{ $pd['expected_impressions_display_ar'] ?? '—' }}</td></tr>
                <tr><td>النقرات المتوقعة</td><td>{{ $pd['expected_clicks_display_ar'] ?? '—' }}</td></tr>
            </table>

            @php $rp = $pd['plan']; @endphp
            <p class="section-title">تفاصيل إضافية</p>
            <table class="info-table">
                <tr><td>متوسط CPM</td><td class="ltr">{{ is_array($rp) ? ($rp['average_cpm'] ?? '—') : ($rp->average_cpm ?? '—') }}</td></tr>
                <tr><td>متوسط CPC</td><td class="ltr">{{ is_array($rp) ? ($rp['average_cpc'] ?? '—') : ($rp->average_cpc ?? '—') }}</td></tr>
                <tr><td>قيمة التسويق (خام)</td><td class="ltr">@if(is_array($rp) && isset($rp['marketing_value'])){{ number_format((float) $rp['marketing_value'], 2, '.', ',') }}@elseif(is_object($rp) && isset($rp->marketing_value)){{ number_format((float) $rp->marketing_value, 2, '.', ',') }}@else—@endif</td></tr>
                <tr><td>نسبة التسويق %</td><td class="ltr">{{ is_array($rp) ? ($rp['marketing_percent'] ?? '—') : ($rp->marketing_percent ?? '—') }}</td></tr>
            </table>

            @if(!empty($pd['platforms']) && is_array($pd['platforms']))
                <p class="section-title">المنصات</p>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>المنصة</th>
                            <th>CPM</th>
                            <th>CPC</th>
                            <th>مشاهدات</th>
                            <th>نقرات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pd['platforms'] as $row)
                            @php
                                if (! is_array($row)) { continue; }
                                $name = $row['platform_name_ar'] ?? $row['platform_key'] ?? '—';
                            @endphp
                            <tr>
                                <td>{{ $name }}</td>
                                <td class="ltr">{{ $row['cpm'] ?? '—' }}</td>
                                <td class="ltr">{{ $row['cpc'] ?? '—' }}</td>
                                <td class="ltr">{{ $row['views'] ?? '—' }}</td>
                                <td class="ltr">{{ $row['clicks'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    @elseif(!empty($plan) && is_iterable($plan))
        <p class="section-title">تفاصيل الخطة</p>
        <table class="data-table">
            <thead>
                <tr>
                    <th>البيان</th>
                    <th>القيمة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($plan as $key => $value)
                    @if(!is_array($value) && !is_object($value) && !is_null($value))
                        <tr>
                            <td>{{ str_replace('_', ' ', (string) $key) }}</td>
                            <td>{{ $value }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="auto-msg">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز العقاري</p>
@endsection

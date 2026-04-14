@extends('layouts.pdf_sand_qabd')

@php
    /** @var int|string $contractId */
    /** @var string|null $projectName */
    /** @var array<string,mixed>|iterable|null $plan payload from getPlanForDeveloper, or legacy flat key-value array */
    $pd = (is_array($plan ?? null) && array_key_exists('contract', $plan)) ? $plan : null;
@endphp

@section('title', 'خطة تسويق المطور - ' . ($projectName ?? ''))

@section('content')
    <p class="sand-title">خطة تسويق المطور</p>
    <p class="sand-title-en" dir="ltr">Developer marketing plan</p>
    <p class="sand-subtitle">
        رقم العقد: <span class="ltr">{{ $contractId }}</span>
        @if(filled($projectName))
            — المشروع: {{ $projectName }}
        @endif
    </p>

    <table class="sand-meta-line" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 55%;">
                <strong>مرجع الوثيقة /</strong>
                تقرير خطة تسويق المطور (مستخرج من النظام)
            </td>
            <td style="width: 45%; text-align: left; direction: ltr;">
                <strong>Contract</strong> <span class="ltr">{{ $contractId }}</span>
            </td>
        </tr>
    </table>

    <p class="sand-section first">معلومات المشروع</p>
    <table class="sand-kv" cellpadding="0" cellspacing="0">
        <tr><td>المشروع</td><td>{{ $projectName ?? '—' }}</td></tr>
        <tr><td>رقم العقد</td><td class="sand-val-ltr">{{ $contractId }}</td></tr>
    </table>

    @if($pd)
        @php
            $c = $pd['contract'] ?? [];
        @endphp
        @php $pb = $c['pricing_basis'] ?? []; @endphp
        <p class="sand-section">بيانات العقد (السعي والتسعير)</p>
        <table class="sand-kv" cellpadding="0" cellspacing="0">
            <tr><td>نسبة السعي %</td><td class="sand-val-ltr">{{ $c['commission_percent'] ?? '—' }}</td></tr>
            <tr><td>مصدر التسعير</td><td class="sand-val-ltr">{{ $pb['source'] ?? '—' }}</td></tr>
            <tr><td>إجمالي سعر الوحدات (أساس العمولة)</td><td class="sand-val-ltr">{{ isset($pb['total_unit_price']) ? number_format((float) $pb['total_unit_price'], 2, '.', ',') : '—' }}</td></tr>
            <tr><td>متوسط سعر الوحدة (كل الوحدات)</td><td class="sand-val-ltr">{{ isset($pb['average_unit_price_all']) ? number_format((float) $pb['average_unit_price_all'], 2, '.', ',') : '—' }}</td></tr>
            <tr><td>متوسط سعر الوحدة (متاح فقط)</td><td class="sand-val-ltr">{{ isset($pb['average_unit_price_available']) ? number_format((float) $pb['average_unit_price_available'], 2, '.', ',') : '—' }}</td></tr>
            <tr><td>avg_property_value (مخزن)</td><td class="sand-val-ltr">{{ isset($pb['avg_property_value_stored']) ? number_format((float) $pb['avg_property_value_stored'], 2, '.', ',') : '—' }}</td></tr>
            <tr><td>وحدات متاحة / إجمالي</td><td class="sand-val-ltr">{{ ($pb['available_units_count'] ?? '—') }} / {{ ($pb['all_units_count'] ?? '—') }}</td></tr>
        </table>

        @if(!empty($pd['calculated_contract_budget']))
            <p class="sand-section">الحسابات (العمولة والتسويق)</p>
            <table class="sand-kv" cellpadding="0" cellspacing="0">
                <tr><td>إجمالي العمولة (محسوبة)</td><td class="sand-val-ltr">{{ isset($pd['calculated_contract_budget']['commission_value']) ? number_format((float) $pd['calculated_contract_budget']['commission_value'], 2, '.', ',') : '—' }}</td></tr>
                <tr><td>قيمة التسويق (محسوبة)</td><td class="sand-val-ltr">{{ isset($pd['calculated_contract_budget']['marketing_value']) ? number_format((float) $pd['calculated_contract_budget']['marketing_value'], 2, '.', ',') : '—' }}</td></tr>
            </table>
        @endif

        @if(!empty($pd['plan']))
            <p class="sand-section">ملخص خطة التسويق</p>
            <table class="sand-kv" cellpadding="0" cellspacing="0">
                <tr><td>ميزانية التسويق (محسوبة — للعرض)</td><td class="sand-val-ltr">{{ $pd['total_budget_display'] ?? (isset($pd['total_budget']) ? number_format((float) $pd['total_budget'], 2, '.', ',') : '—') }}</td></tr>
                <tr><td>ميزانية مخزنة (آخر حفظ)</td><td class="sand-val-ltr">{{ $pd['stored_marketing_value_display'] ?? '—' }}</td></tr>
                <tr><td>مدة التسويق</td><td>{{ $pd['marketing_duration_ar'] ?? '—' }}</td></tr>
                <tr><td>الظهور المتوقع</td><td>{{ $pd['expected_impressions_display_ar'] ?? '—' }}</td></tr>
                <tr><td>النقرات المتوقعة</td><td>{{ $pd['expected_clicks_display_ar'] ?? '—' }}</td></tr>
            </table>

            @php $rp = $pd['plan']; @endphp
            <p class="sand-section">تفاصيل إضافية</p>
            <table class="sand-kv" cellpadding="0" cellspacing="0">
                <tr><td>متوسط CPM</td><td class="sand-val-ltr">{{ is_array($rp) ? ($rp['average_cpm'] ?? '—') : ($rp->average_cpm ?? '—') }}</td></tr>
                <tr><td>متوسط CPC</td><td class="sand-val-ltr">{{ is_array($rp) ? ($rp['average_cpc'] ?? '—') : ($rp->average_cpc ?? '—') }}</td></tr>
                <tr><td>قيمة التسويق (خام)</td><td class="sand-val-ltr">@if(is_array($rp) && isset($rp['marketing_value'])){{ number_format((float) $rp['marketing_value'], 2, '.', ',') }}@elseif(is_object($rp) && isset($rp->marketing_value)){{ number_format((float) $rp->marketing_value, 2, '.', ',') }}@else—@endif</td></tr>
                <tr><td>نسبة التسويق %</td><td class="sand-val-ltr">{{ is_array($rp) ? ($rp['marketing_percent'] ?? '—') : ($rp->marketing_percent ?? '—') }}</td></tr>
            </table>

            @if(!empty($pd['platforms']) && is_array($pd['platforms']))
                <p class="sand-section">المنصات</p>
                <table class="sand-grid" cellpadding="0" cellspacing="0">
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
                                <td class="sand-val-ltr">{{ $row['cpm'] ?? '—' }}</td>
                                <td class="sand-val-ltr">{{ $row['cpc'] ?? '—' }}</td>
                                <td class="sand-val-ltr">{{ $row['views'] ?? '—' }}</td>
                                <td class="sand-val-ltr">{{ $row['clicks'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endif
    @elseif(!empty($plan) && is_iterable($plan))
        <p class="sand-section">تفاصيل الخطة</p>
        <table class="sand-grid" cellpadding="0" cellspacing="0">
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
                            <td class="sand-val-ltr">{{ $value }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="sand-auto-msg">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز العقارية — خطة تسويق المطور</p>
@endsection

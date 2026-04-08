@extends('layouts.pdf')

@php /** @var \App\Models\Contract $contract */ @endphp
@php /** @var \App\Models\ContractInfo $info */ @endphp

@section('title', 'معلومات العقد — ' . ($contract->project_name ?? $contract->id))

@section('content')
    <p class="doc-title">معلومات العقد</p>
    <p class="doc-title-en" style="direction: ltr;">Contract information only</p>
    <p class="doc-subtitle">
        المشروع: {{ $contract->project_name ?? '—' }}
        — رقم العقد (النظام): <span class="ltr">{{ $contract->id }}</span>
        — تم الإنشاء: {{ $generated_at }}
    </p>

    <p class="section-title">البيانات الرسمية</p>
    <table class="info-table">
        <tr>
            <td>رقم العقد</td>
            <td class="ltr">{{ $info->contract_number ?? '—' }}</td>
        </tr>
    </table>

    <p class="section-title">الطرف الأول (الشركة)</p>
    <table class="info-table">
        <tr><td>الاسم</td><td>{{ $info->first_party_name ?? '—' }}</td></tr>
        <tr><td>السجل التجاري</td><td class="ltr">{{ $info->first_party_cr_number ?? '—' }}</td></tr>
        <tr><td>المفوّض بالتوقيع</td><td>{{ $info->first_party_signatory ?? '—' }}</td></tr>
        <tr><td>الجوال</td><td class="ltr">{{ $info->first_party_phone ?? '—' }}</td></tr>
        <tr><td>البريد الإلكتروني</td><td class="ltr">{{ $info->first_party_email ?? '—' }}</td></tr>
    </table>

    <p class="section-title">الطرف الثاني</p>
    <table class="info-table">
        <tr><td>الاسم</td><td>{{ $info->second_party_name ?? '—' }}</td></tr>
        <tr><td>العنوان</td><td>{{ $info->second_party_address ?? '—' }}</td></tr>
        <tr><td>السجل التجاري</td><td class="ltr">{{ $info->second_party_cr_number ?? '—' }}</td></tr>
        <tr><td>رقم الهوية</td><td class="ltr">{{ $info->second_party_id_number ?? '—' }}</td></tr>
        <tr><td>المفوّض بالتوقيع</td><td>{{ $info->second_party_signatory ?? '—' }}</td></tr>
        <tr><td>صفة الموقّع</td><td>{{ $info->second_party_role ?? '—' }}</td></tr>
        <tr><td>الجوال</td><td class="ltr">{{ $info->second_party_phone ?? '—' }}</td></tr>
        <tr><td>البريد الإلكتروني</td><td class="ltr">{{ $info->second_party_email ?? '—' }}</td></tr>
    </table>

    <p class="section-title">التواريخ والموقع</p>
    <table class="info-table">
        <tr><td>التاريخ الميلادي</td><td>{{ $info->gregorian_date ? $info->gregorian_date->format('Y-m-d') : '—' }}</td></tr>
        <tr><td>التاريخ الهجري</td><td>{{ $info->hijri_date ?? '—' }}</td></tr>
        <tr><td>مدينة العقد</td><td>{{ $info->contract_city ?? '—' }}</td></tr>
        <tr><td>رابط الموقع</td><td class="ltr" style="word-break: break-all;">{{ $info->location_url ?? '—' }}</td></tr>
    </table>

    <p class="section-title">مدة الاتفاق والوكالة</p>
    <table class="info-table">
        <tr><td>مدة الاتفاق (أيام)</td><td class="ltr">{{ $info->agreement_duration_days !== null ? $info->agreement_duration_days : '—' }}</td></tr>
        <tr><td>مدة الاتفاق (أشهر)</td><td class="ltr">{{ $info->agreement_duration_months !== null ? $info->agreement_duration_months : '—' }}</td></tr>
        <tr><td>رقم الوكالة</td><td class="ltr">{{ $info->agency_number ?? '—' }}</td></tr>
        <tr><td>تاريخ الوكالة</td><td>{{ $info->agency_date ? $info->agency_date->format('Y-m-d') : '—' }}</td></tr>
        <tr><td>متوسط قيمة العقار</td><td class="ltr">{{ $info->avg_property_value !== null ? number_format((float) $info->avg_property_value, 2, '.', ',') : '—' }}</td></tr>
        <tr><td>تاريخ الإفراج</td><td>{{ $info->release_date ? $info->release_date->format('Y-m-d') : '—' }}</td></tr>
    </table>

    <p class="auto-msg">وثيقة معلومات العقد فقط — نظام راكز</p>
@endsection

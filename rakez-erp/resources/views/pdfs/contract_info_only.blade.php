@extends('layouts.pdf_sand_qabd')

@php
    /** @var \App\Models\ContractInfo $info */
    /** @var array<string,string> $info_display */
@endphp

@section('title', 'معلومات العقد — سجل ' . ($info_display['info_record_id'] ?? ''))

@section('content')
    <p class="sand-title">معلومات العقد</p>
    <p class="sand-title-en" dir="ltr">Contract information record</p>
    <p class="sand-subtitle">
        معرف السجل (contract_infos): <span class="ltr">{{ $info_display['info_record_id'] }}</span>
        — مرجع العقد (contract_id): <span class="ltr">{{ $info_display['contract_id'] }}</span>
        — تم إنشاء المستند: {{ $generated_at }}
    </p>

    <table class="sand-meta-line" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 55%;">
                <strong>مرجع الوثيقة /</strong>
                عقد إحالة تسويق — بيانات مسجلة
            </td>
            <td style="width: 45%; text-align: left; direction: ltr;">
                <strong>Contract #</strong> <span class="ltr">{{ $info_display['contract_id'] }}</span>
            </td>
        </tr>
    </table>

    <p class="sand-section first">البيانات الرسمية</p>
    <table class="sand-kv" cellpadding="0" cellspacing="0">
        <tr>
            <td>رقم العقد</td>
            <td class="ltr">{{ $info_display['contract_number'] }}</td>
        </tr>
    </table>

    <p class="sand-section">الطرف الأول (الشركة)</p>
    <table class="sand-kv" cellpadding="0" cellspacing="0">
        <tr><td>الاسم</td><td>{{ $info_display['first_party_name'] }}</td></tr>
        <tr><td>السجل التجاري</td><td class="ltr">{{ $info_display['first_party_cr_number'] }}</td></tr>
        <tr><td>المفوّض بالتوقيع</td><td>{{ $info_display['first_party_signatory'] }}</td></tr>
        <tr><td>الجوال</td><td class="ltr">{{ $info_display['first_party_phone'] }}</td></tr>
        <tr><td>البريد الإلكتروني</td><td class="ltr">{{ $info_display['first_party_email'] }}</td></tr>
    </table>

    <p class="sand-section">الطرف الثاني</p>
    <table class="sand-kv" cellpadding="0" cellspacing="0">
        <tr><td>الاسم</td><td>{{ $info_display['second_party_name'] }}</td></tr>
        <tr><td>العنوان</td><td>{{ $info_display['second_party_address'] }}</td></tr>
        <tr><td>السجل التجاري</td><td class="ltr">{{ $info_display['second_party_cr_number'] }}</td></tr>
        <tr><td>رقم الهوية</td><td class="ltr">{{ $info_display['second_party_id_number'] }}</td></tr>
        <tr><td>المفوّض بالتوقيع</td><td>{{ $info_display['second_party_signatory'] }}</td></tr>
        <tr><td>صفة الموقّع</td><td>{{ $info_display['second_party_role'] }}</td></tr>
        <tr><td>الجوال</td><td class="ltr">{{ $info_display['second_party_phone'] }}</td></tr>
        <tr><td>البريد الإلكتروني</td><td class="ltr">{{ $info_display['second_party_email'] }}</td></tr>
    </table>

    <p class="sand-section">التواريخ والموقع</p>
    <table class="sand-kv" cellpadding="0" cellspacing="0">
        <tr><td>التاريخ الميلادي</td><td>{{ $info_display['gregorian_date'] }}</td></tr>
        <tr><td>التاريخ الهجري</td><td>{{ $info_display['hijri_date'] }}</td></tr>
        <tr><td>مدينة العقد</td><td>{{ $info_display['contract_city'] }}</td></tr>
        <tr><td>رابط الموقع</td><td class="ltr">{{ $info_display['location_url'] }}</td></tr>
    </table>

    <p class="sand-section">مدة الاتفاق والوكالة</p>
    <table class="sand-kv" cellpadding="0" cellspacing="0">
        <tr><td>مدة الاتفاق (أيام)</td><td class="ltr">{{ $info_display['agreement_duration_days'] }}</td></tr>
        <tr><td>مدة الاتفاق (أشهر)</td><td class="ltr">{{ $info_display['agreement_duration_months'] }}</td></tr>
        <tr><td>رقم الوكالة</td><td class="ltr">{{ $info_display['agency_number'] }}</td></tr>
        <tr><td>تاريخ الوكالة</td><td>{{ $info_display['agency_date'] }}</td></tr>
        <tr><td>متوسط قيمة العقار</td><td class="ltr">{{ $info_display['avg_property_value'] }}</td></tr>
        <tr><td>تاريخ الإفراج</td><td>{{ $info_display['release_date'] }}</td></tr>
    </table>

    <p class="sand-auto-msg">وثيقة من جدول معلومات العقد (ContractInfo) — نظام راكز العقارية</p>
@endsection

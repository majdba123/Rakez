@extends('layouts.pdf')

@php
    /** @var \App\Models\Contract $contract */
    /** @var array<string,string> $contract_main */
    /** @var array<string,string>|null $info_display */
    /** @var array<int,array<string,string>> $unit_rows */
    /** @var array<int,array<string,string>> $legacy_unit_rows */
    /** @var array<string,string>|null $second_party_flags */
    $unit_rows = $unit_rows ?? [];
    $legacy_unit_rows = $legacy_unit_rows ?? [];
@endphp

@section('title', 'بيانات العقد رقم ' . ($contract->id ?? ''))

@section('content')
    <div class="doc-title-wrap">
        <p class="doc-title">بيانات العقد</p>
        <p class="doc-title-en" style="direction: ltr;">Contract details</p>
        <p class="doc-subtitle">تم الإنشاء: {{ $generated_at }} — رقم العقد: <span class="ltr">{{ $contract->id }}</span>
            @if(($contract_main['code'] ?? '—') !== '—')
                — الكود: <span class="ltr">{{ $contract_main['code'] }}</span>
            @endif
        </p>
    </div>

    <p class="section-title first">المشروع والمطور</p>
    <table class="info-table">
        <tr>
            <td>اسم المشروع</td>
            <td>{{ $contract_main['project_name'] }}</td>
        </tr>
        <tr>
            <td>اسم المطور</td>
            <td>{{ $contract_main['developer_name'] }}</td>
        </tr>
        <tr>
            <td>رقم المطور</td>
            <td class="ltr">{{ $contract_main['developer_number'] }}</td>
        </tr>
        <tr>
            <td>المدينة</td>
            <td>{{ $contract_main['city'] }}</td>
        </tr>
        <tr>
            <td>الحي</td>
            <td>{{ $contract_main['district'] }}</td>
        </tr>
        <tr>
            <td>الجانب (الاتجاه)</td>
            <td>{{ $side_label_ar }}</td>
        </tr>
        <tr>
            <td>نوع العقد</td>
            <td>{{ $contract_type_ar }}</td>
        </tr>
        <tr>
            <td>حالة العقد</td>
            <td>{{ $status_label_ar }}</td>
        </tr>
        <tr>
            <td>متطلبات المطور</td>
            <td>{{ $contract_main['developer_requiment'] }}</td>
        </tr>
        <tr>
            <td>العمولة %</td>
            <td>{{ $contract_main['commission_percent'] }}</td>
        </tr>
        <tr>
            <td>العمولة على</td>
            <td>{{ $commission_from_ar }}</td>
        </tr>
        <tr>
            <td>ملاحظات</td>
            <td>{{ $contract_main['notes'] }}</td>
        </tr>
        @if(($contract_main['owner_name'] ?? '—') !== '—')
            <tr>
                <td>صاحب الطلب (الموظف)</td>
                <td>{{ $contract_main['owner_name'] }}</td>
            </tr>
        @endif
    </table>

    @if(!empty($info_display))
        <p class="section-title">معلومات العقد (الطرفان والتواريخ)</p>
        <table class="info-table">
            <tr>
                <td>رقم العقد</td>
                <td class="ltr">{{ $info_display['contract_number'] }}</td>
            </tr>
            <tr>
                <td>الطرف الأول — الاسم</td>
                <td>{{ $info_display['first_party_name'] }}</td>
            </tr>
            <tr>
                <td>الطرف الأول — السجل التجاري</td>
                <td class="ltr">{{ $info_display['first_party_cr_number'] }}</td>
            </tr>
            <tr>
                <td>الطرف الثاني — الاسم</td>
                <td>{{ $info_display['second_party_name'] }}</td>
            </tr>
            <tr>
                <td>الطرف الثاني — العنوان</td>
                <td>{{ $info_display['second_party_address'] }}</td>
            </tr>
            <tr>
                <td>الطرف الثاني — الجوال</td>
                <td class="ltr">{{ $info_display['second_party_phone'] }}</td>
            </tr>
            <tr>
                <td>التاريخ الميلادي</td>
                <td>{{ $info_display['gregorian_date'] }}</td>
            </tr>
            <tr>
                <td>التاريخ الهجري</td>
                <td>{{ $info_display['hijri_date'] }}</td>
            </tr>
            <tr>
                <td>مدة الاتفاق (أيام)</td>
                <td>{{ $info_display['agreement_duration_days'] }}</td>
            </tr>
            <tr>
                <td>مدينة العقد</td>
                <td>{{ $info_display['contract_city'] }}</td>
            </tr>
        </table>
    @endif

    @if(!empty($second_party_flags))
        <p class="section-title">بيانات الطرف الثاني (المرفقات)</p>
        <table class="info-table">
            <tr><td>أوراق العقار</td><td>{{ $second_party_flags['real_estate_papers_url'] }}</td></tr>
            <tr><td>المخططات والتجهيزات</td><td>{{ $second_party_flags['plans_equipment_docs_url'] }}</td></tr>
            <tr><td>شعار المشروع</td><td>{{ $second_party_flags['project_logo_url'] }}</td></tr>
            <tr><td>الأسعار والوحدات</td><td>{{ $second_party_flags['prices_units_url'] }}</td></tr>
            <tr><td>رخصة التسويق</td><td>{{ $second_party_flags['marketing_license_url'] }}</td></tr>
            <tr><td>رقم المعلن / القسم</td><td>{{ $second_party_flags['advertiser_section_url'] }}</td></tr>
        </table>
    @endif

    <p class="section-title">الوحدات</p>
    @if(count($unit_rows) > 0)
        <table class="data-table">
            <thead>
            <tr>
                <th>رقم الوحدة</th>
                <th>النوع</th>
                <th>الحالة</th>
                <th>السعر</th>
                <th>المساحة</th>
            </tr>
            </thead>
            <tbody>
            @foreach($unit_rows as $u)
                <tr>
                    <td class="ltr">{{ $u['unit_number'] }}</td>
                    <td>{{ $u['unit_type'] }}</td>
                    <td>{{ $u['status'] }}</td>
                    <td class="ltr">{{ $u['price'] }}</td>
                    <td>{{ $u['area'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @elseif(count($legacy_unit_rows) > 0)
        <table class="data-table">
            <thead>
            <tr>
                <th>نوع الوحدة</th>
                <th>العدد</th>
                <th>سعر الوحدة</th>
            </tr>
            </thead>
            <tbody>
            @foreach($legacy_unit_rows as $row)
                <tr>
                    <td>{{ $row['type'] }}</td>
                    <td class="ltr">{{ $row['count'] }}</td>
                    <td class="ltr">{{ $row['price'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="empty-msg">لا توجد وحدات مسجلة.</p>
    @endif

    <p class="auto-msg">وثيقة تلقائية من نظام راكز — لا تعتبر عقدًا نهائيًا دون التوقيع والاعتماد الرسمي.</p>
@endsection

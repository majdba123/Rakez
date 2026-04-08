@extends('layouts.pdf')

@section('title', 'بيانات العقد رقم ' . ($contract->id ?? ''))

@section('content')
    <p class="doc-title">بيانات العقد</p>
    <p class="doc-title-en" style="direction: ltr;">Contract details</p>
    <p class="doc-subtitle">تم الإنشاء: {{ $generated_at }} — رقم العقد: <span class="ltr">{{ $contract->id }}</span>
        @if(!empty($contract->code))
            — الكود: <span class="ltr">{{ $contract->code }}</span>
        @endif
    </p>

    <p class="section-title">المشروع والمطور</p>
    <table class="info-table">
        <tr>
            <td>اسم المشروع</td>
            <td>{{ $contract->project_name ?? '—' }}</td>
        </tr>
        <tr>
            <td>اسم المطور</td>
            <td>{{ $contract->developer_name ?? '—' }}</td>
        </tr>
        <tr>
            <td>رقم المطور</td>
            <td class="ltr">{{ $contract->developer_number ?? '—' }}</td>
        </tr>
        <tr>
            <td>المدينة</td>
            <td>{{ $contract->city?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td>الحي</td>
            <td>{{ $contract->district?->name ?? '—' }}</td>
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
            <td>{{ $contract->developer_requiment ?? '—' }}</td>
        </tr>
        <tr>
            <td>العمولة %</td>
            <td>{{ $contract->commission_percent !== null ? $contract->commission_percent . '%' : '—' }}</td>
        </tr>
        <tr>
            <td>العمولة على</td>
            <td>{{ $commission_from_ar }}</td>
        </tr>
        <tr>
            <td>ملاحظات</td>
            <td>{{ $contract->notes ?? '—' }}</td>
        </tr>
        @if($contract->user)
            <tr>
                <td>صاحب الطلب (الموظف)</td>
                <td>{{ $contract->user->name ?? '—' }}</td>
            </tr>
        @endif
    </table>

    @if($contract->info)
        @php $info = $contract->info; @endphp
        <p class="section-title">معلومات العقد (الطرفان والتواريخ)</p>
        <table class="info-table">
            <tr>
                <td>رقم العقد</td>
                <td class="ltr">{{ $info->contract_number ?? '—' }}</td>
            </tr>
            <tr>
                <td>الطرف الأول — الاسم</td>
                <td>{{ $info->first_party_name ?? '—' }}</td>
            </tr>
            <tr>
                <td>الطرف الأول — السجل التجاري</td>
                <td class="ltr">{{ $info->first_party_cr_number ?? '—' }}</td>
            </tr>
            <tr>
                <td>الطرف الثاني — الاسم</td>
                <td>{{ $info->second_party_name ?? '—' }}</td>
            </tr>
            <tr>
                <td>الطرف الثاني — العنوان</td>
                <td>{{ $info->second_party_address ?? '—' }}</td>
            </tr>
            <tr>
                <td>الطرف الثاني — الجوال</td>
                <td class="ltr">{{ $info->second_party_phone ?? '—' }}</td>
            </tr>
            <tr>
                <td>التاريخ الميلادي</td>
                <td>{{ $info->gregorian_date ? $info->gregorian_date->format('Y-m-d') : '—' }}</td>
            </tr>
            <tr>
                <td>التاريخ الهجري</td>
                <td>{{ $info->hijri_date ?? '—' }}</td>
            </tr>
            <tr>
                <td>مدة الاتفاق (أيام)</td>
                <td>{{ $info->agreement_duration_days !== null ? $info->agreement_duration_days : '—' }}</td>
            </tr>
            <tr>
                <td>مدينة العقد</td>
                <td>{{ $info->contract_city ?? '—' }}</td>
            </tr>
        </table>
    @endif

    @if($contract->secondPartyData)
        @php $spd = $contract->secondPartyData; @endphp
        <p class="section-title">بيانات الطرف الثاني (المرفقات)</p>
        <table class="info-table">
            <tr><td>أوراق العقار</td><td>{{ $spd->real_estate_papers_url ? 'متوفر (رابط)' : '—' }}</td></tr>
            <tr><td>المخططات والتجهيزات</td><td>{{ $spd->plans_equipment_docs_url ? 'متوفر (رابط)' : '—' }}</td></tr>
            <tr><td>شعار المشروع</td><td>{{ $spd->project_logo_url ? 'متوفر (رابط)' : '—' }}</td></tr>
            <tr><td>الأسعار والوحدات</td><td>{{ $spd->prices_units_url ? 'متوفر (رابط)' : '—' }}</td></tr>
            <tr><td>رخصة التسويق</td><td>{{ $spd->marketing_license_url ? 'متوفر (رابط)' : '—' }}</td></tr>
            <tr><td>رقم المعلن / القسم</td><td>{{ $spd->advertiser_section_url ?? '—' }}</td></tr>
        </table>
    @endif

    <p class="section-title">الوحدات</p>
    @if($contract->contractUnits && $contract->contractUnits->isNotEmpty())
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
            @foreach($contract->contractUnits as $u)
                <tr>
                    <td class="ltr">{{ $u->unit_number ?? $u->id }}</td>
                    <td>{{ $u->unit_type ?? '—' }}</td>
                    <td>{{ $u->status ?? '—' }}</td>
                    <td class="ltr">{{ number_format((float)($u->price ?? 0), 2, '.', ',') }}</td>
                    <td>{{ $u->area ?? $u->total_area_m2 ?? '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @elseif(is_array($contract->units) && count($contract->units) > 0)
        <table class="data-table">
            <thead>
            <tr>
                <th>نوع الوحدة</th>
                <th>العدد</th>
                <th>سعر الوحدة</th>
            </tr>
            </thead>
            <tbody>
            @foreach($contract->units as $row)
                <tr>
                    <td>{{ $row['type'] ?? '—' }}</td>
                    <td class="ltr">{{ $row['count'] ?? '—' }}</td>
                    <td class="ltr">{{ isset($row['price']) ? number_format((float)$row['price'], 2, '.', ',') : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p class="empty-msg">لا توجد وحدات مسجلة.</p>
    @endif

    <p class="auto-msg">وثيقة تلقائية من نظام راكز — لا تعتبر عقدًا نهائيًا دون التوقيع والاعتماد الرسمي.</p>
@endsection

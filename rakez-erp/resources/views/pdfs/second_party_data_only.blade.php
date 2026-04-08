@extends('layouts.pdf')

@php
    /** @var \App\Models\SecondPartyData $spd */
    /** @var array<string,string> $display */
@endphp

@section('title', 'بيانات الطرف الثاني — سجل ' . ($display['record_id'] ?? ''))

@section('content')
    <div class="doc-title-wrap">
        <p class="doc-title">بيانات الطرف الثاني (SecondPartyData)</p>
        <p class="doc-title-en" style="direction: ltr;">Second party attachments & references</p>
        <p class="doc-subtitle">
            معرف السجل: <span class="ltr">{{ $display['record_id'] }}</span>
            — مرجع العقد (contract_id): <span class="ltr">{{ $display['contract_id'] }}</span>
            — تم إنشاء المستند: {{ $generated_at }}
        </p>
    </div>

    <p class="section-title first">الروابط والمرفقات</p>
    <table class="info-table">
        <tr>
            <td>أوراق العقار</td>
            <td class="ltr" style="word-break: break-all;">{{ $display['real_estate_papers_url'] }}</td>
        </tr>
        <tr>
            <td>مستندات المخططات والتجهيزات</td>
            <td class="ltr" style="word-break: break-all;">{{ $display['plans_equipment_docs_url'] }}</td>
        </tr>
        <tr>
            <td>شعار المشروع</td>
            <td class="ltr" style="word-break: break-all;">{{ $display['project_logo_url'] }}</td>
        </tr>
        <tr>
            <td>الأسعار والوحدات</td>
            <td class="ltr" style="word-break: break-all;">{{ $display['prices_units_url'] }}</td>
        </tr>
        <tr>
            <td>رخصة التسويق</td>
            <td class="ltr" style="word-break: break-all;">{{ $display['marketing_license_url'] }}</td>
        </tr>
        <tr>
            <td>رقم قسم المعلن / المعلن</td>
            <td class="ltr">{{ $display['advertiser_section_url'] }}</td>
        </tr>
    </table>

    <p class="section-title">المعالجة</p>
    <table class="info-table">

        <tr>
            <td>اسم المعالج</td>
            <td>{{ $display['processed_by_name'] }}</td>
        </tr>
        <tr>
            <td>تاريخ المعالجة</td>
            <td>{{ $display['processed_at'] }}</td>
        </tr>
    </table>

    <p class="auto-msg">وثيقة من جدول بيانات الطرف الثاني فقط — نظام راكز</p>
@endsection

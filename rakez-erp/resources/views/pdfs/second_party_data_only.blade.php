@extends('layouts.pdf_sand_qabd')

@php
    /** @var \App\Models\SecondPartyData $spd */
    /** @var array<string,string> $display */
@endphp

@section('title', 'بيانات الطرف الثاني — سجل ' . ($display['record_id'] ?? ''))

@section('content')
    <p class="sand-title">بيانات الطرف الثاني والمرفقات</p>
    <p class="sand-title-en" dir="ltr">Second party data &amp; attachment references</p>
    <p class="sand-subtitle">
        معرف السجل: <span class="ltr">{{ $display['record_id'] }}</span>
        — مرجع العقد (contract_id): <span class="ltr">{{ $display['contract_id'] }}</span>
        — تم إنشاء المستند: {{ $generated_at }}
    </p>

    <table class="sand-meta-line" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 55%;">
                <strong>سند بيانات /</strong>
                مرفقات وروابط الطرف الثاني
            </td>
            <td style="width: 45%; text-align: left; direction: ltr;">
                <strong>Record</strong> <span class="ltr">{{ $display['record_id'] }}</span>
            </td>
        </tr>
    </table>

    <p class="sand-section first">الروابط والمرفقات</p>
    <table class="sand-kv" cellpadding="0" cellspacing="0">
        <tr>
            <td>أوراق العقار</td>
            <td class="sand-val-ltr">{{ $display['real_estate_papers_url'] }}</td>
        </tr>
        <tr>
            <td>مستندات المخططات والتجهيزات</td>
            <td class="sand-val-ltr">{{ $display['plans_equipment_docs_url'] }}</td>
        </tr>
        <tr>
            <td>شعار المشروع</td>
            <td class="sand-val-ltr">{{ $display['project_logo_url'] }}</td>
        </tr>
        <tr>
            <td>الأسعار والوحدات</td>
            <td class="sand-val-ltr">{{ $display['prices_units_url'] }}</td>
        </tr>
        <tr>
            <td>رخصة التسويق</td>
            <td class="sand-val-ltr">{{ $display['marketing_license_url'] }}</td>
        </tr>
        <tr>
            <td>رقم قسم المعلن / المعلن</td>
            <td class="sand-val-ltr">{{ $display['advertiser_section_url'] }}</td>
        </tr>
    </table>

    <p class="sand-section">المعالجة</p>
    <table class="sand-kv" cellpadding="0" cellspacing="0">
        <tr>
            <td>اسم المعالج</td>
            <td>{{ $display['processed_by_name'] }}</td>
        </tr>
        <tr>
            <td>تاريخ المعالجة</td>
            <td>{{ $display['processed_at'] }}</td>
        </tr>
    </table>

    <p class="sand-auto-msg">وثيقة من جدول بيانات الطرف الثاني (SecondPartyData) — نظام راكز العقارية</p>
@endsection

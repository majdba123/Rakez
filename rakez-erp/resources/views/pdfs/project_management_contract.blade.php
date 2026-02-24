@extends('layouts.pdf')

@section('title', 'عقد - ' . ($contract->project_name ?? 'Contract'))

@section('content')
    <p class="doc-title">تفاصيل العقد</p>
    <p class="doc-title-en">Contract Summary</p>
    <p class="doc-subtitle">{{ $contract->project_name }}</p>

    <p class="section-title">&#9670; معلومات المشروع</p>
    <table class="info-table">
        <tr><td>اسم المشروع</td><td>{{ $contract->project_name }}</td></tr>
        <tr><td>المطور</td><td>{{ $contract->developer_name }}</td></tr>
        <tr><td>المدينة</td><td>{{ $contract->city }}</td></tr>
        <tr><td>الحي</td><td>{{ $contract->district }}</td></tr>
        <tr><td>الحالة</td><td>{{ $contract->status }}</td></tr>
        @if($contract->notes)
        <tr><td>ملاحظات</td><td>{{ $contract->notes }}</td></tr>
        @endif
    </table>

    <p class="section-title">&#9670; التاريخ</p>
    <table class="info-table">
        <tr><td>تاريخ الإنشاء</td><td>{{ $contract->created_at?->format('Y-m-d') }}</td></tr>
    </table>

    <p class="auto-msg">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز | {{ now()->format('Y-m-d H:i:s') }}</p>
@endsection

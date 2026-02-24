@extends('layouts.pdf')

@section('title', 'عقد مشروع حصري - Exclusive Project Contract')

@section('content')
    <p class="doc-title">عقد مشروع حصري</p>
    <p class="doc-title-en">Exclusive Project Contract</p>
    <p class="doc-subtitle">رقم الطلب: {{ $request->id }} | التاريخ: {{ now()->format('Y-m-d') }}</p>

    <p class="section-title">&#9670; معلومات المشروع / Project Information</p>
    <table class="info-table">
        <tr><td>اسم المشروع / Project Name</td><td>{{ $request->project_name }}</td></tr>
        <tr><td>اسم المطور / Developer Name</td><td>{{ $request->developer_name }}</td></tr>
        <tr><td>رقم التواصل / Contact Number</td><td>{{ $request->developer_contact }}</td></tr>
        <tr><td>المدينة / City</td><td>{{ $request->location_city }}</td></tr>
        @if($request->location_district)
        <tr><td>الحي / District</td><td>{{ $request->location_district }}</td></tr>
        @endif
        @if($request->estimated_units)
        <tr><td>عدد الوحدات المتوقع / Estimated Units</td><td>{{ $request->estimated_units }}</td></tr>
        @endif
    </table>

    @if($request->project_description)
    <p class="section-title">&#9670; وصف المشروع / Project Description</p>
    <p style="font-size: 10px; padding: 5px; background: #f9f9f9; border: 1px solid #eee;">{{ $request->project_description }}</p>
    @endif

    @if($contract && $contract->units)
    <p class="section-title">&#9670; الوحدات / Units</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>النوع / Type</th>
                <th>العدد / Count</th>
                <th>السعر / Price</th>
                <th>الإجمالي / Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($contract->units as $unit)
            <tr>
                <td>{{ $unit['type'] }}</td>
                <td>{{ $unit['count'] }}</td>
                <td>{{ number_format($unit['price'], 2) }} ريال</td>
                <td>{{ number_format($unit['count'] * $unit['price'], 2) }} ريال</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <p class="section-title">&#9670; معلومات الموافقة / Approval Information</p>
    <table class="info-table">
        <tr><td>تم الطلب بواسطة / Requested By</td><td>{{ $request->requestedBy->name }}</td></tr>
        @if($request->approvedBy)
        <tr><td>تمت الموافقة بواسطة / Approved By</td><td>{{ $request->approvedBy->name }}</td></tr>
        <tr><td>تاريخ الموافقة / Approval Date</td><td>{{ $request->approved_at->format('Y-m-d') }}</td></tr>
        @endif
        @if($request->contract_completed_at)
        <tr><td>تاريخ إكمال العقد / Completion Date</td><td>{{ $request->contract_completed_at->format('Y-m-d') }}</td></tr>
        @endif
    </table>

    <p class="auto-msg">هذا المستند تم إنشاؤه تلقائياً من نظام إدارة المشاريع | تاريخ الطباعة: {{ now()->format('Y-m-d H:i:s') }}</p>
@endsection

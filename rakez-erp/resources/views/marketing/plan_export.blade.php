@extends('layouts.pdf')

@section('title', 'خطة تسويق - ' . $plan->id)

@section('content')
    <p class="doc-title">خطة تسويق</p>
    <p class="doc-title-en">Marketing Plan #{{ $plan->id }}</p>

    <p class="section-title">&#9670; معلومات الخطة</p>
    <table class="info-table">
        <tr><td>المشروع / Project</td><td>{{ $plan->marketingProject->contract->project_name ?? '-' }}</td></tr>
        <tr><td>الموظف / User</td><td>{{ $plan->user->name ?? '-' }}</td></tr>
        <tr><td>قيمة العمولة / Commission</td><td>{{ number_format($plan->commission_value, 2) }}</td></tr>
        <tr><td>قيمة التسويق / Marketing</td><td>{{ number_format($plan->marketing_value, 2) }}</td></tr>
    </table>

    @if(!empty($plan->platform_distribution))
    <p class="section-title">&#9670; توزيع المنصات / Platform Distribution</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>المنصة / Platform</th>
                <th>النسبة / Percentage</th>
            </tr>
        </thead>
        <tbody>
            @foreach($plan->platform_distribution as $platform => $percentage)
            <tr>
                <td>{{ $platform }}</td>
                <td>{{ $percentage }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(!empty($plan->campaign_distribution))
    <p class="section-title">&#9670; توزيع الحملات / Campaign Distribution</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>الحملة / Campaign</th>
                <th>النسبة / Percentage</th>
            </tr>
        </thead>
        <tbody>
            @foreach($plan->campaign_distribution as $campaign => $percentage)
            <tr>
                <td>{{ $campaign }}</td>
                <td>{{ $percentage }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <p class="auto-msg">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز العقاري</p>
@endsection

@extends('layouts.pdf')

@section('title', 'عقد موظف - ' . ($employee->name ?? ''))

@section('extra-styles')
    body { line-height: 1.6; }
    .contract-terms { margin: 5px 0; padding: 8px; background: #fafafa; border: 1px solid #eee; }
    .contract-terms table { width: 100%; border-collapse: collapse; }
    .contract-terms table td { padding: 5px 8px; border: 1px solid #eee; font-size: 10px; }
    .contract-terms table td:first-child { width: 40%; font-weight: bold; color: #555; background: #f5f5f5; }
    .sig-table { width: 100%; border-collapse: collapse; margin-top: 40px; }
    .sig-table td { padding: 10px; text-align: center; font-size: 10px; width: 50%; }
    .sig-line { border-top: 1px solid #333; margin-top: 45px; padding-top: 5px; }
@endsection

@section('content')
    <p class="doc-title">عقد عمل موظف</p>
    <p class="doc-title-en">Employee Contract</p>
    <p class="doc-subtitle">رقم العقد: {{ $contract->id }} | تاريخ الإصدار: {{ $generated_at }}</p>

    <p class="section-title">&#9670; بيانات الموظف / Employee Information</p>
    <table class="info-table">
        <tr><td>اسم الموظف / Name</td><td>{{ $employee->name ?? '-' }}</td></tr>
        <tr><td>البريد الإلكتروني / Email</td><td>{{ $employee->email ?? '-' }}</td></tr>
        @if($employee->phone ?? null)
        <tr><td>رقم الجوال / Phone</td><td>{{ $employee->phone }}</td></tr>
        @endif
        @if($employee->type ?? null)
        <tr><td>القسم / Department</td><td>{{ $employee->type }}</td></tr>
        @endif
    </table>

    <p class="section-title">&#9670; بيانات العقد / Contract Details</p>
    <table class="info-table">
        <tr><td>تاريخ بداية العقد / Start Date</td><td>{{ $contract->start_date?->format('Y-m-d') ?? '-' }}</td></tr>
        <tr><td>تاريخ نهاية العقد / End Date</td><td>{{ $contract->end_date?->format('Y-m-d') ?? 'غير محدد / Open-ended' }}</td></tr>
        <tr>
            <td>حالة العقد / Status</td>
            <td>
                <span class="status-badge status-{{ $contract->status }}">
                    @switch($contract->status)
                        @case('draft') مسودة / Draft @break
                        @case('active') نشط / Active @break
                        @case('expired') منتهي / Expired @break
                        @case('terminated') ملغي / Terminated @break
                        @default {{ $contract->status }}
                    @endswitch
                </span>
            </td>
        </tr>
    </table>

    @if(!empty($contract_data))
    <p class="section-title">&#9670; تفاصيل العقد / Contract Terms</p>
    <div class="contract-terms">
        <table>
            @foreach($contract_data as $key => $value)
                @if(!is_array($value) && !is_null($value))
                <tr>
                    <td>{{ str_replace('_', ' ', $key) }}</td>
                    <td>{{ $value }}</td>
                </tr>
                @endif
            @endforeach
        </table>
    </div>
    @endif

    <table class="sig-table">
        <tr>
            <td>توقيع الموظف / Employee Signature</td>
            <td>توقيع المسؤول / Manager Signature</td>
        </tr>
        <tr>
            <td><div class="sig-line">&nbsp;</div></td>
            <td><div class="sig-line">&nbsp;</div></td>
        </tr>
    </table>

    <p class="auto-msg">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز | {{ $generated_at }}</p>
@endsection

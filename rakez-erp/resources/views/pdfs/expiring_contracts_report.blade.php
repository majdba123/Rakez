@extends('layouts.pdf')

@section('title', 'تقرير العقود القريبة من الانتهاء')

@section('content')
    <p class="doc-title">تقرير العقود القريبة من الانتهاء وقرب انتهاء فترة التجربة</p>
    <p class="doc-subtitle">الفترة: خلال {{ $days }} يوماً | تاريخ التقرير: {{ $report['generated_at'] ?? '' }}</p>

    <p class="section-title">&#9670; عقود قريبة من الانتهاء</p>
    @if(!empty($report['expiring_contracts']) && count($report['expiring_contracts']) > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>رقم العقد</th>
                <th>اسم الموظف</th>
                <th>البريد الإلكتروني</th>
                <th>تاريخ الانتهاء</th>
                <th>الأيام المتبقية</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['expiring_contracts'] as $row)
            <tr>
                <td>{{ $row['contract_id'] ?? '-' }}</td>
                <td>{{ $row['employee_name'] ?? '-' }}</td>
                <td>{{ $row['employee_email'] ?? '-' }}</td>
                <td>{{ $row['end_date'] ?? '-' }}</td>
                <td>{{ $row['days_remaining'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="empty-msg">لا توجد عقود تنتهي خلال الفترة المحددة.</p>
    @endif

    <p class="section-title">&#9670; موظفون قرب انتهاء فترة التجربة</p>
    @if(!empty($report['probation_ending']) && count($report['probation_ending']) > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>الاسم</th>
                <th>البريد الإلكتروني</th>
                <th>تاريخ انتهاء التجربة</th>
                <th>الأيام المتبقية</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['probation_ending'] as $row)
            <tr>
                <td>{{ $row['name'] ?? '-' }}</td>
                <td>{{ $row['email'] ?? '-' }}</td>
                <td>{{ $row['probation_end_date'] ?? '-' }}</td>
                <td>{{ $row['days_remaining'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="empty-msg">لا يوجد موظفون قرب انتهاء فترة التجربة خلال الفترة المحددة.</p>
    @endif

    @if(!empty($report['summary']))
    <p class="section-title">&#9670; الملخص</p>
    <div class="summary-box">
        <p><strong>عدد العقود القريبة من الانتهاء:</strong> {{ $report['summary']['contracts_expiring_count'] ?? 0 }}</p>
        <p><strong>عدد الموظفين قرب انتهاء التجربة:</strong> {{ $report['summary']['probation_ending_count'] ?? 0 }}</p>
        <p><strong>الفترة المعتمدة (أيام):</strong> {{ $report['summary']['days_checked'] ?? $days }}</p>
    </div>
    @endif

    <p class="auto-msg">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز | {{ $report['generated_at'] ?? '' }}</p>
@endsection

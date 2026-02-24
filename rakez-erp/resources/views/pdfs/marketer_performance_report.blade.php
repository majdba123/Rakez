@extends('layouts.pdf')

@section('title', 'تقرير أداء المسوقين')

@section('content')
    <p class="doc-title">تقرير أداء المسوقين</p>
    <p class="doc-subtitle">الفترة: {{ $report['period']['year'] ?? '' }} / {{ $report['period']['month'] ?? '' }} | تاريخ التقرير: {{ $generated_at }}</p>

    <p class="section-title">&#9670; أداء المسوقين</p>
    @if(!empty($report['marketers']) && count($report['marketers']) > 0)
    <table class="data-table">
        <thead>
            <tr>
                <th>الاسم</th>
                <th>البريد الإلكتروني</th>
                <th>الفريق</th>
                <th>نسبة تحقيق الهدف %</th>
                <th>عدد الودائع</th>
                <th>عدد الإنذارات</th>
                <th>فترة التجربة</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report['marketers'] as $row)
            <tr>
                <td>{{ $row['name'] ?? '-' }}</td>
                <td>{{ $row['email'] ?? '-' }}</td>
                <td>{{ $row['team_name'] ?? '-' }}</td>
                <td>{{ is_numeric($row['target_achievement_rate'] ?? null) ? number_format((float)$row['target_achievement_rate'], 1) : ($row['target_achievement_rate'] ?? '-') }}</td>
                <td>{{ $row['deposits_count'] ?? 0 }}</td>
                <td>{{ $row['warnings_count'] ?? 0 }}</td>
                <td>{{ !empty($row['is_in_probation']) ? 'نعم' : 'لا' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p class="empty-msg">لا يوجد مسوقون في الفترة المحددة.</p>
    @endif

    @if(!empty($report['totals']))
    <p class="section-title">&#9670; الملخص</p>
    <div class="summary-box">
        <p><strong>عدد المسوقين:</strong> {{ $report['totals']['marketers_count'] ?? 0 }}</p>
        <p><strong>متوسط تحقيق الهدف %:</strong> {{ isset($report['totals']['avg_achievement']) ? number_format((float)$report['totals']['avg_achievement'], 1) : '-' }}</p>
        <p><strong>إجمالي الودائع:</strong> {{ $report['totals']['total_deposits'] ?? 0 }}</p>
        <p><strong>إجمالي الإنذارات:</strong> {{ $report['totals']['total_warnings'] ?? 0 }}</p>
    </div>
    @endif

    <p class="auto-msg">هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز | {{ $generated_at }}</p>
@endsection

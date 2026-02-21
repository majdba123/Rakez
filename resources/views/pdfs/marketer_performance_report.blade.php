<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير أداء المسوقين</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            direction: rtl;
            text-align: right;
            margin: 20px;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #333;
            padding-bottom: 12px;
        }
        .header h1 {
            margin: 0 0 8px 0;
            color: #333;
            font-size: 18px;
        }
        .header .meta {
            color: #555;
            font-size: 11px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: right;
        }
        th {
            background-color: #4CAF50;
            color: white;
            font-size: 11px;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .summary {
            margin-top: 15px;
            padding: 12px;
            background-color: #f5f5f5;
            border: 1px solid #ddd;
        }
        .summary p {
            margin: 4px 0;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
            color: #777;
        }
        .empty-msg {
            color: #777;
            font-style: italic;
            padding: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>تقرير أداء المسوقين</h1>
        <p class="meta">الفترة: {{ $report['period']['year'] ?? '' }} / {{ $report['period']['month'] ?? '' }}</p>
        <p class="meta">تاريخ التقرير: {{ $generated_at }}</p>
    </div>

    <div class="section">
        <h2>أداء المسوقين</h2>
        @if(!empty($report['marketers']) && count($report['marketers']) > 0)
        <table>
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
    </div>

    @if(!empty($report['totals']))
    <div class="summary">
        <p><strong>عدد المسوقين:</strong> {{ $report['totals']['marketers_count'] ?? 0 }}</p>
        <p><strong>متوسط تحقيق الهدف %:</strong> {{ isset($report['totals']['avg_achievement']) ? number_format((float)$report['totals']['avg_achievement'], 1) : '-' }}</p>
        <p><strong>إجمالي الودائع:</strong> {{ $report['totals']['total_deposits'] ?? 0 }}</p>
        <p><strong>إجمالي الإنذارات:</strong> {{ $report['totals']['total_warnings'] ?? 0 }}</p>
    </div>
    @endif

    <div class="footer">
        <p>هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز</p>
        <p>{{ $generated_at }}</p>
    </div>
</body>
</html>

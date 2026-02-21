<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير العقود القريبة من الانتهاء</title>
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
        .section {
            margin-bottom: 20px;
        }
        .section h2 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 6px;
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
        <h1>تقرير العقود القريبة من الانتهاء وقرب انتهاء فترة التجربة</h1>
        <p class="meta">الفترة: خلال {{ $days }} يوماً</p>
        <p class="meta">تاريخ التقرير: {{ $report['generated_at'] ?? '' }}</p>
    </div>

    <div class="section">
        <h2>عقود قريبة من الانتهاء</h2>
        @if(!empty($report['expiring_contracts']) && count($report['expiring_contracts']) > 0)
        <table>
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
    </div>

    <div class="section">
        <h2>موظفون قرب انتهاء فترة التجربة</h2>
        @if(!empty($report['probation_ending']) && count($report['probation_ending']) > 0)
        <table>
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
    </div>

    @if(!empty($report['summary']))
    <div class="summary">
        <p><strong>عدد العقود القريبة من الانتهاء:</strong> {{ $report['summary']['contracts_expiring_count'] ?? 0 }}</p>
        <p><strong>عدد الموظفين قرب انتهاء التجربة:</strong> {{ $report['summary']['probation_ending_count'] ?? 0 }}</p>
        <p><strong>الفترة المعتمدة (أيام):</strong> {{ $report['summary']['days_checked'] ?? $days }}</p>
    </div>
    @endif

    <div class="footer">
        <p>هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز</p>
        <p>{{ $report['generated_at'] ?? '' }}</p>
    </div>
</body>
</html>

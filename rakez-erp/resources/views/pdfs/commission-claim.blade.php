<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مطالبة عمولة - {{ $commission->id }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            direction: rtl;
            text-align: right;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .info-section {
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px;
            background-color: #f5f5f5;
        }
        .label {
            font-weight: bold;
            color: #555;
        }
        .value {
            color: #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: right;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .totals {
            margin-top: 20px;
            border-top: 2px solid #333;
            padding-top: 15px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .total-row.final {
            font-size: 18px;
            font-weight: bold;
            color: #4CAF50;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 5px;
            font-weight: bold;
        }
        .status-pending { background-color: #FFC107; color: #000; }
        .status-approved { background-color: #4CAF50; color: #fff; }
        .status-paid { background-color: #2196F3; color: #fff; }
    </style>
</head>
<body>
    <div class="header">
        <h1>مطالبة عمولة</h1>
        <p>رقم العمولة: {{ $commission->id }}</p>
        <p>تاريخ الإصدار: {{ $generated_at }}</p>
    </div>

    <div class="info-section">
        <h3>معلومات العمولة</h3>
        <div class="info-row">
            <span class="label">سعر البيع النهائي:</span>
            <span class="value">{{ number_format($commission->final_selling_price, 2) }} ريال</span>
        </div>
        <div class="info-row">
            <span class="label">نسبة العمولة:</span>
            <span class="value">{{ $commission->commission_percentage }}%</span>
        </div>
        <div class="info-row">
            <span class="label">الحالة:</span>
            <span class="status-badge status-{{ $commission->status }}">
                @if($commission->status === 'pending') معلق
                @elseif($commission->status === 'approved') معتمد
                @elseif($commission->status === 'paid') مدفوع
                @endif
            </span>
        </div>
    </div>

    <div class="info-section">
        <h3>توزيع العمولة</h3>
        <table>
            <thead>
                <tr>
                    <th>المستلم</th>
                    <th>النوع</th>
                    <th>النسبة</th>
                    <th>المبلغ</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($distributions as $dist)
                <tr>
                    <td>
                        @if($dist->recipient)
                            {{ $dist->recipient->name }}
                        @else
                            {{ $dist->external_marketer_name ?? 'غير محدد' }}
                        @endif
                    </td>
                    <td>{{ $dist->type }}</td>
                    <td>{{ $dist->percentage }}%</td>
                    <td>{{ number_format($dist->amount, 2) }} ريال</td>
                    <td>
                        @if($dist->status === 'pending') معلق
                        @elseif($dist->status === 'approved') معتمد
                        @elseif($dist->status === 'rejected') مرفوض
                        @elseif($dist->status === 'paid') مدفوع
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals">
        <h3>الإجماليات</h3>
        <div class="total-row">
            <span class="label">المبلغ الإجمالي:</span>
            <span class="value">{{ number_format($commission->total_amount, 2) }} ريال</span>
        </div>
        <div class="total-row">
            <span class="label">ضريبة القيمة المضافة (15%):</span>
            <span class="value">{{ number_format($commission->vat, 2) }} ريال</span>
        </div>
        <div class="total-row">
            <span class="label">مصاريف التسويق:</span>
            <span class="value">{{ number_format($commission->marketing_expenses, 2) }} ريال</span>
        </div>
        <div class="total-row">
            <span class="label">رسوم البنك:</span>
            <span class="value">{{ number_format($commission->bank_fees, 2) }} ريال</span>
        </div>
        <div class="total-row final">
            <span class="label">صافي العمولة:</span>
            <span class="value">{{ number_format($commission->net_amount, 2) }} ريال</span>
        </div>
    </div>

    <div class="footer">
        <p>هذا المستند تم إنشاؤه آلياً بواسطة نظام راكز العقاري</p>
        <p>{{ $generated_at }}</p>
    </div>
</body>
</html>

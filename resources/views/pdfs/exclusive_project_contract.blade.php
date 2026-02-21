<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عقد مشروع حصري - Exclusive Project Contract</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            direction: rtl;
            text-align: right;
            padding: 20px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
        }
        .section {
            margin-bottom: 25px;
        }
        .section-title {
            background-color: #f8f9fa;
            padding: 10px;
            border-right: 4px solid #3498db;
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .info-label {
            font-weight: bold;
            color: #555;
        }
        .info-value {
            color: #333;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #333;
            text-align: center;
            font-size: 12px;
            color: #777;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        table th {
            background-color: #3498db;
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>عقد مشروع حصري</h1>
        <h2>Exclusive Project Contract</h2>
        <p>رقم الطلب: {{ $request->id }}</p>
        <p>التاريخ: {{ now()->format('Y-m-d') }}</p>
    </div>

    <div class="section">
        <div class="section-title">معلومات المشروع - Project Information</div>
        <div class="info-row">
            <span class="info-label">اسم المشروع - Project Name:</span>
            <span class="info-value">{{ $request->project_name }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">اسم المطور - Developer Name:</span>
            <span class="info-value">{{ $request->developer_name }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">رقم التواصل - Contact Number:</span>
            <span class="info-value">{{ $request->developer_contact }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">المدينة - City:</span>
            <span class="info-value">{{ $request->location_city }}</span>
        </div>
        @if($request->location_district)
        <div class="info-row">
            <span class="info-label">الحي - District:</span>
            <span class="info-value">{{ $request->location_district }}</span>
        </div>
        @endif
        @if($request->estimated_units)
        <div class="info-row">
            <span class="info-label">عدد الوحدات المتوقع - Estimated Units:</span>
            <span class="info-value">{{ $request->estimated_units }}</span>
        </div>
        @endif
    </div>

    @if($request->project_description)
    <div class="section">
        <div class="section-title">وصف المشروع - Project Description</div>
        <p>{{ $request->project_description }}</p>
    </div>
    @endif

    @if($contract && $contract->units)
    <div class="section">
        <div class="section-title">الوحدات - Units</div>
        <table>
            <thead>
                <tr>
                    <th>النوع - Type</th>
                    <th>العدد - Count</th>
                    <th>السعر - Price</th>
                    <th>الإجمالي - Total</th>
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
    </div>
    @endif

    <div class="section">
        <div class="section-title">معلومات الموافقة - Approval Information</div>
        <div class="info-row">
            <span class="info-label">تم الطلب بواسطة - Requested By:</span>
            <span class="info-value">{{ $request->requestedBy->name }}</span>
        </div>
        @if($request->approvedBy)
        <div class="info-row">
            <span class="info-label">تمت الموافقة بواسطة - Approved By:</span>
            <span class="info-value">{{ $request->approvedBy->name }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">تاريخ الموافقة - Approval Date:</span>
            <span class="info-value">{{ $request->approved_at->format('Y-m-d') }}</span>
        </div>
        @endif
        @if($request->contract_completed_at)
        <div class="info-row">
            <span class="info-label">تاريخ إكمال العقد - Contract Completion Date:</span>
            <span class="info-value">{{ $request->contract_completed_at->format('Y-m-d') }}</span>
        </div>
        @endif
    </div>

    <div class="footer">
        <p>هذا المستند تم إنشاؤه تلقائياً من نظام إدارة المشاريع</p>
        <p>This document was automatically generated from the Project Management System</p>
        <p>تاريخ الطباعة: {{ now()->format('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>

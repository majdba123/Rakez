<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>Reservation Voucher - {{ $reservation->id }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #333;
            padding-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            color: #333;
            font-size: 28px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .section {
            margin: 25px 0;
        }
        .section h2 {
            background-color: #f0f0f0;
            padding: 10px;
            border-right: 5px solid #333;
            margin-bottom: 15px;
            font-size: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        td, th {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: right;
        }
        th {
            background-color: #f8f8f8;
            font-weight: bold;
            width: 35%;
        }
        td {
            background-color: #fff;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            text-align: center;
            color: #666;
        }
        .signature-section {
            margin-top: 50px;
            display: table;
            width: 100%;
        }
        .signature-box {
            display: table-cell;
            width: 50%;
            text-align: center;
            padding: 20px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 60px;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>سند حجز وحدة سكنية</h1>
        <h1>Reservation Voucher</h1>
        <p><strong>رقم الحجز / Reservation No:</strong> {{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}</p>
        <p><strong>تاريخ الإصدار / Issue Date:</strong> {{ $reservation->created_at->format('Y-m-d') }}</p>
    </div>

    <!-- Project Data -->
    <div class="section">
        <h2>بيانات المشروع / Project Data</h2>
        <table>
            <tr>
                <th>اسم المشروع / Project Name</th>
                <td>{{ $project['name'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>المدينة / City</th>
                <td>{{ $project['city'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>الحي / District</th>
                <td>{{ $project['district'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>اسم المطور / Developer Name</th>
                <td>{{ $project['developer_name'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>رقم المطور / Developer Number</th>
                <td>{{ $project['developer_number'] ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <!-- Unit Data -->
    <div class="section">
        <h2>بيانات الوحدة / Unit Data</h2>
        <table>
            <tr>
                <th>رقم الوحدة / Unit Number</th>
                <td>{{ $unit['number'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>نوع الوحدة / Unit Type</th>
                <td>{{ $unit['type'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>المساحة (م²) / Area (m²)</th>
                <td>{{ $unit['area'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>الطابق / Floor</th>
                <td>{{ $unit['floor'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>سعر الوحدة (ريال) / Unit Price (SAR)</th>
                <td>{{ number_format($unit['price'] ?? 0, 2) }}</td>
            </tr>
        </table>
    </div>

    <!-- Client Data -->
    <div class="section">
        <h2>بيانات العميل / Client Data</h2>
        <table>
            <tr>
                <th>اسم العميل / Client Name</th>
                <td>{{ $reservation->client_name }}</td>
            </tr>
            <tr>
                <th>رقم الجوال / Mobile Number</th>
                <td>{{ $reservation->client_mobile }}</td>
            </tr>
            <tr>
                <th>الجنسية / Nationality</th>
                <td>{{ $reservation->client_nationality }}</td>
            </tr>
            <tr>
                <th>رقم الآيبان / IBAN</th>
                <td>{{ $reservation->client_iban }}</td>
            </tr>
        </table>
    </div>

    <!-- Reservation Data -->
    <div class="section">
        <h2>بيانات الحجز / Reservation Data</h2>
        <table>
            <tr>
                <th>تاريخ العقد / Contract Date</th>
                <td>{{ $reservation->contract_date->format('Y-m-d') }}</td>
            </tr>
            <tr>
                <th>نوع الحجز / Reservation Type</th>
                <td>
                    @if($reservation->reservation_type === 'confirmed_reservation')
                        حجز مؤكد / Confirmed Reservation
                    @else
                        حجز للتفاوض / Reservation for Negotiation
                    @endif
                </td>
            </tr>
            @if($reservation->negotiation_notes)
            <tr>
                <th>ملاحظات التفاوض / Negotiation Notes</th>
                <td>{{ $reservation->negotiation_notes }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Payment Data -->
    <div class="section">
        <h2>بيانات الدفع / Payment Data</h2>
        <table>
            <tr>
                <th>طريقة الدفع / Payment Method</th>
                <td>
                    @switch($reservation->payment_method)
                        @case('bank_transfer')
                            تحويل بنكي / Bank Transfer
                            @break
                        @case('cash')
                            نقداً / Cash
                            @break
                        @case('bank_financing')
                            تمويل بنكي / Bank Financing
                            @break
                    @endswitch
                </td>
            </tr>
            <tr>
                <th>مبلغ الدفعة المقدمة (ريال) / Down Payment Amount (SAR)</th>
                <td><strong>{{ number_format($reservation->down_payment_amount, 2) }}</strong></td>
            </tr>
            <tr>
                <th>حالة الدفعة المقدمة / Down Payment Status</th>
                <td>
                    @if($reservation->down_payment_status === 'refundable')
                        قابلة للاسترداد / Refundable
                    @else
                        غير قابلة للاسترداد / Non-refundable
                    @endif
                </td>
            </tr>
            <tr>
                <th>آلية الشراء / Purchase Mechanism</th>
                <td>
                    @switch($reservation->purchase_mechanism)
                        @case('cash')
                            نقدي / Cash
                            @break
                        @case('supported_bank')
                            بنك معتمد / Supported Bank
                            @break
                        @case('unsupported_bank')
                            بنك غير معتمد / Unsupported Bank
                            @break
                    @endswitch
                </td>
            </tr>
        </table>
    </div>

    <!-- Employee Data -->
    <div class="section">
        <h2>بيانات الموظف المسؤول / Responsible Employee Data</h2>
        <table>
            <tr>
                <th>اسم الموظف / Employee Name</th>
                <td>{{ $employee['name'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>الفريق / Team</th>
                <td>{{ $employee['team'] ?? 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <!-- Signature Section -->
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line">
                <strong>توقيع العميل / Client Signature</strong>
            </div>
        </div>
        <div class="signature-box">
            <div class="signature-line">
                <strong>توقيع المسؤول / Employee Signature</strong>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p><strong>تاريخ الحجز / Reservation Date:</strong> {{ $reservation->contract_date->format('Y-m-d') }}</p>
        <p>هذا المستند يعتبر إثبات رسمي للحجز ويخضع للشروط والأحكام</p>
        <p>This document is an official proof of reservation and is subject to terms and conditions</p>
    </div>
</body>
</html>

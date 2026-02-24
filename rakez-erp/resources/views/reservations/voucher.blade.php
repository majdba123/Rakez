@extends('layouts.pdf')

@section('title', 'سند حجز - ' . $reservation->id)

@section('extra-styles')
    .client-line { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    .client-line td { padding: 5px 0; font-size: 12px; }
    .terms-list { margin: 10px 0; padding: 0; list-style: none; }
    .terms-list li { margin-bottom: 6px; font-size: 10px; line-height: 1.6; }
    .terms-list .num { font-weight: bold; color: #1B2A4A; }
    .note-box { margin: 15px 0; padding: 8px; font-size: 10px; font-weight: bold; line-height: 1.6; }
    .sig-table { width: 100%; border-collapse: collapse; margin-top: 25px; }
    .sig-table td { padding: 5px 10px; text-align: center; font-size: 10px; width: 33%; }
    .sig-line { border-top: 1px solid #333; margin-top: 35px; padding-top: 5px; }
    .page-num { position: fixed; bottom: 10px; left: 40px; font-size: 14px; font-weight: bold; color: #999; }
@endsection

@section('content')
    <div class="page-num">01</div>

    {{-- Client Info Line --}}
    <table class="client-line">
        <tr>
            <td style="text-align: right;"><strong>&#9670; مستلم من / {{ $reservation->client_name }}</strong></td>
            <td style="text-align: left;"><strong>رقم الجوال: {{ $reservation->client_mobile }}</strong></td>
        </tr>
    </table>

    {{-- Payment Details Table --}}
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>رقم المرجع</th>
                <th>النوع</th>
                <th>المبلغ</th>
                <th>المبلغ المدفوع</th>
                <th>التاريخ</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>01</td>
                <td>{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}</td>
                <td>
                    @if($reservation->reservation_type === 'confirmed_reservation')
                        حجز مؤكد
                    @else
                        حجز للتفاوض
                    @endif
                </td>
                <td>{{ number_format($reservation->down_payment_amount, 0) }}</td>
                <td>{{ number_format($reservation->down_payment_amount, 0) }}</td>
                <td>{{ $reservation->contract_date->format('d/m/Y') }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Project & Unit Info --}}
    <p class="section-title">&#9670; بيانات المشروع والوحدة</p>
    <table class="info-table">
        <tr><td>اسم المشروع / Project Name</td><td>{{ $project['name'] ?? 'N/A' }}</td></tr>
        <tr><td>المدينة / City</td><td>{{ $project['city'] ?? 'N/A' }}</td></tr>
        <tr><td>الحي / District</td><td>{{ $project['district'] ?? 'N/A' }}</td></tr>
        <tr><td>اسم المطور / Developer</td><td>{{ $project['developer_name'] ?? 'N/A' }}</td></tr>
        <tr><td>رقم الوحدة / Unit No</td><td>{{ $unit['number'] ?? 'N/A' }}</td></tr>
        <tr><td>نوع الوحدة / Unit Type</td><td>{{ $unit['type'] ?? 'N/A' }}</td></tr>
        <tr><td>المساحة (م²) / Area</td><td>{{ $unit['area'] ?? 'N/A' }}</td></tr>
        <tr><td>الطابق / Floor</td><td>{{ $unit['floor'] ?? 'N/A' }}</td></tr>
        <tr><td>سعر الوحدة (ريال) / Price</td><td>{{ number_format($unit['price'] ?? 0, 2) }}</td></tr>
    </table>

    {{-- Payment Info --}}
    <p class="section-title">&#9670; بيانات الدفع</p>
    <table class="info-table">
        <tr>
            <td>طريقة الدفع / Payment Method</td>
            <td>
                @switch($reservation->payment_method)
                    @case('bank_transfer') تحويل بنكي / Bank Transfer @break
                    @case('cash') نقداً / Cash @break
                    @case('bank_financing') تمويل بنكي / Bank Financing @break
                @endswitch
            </td>
        </tr>
        <tr>
            <td>مبلغ الدفعة المقدمة (ريال)</td>
            <td><strong>{{ number_format($reservation->down_payment_amount, 2) }}</strong></td>
        </tr>
        <tr>
            <td>حالة الدفعة المقدمة</td>
            <td>
                @if($reservation->down_payment_status === 'refundable')
                    قابلة للاسترداد / Refundable
                @else
                    غير قابلة للاسترداد / Non-refundable
                @endif
            </td>
        </tr>
        <tr>
            <td>آلية الشراء / Purchase</td>
            <td>
                @switch($reservation->purchase_mechanism)
                    @case('cash') نقدي / Cash @break
                    @case('supported_bank') بنك معتمد / Supported Bank @break
                    @case('unsupported_bank') بنك غير معتمد / Unsupported Bank @break
                @endswitch
            </td>
        </tr>
    </table>

    {{-- Client Info --}}
    <p class="section-title">&#9670; بيانات العميل</p>
    <table class="info-table">
        <tr><td>اسم العميل / Client Name</td><td>{{ $reservation->client_name }}</td></tr>
        <tr><td>رقم الجوال / Mobile</td><td>{{ $reservation->client_mobile }}</td></tr>
        <tr><td>الجنسية / Nationality</td><td>{{ $reservation->client_nationality }}</td></tr>
        <tr><td>رقم الآيبان / IBAN</td><td>{{ $reservation->client_iban }}</td></tr>
    </table>

    {{-- Employee Info --}}
    <p class="section-title">&#9670; بيانات الموظف المسؤول</p>
    <table class="info-table">
        <tr><td>اسم الموظف / Employee</td><td>{{ $employee['name'] ?? 'N/A' }}</td></tr>
        <tr><td>الفريق / Team</td><td>{{ $employee['team'] ?? 'N/A' }}</td></tr>
    </table>

    @if($reservation->negotiation_notes)
    <p class="section-title">&#9670; ملاحظات التفاوض</p>
    <p style="font-size: 10px;">{{ $reservation->negotiation_notes }}</p>
    @endif

    {{-- Important Note --}}
    <div class="note-box">
        يعتبر اعتماد الحجز بتوقيع العميل والإقرار بالموافقة وقراءة الشروط وفي حال عدم الحضور خلال ٢٤ ساعة يكون الحجز لاغياً
    </div>

    {{-- Signature Area --}}
    <table class="sig-table">
        <tr>
            <td>اسم العميل /</td>
            <td>التاريخ :&nbsp;&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;&nbsp;/ {{ now()->format('Y') }}</td>
            <td>توقيع العميل /</td>
        </tr>
        <tr>
            <td><div class="sig-line">{{ $reservation->client_name }}</div></td>
            <td><div class="sig-line">{{ $reservation->contract_date->format('Y-m-d') }}</div></td>
            <td><div class="sig-line">&nbsp;</div></td>
        </tr>
    </table>

    <table class="sig-table" style="margin-top: 15px;">
        <tr>
            <td>اسم الموظف المسؤول /</td>
            <td>&nbsp;</td>
            <td>توقيع الموظف /</td>
        </tr>
        <tr>
            <td><div class="sig-line">{{ $employee['name'] ?? '' }}</div></td>
            <td>&nbsp;</td>
            <td><div class="sig-line">&nbsp;</div></td>
        </tr>
    </table>
@endsection

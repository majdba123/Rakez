@extends('layouts.pdf_sand_qabd')

@php
    $reservationStatus = match ($reservation?->status) {
        'confirmed' => 'مؤكد',
        'under_negotiation' => 'تحت التفاوض',
        'cancelled' => 'ملغي',
        default => '—',
    };
    $accountLabel = match ($reservation?->account) {
        'basic' => 'الحساب الأساسي',
        'from_developer' => 'من المطور',
        default => '—',
    };
@endphp

@section('title', 'ملف وحدة للمطور - ' . ($unit->unit_number ?? $unit->id))

@section('content')
    <p class="sand-title">ملف الوحدة للمطور</p>
    <p class="sand-title-en" dir="ltr">Unit developer package</p>
    <p class="sand-subtitle">
        رقم الوحدة: <span class="ltr">{{ $unit->unit_number ?? $unit->id }}</span>
        — المشروع: {{ $contract->project_name ?? '—' }}
        — تاريخ الإنشاء: {{ $generated_at }}
    </p>

    <table class="sand-meta-line" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 55%;">
                <strong>مرجع الوثيقة /</strong>
                ملف وحدة للمطور — بيانات وحدة وحجز ودفعات
            </td>
            <td style="width: 45%; text-align: left; direction: ltr;">
                <strong>Unit #</strong> <span class="ltr">{{ $unit->unit_number ?? $unit->id }}</span>
                @if($reservation)
                    — <strong>Reservation #</strong> <span class="ltr">{{ $reservation->id }}</span>
                @endif
            </td>
        </tr>
    </table>

    <p class="sand-section first">بيانات المشروع والوحدة</p>
    <table class="sand-kv" cellpadding="0" cellspacing="0">
        <tr><td>اسم المشروع</td><td>{{ $contract->project_name ?? '—' }}</td></tr>
        <tr><td>اسم المطور</td><td>{{ $contract->developer_name ?? '—' }}</td></tr>
        <tr><td>رقم المطور</td><td class="sand-val-ltr">{{ $contract->developer_number ?? '—' }}</td></tr>
        <tr><td>المدينة / الحي</td><td>{{ $contract->city?->name ?? '—' }} / {{ $contract->district?->name ?? '—' }}</td></tr>
        <tr><td>نوع المشروع</td><td>{{ $contract->is_off_plan ? 'على الخارطة' : 'جاهز' }}</td></tr>
        <tr><td>رقم الوحدة</td><td class="sand-val-ltr">{{ $unit->unit_number ?? '—' }}</td></tr>
        <tr><td>نوع الوحدة</td><td>{{ $unit->unit_type ?? '—' }}</td></tr>
        <tr><td>الدور</td><td class="sand-val-ltr">{{ $unit->floor ?? '—' }}</td></tr>
        <tr><td>المساحة</td><td class="sand-val-ltr">{{ $unit->area ?? '—' }}</td></tr>
        <tr><td>السعر</td><td class="sand-val-ltr">{{ $unit->price !== null ? number_format((float) $unit->price, 2) : '—' }}</td></tr>
    </table>

    <p class="sand-section">المخططات والتجهيزات</p>
    <table class="sand-kv" cellpadding="0" cellspacing="0">
        <tr>
            <td>مخططات الوحدة</td>
            <td class="sand-val-ltr">{{ $unit->diagrames ?: '—' }}</td>
        </tr>
        <tr>
            <td>مستندات المخططات والتجهيزات</td>
            <td class="sand-val-ltr">{{ $secondPartyData?->plans_equipment_docs_url ?: '—' }}</td>
        </tr>
    </table>

    <p class="sand-section">بيانات معلومات العقد</p>
    <table class="sand-kv" cellpadding="0" cellspacing="0">
        <tr><td>رقم العقد</td><td class="sand-val-ltr">{{ $contractInfo?->contract_number ?? '—' }}</td></tr>
        <tr><td>مدينة العقد</td><td>{{ $contractInfo?->contract_city ?? '—' }}</td></tr>
        <tr><td>تاريخ العقد</td><td>{{ $contractInfo?->gregorian_date?->format('Y-m-d') ?? '—' }}</td></tr>
        <tr><td>اسم الطرف الثاني</td><td>{{ $contractInfo?->second_party_name ?? '—' }}</td></tr>
        <tr><td>جوال الطرف الثاني</td><td class="sand-val-ltr">{{ $contractInfo?->second_party_phone ?? '—' }}</td></tr>
        <tr><td>السجل التجاري للطرف الثاني</td><td class="sand-val-ltr">{{ $contractInfo?->second_party_cr_number ?? '—' }}</td></tr>
    </table>

    <p class="sand-section">بيانات الحجز والدفعات</p>
    <table class="sand-kv" cellpadding="0" cellspacing="0">
        <tr><td>رقم الحجز</td><td class="sand-val-ltr">{{ $reservation?->id ?? '—' }}</td></tr>
        <tr><td>حالة الحجز</td><td>{{ $reservationStatus }}</td></tr>
        <tr><td>اسم العميل</td><td>{{ $reservation?->client_name ?? '—' }}</td></tr>
        <tr><td>جوال العميل</td><td class="sand-val-ltr">{{ $reservation?->client_mobile ?? '—' }}</td></tr>
        <tr><td>تاريخ التسليم</td><td>{{ $reservation?->delivery_date?->format('Y-m-d') ?? '—' }}</td></tr>
        <tr><td>الدفعة الأولى</td><td class="sand-val-ltr">{{ $reservation?->first_payment !== null ? number_format((float) $reservation->first_payment, 2) : '—' }}</td></tr>
        <tr><td>تاريخ الدفعة الأولى</td><td>{{ $reservation?->first_payment_date?->format('Y-m-d') ?? '—' }}</td></tr>
        <tr><td>الحساب</td><td>{{ $accountLabel }}</td></tr>
    </table>

    <table class="sand-grid" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th style="width: 15%;">#</th>
                <th style="width: 45%;">الدفعة</th>
                <th style="width: 40%;">التاريخ</th>
            </tr>
        </thead>
        <tbody>
            @foreach($payments as $index => $payment)
                <tr>
                    <td class="ltr">{{ $index + 1 }}</td>
                    <td class="ltr">{{ number_format((float) $payment['payment'], 2) }}</td>
                    <td class="ltr">{{ $payment['date'] ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="sand-auto-msg">ملف وحدة مرسل من قسم المبيعات — نظام راكز العقارية</p>
@endsection

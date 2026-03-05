@extends('layouts.pdf')

@section('title', 'تفاصيل الوحدة - ' . ($unit->unit_number ?? $unit->id))

@section('content')
    <p class="doc-title">تفاصيل الوحدة</p>
    <p class="doc-subtitle">Unit Details | {{ $generated_at ?? now()->format('Y-m-d H:i') }}</p>

    <p class="section-title">&#9670; المشروع / Project</p>
    <table class="info-table">
        <tr>
            <td>اسم المشروع</td>
            <td>{{ $contract->project_name ?? '—' }}</td>
        </tr>
        <tr>
            <td>المطور</td>
            <td>{{ $contract->developer_name ?? '—' }}</td>
        </tr>
    </table>

    <p class="section-title">&#9670; بيانات الوحدة / Unit Data</p>
    <table class="info-table">
        <tr>
            <td>رقم الوحدة</td>
            <td>{{ $unit->unit_number ?? $unit->id }}</td>
        </tr>
        <tr>
            <td>نوع الوحدة</td>
            <td>{{ $unit->unit_type ?? '—' }}</td>
        </tr>
        <tr>
            <td>السعر (ريال)</td>
            <td>{{ number_format((float)($unit->price ?? 0), 2, '.', ',') }}</td>
        </tr>
        <tr>
            <td>المساحة (م²)</td>
            <td>{{ $unit->area !== null && $unit->area !== '' ? $unit->area : ($unit->total_area_m2 ?? '—') }}</td>
        </tr>
        <tr>
            <td>الدور</td>
            <td>{{ $unit->floor ?? '—' }}</td>
        </tr>
        <tr>
            <td>الغرف</td>
            <td>{{ $unit->bedrooms !== null && $unit->bedrooms !== '' ? $unit->bedrooms : '—' }}</td>
        </tr>
        <tr>
            <td>الحمامات</td>
            <td>{{ $unit->bathrooms !== null && $unit->bathrooms !== '' ? $unit->bathrooms : '—' }}</td>
        </tr>
        <tr>
            <td>المساحة الخاصة (م²)</td>
            <td>{{ $unit->private_area_m2 !== null && $unit->private_area_m2 !== '' ? $unit->private_area_m2 : '—' }}</td>
        </tr>
        <tr>
            <td>إجمالي المساحة (م²)</td>
            <td>{{ $unit->total_area_m2 !== null && $unit->total_area_m2 !== '' ? $unit->total_area_m2 : '—' }}</td>
        </tr>
        <tr>
            <td>الواجهة / الاتجاه</td>
            <td>{{ $unit->facade ?? '—' }}</td>
        </tr>
        <tr>
            <td>الحالة</td>
            <td>{{ $unit->status ?? '—' }}</td>
        </tr>
        <tr>
            <td>وصف</td>
            <td>{{ $unit->description ?? '—' }}</td>
        </tr>
    </table>
@endsection

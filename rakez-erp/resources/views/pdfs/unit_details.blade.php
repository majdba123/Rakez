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
            <td>{{ $unit->price ? number_format((float) $unit->price, 2) : '—' }}</td>
        </tr>
        <tr>
            <td>المساحة (م²)</td>
            <td>{{ $unit->area ?? $unit->total_area_m2 ?? '—' }}</td>
        </tr>
        <tr>
            <td>الدور</td>
            <td>{{ $unit->floor ?? '—' }}</td>
        </tr>
        <tr>
            <td>الغرف</td>
            <td>{{ $unit->bedrooms ?? '—' }}</td>
        </tr>
        <tr>
            <td>الحمامات</td>
            <td>{{ $unit->bathrooms ?? '—' }}</td>
        </tr>
        @if(!empty($unit->private_area_m2))
        <tr>
            <td>المساحة الخاصة (م²)</td>
            <td>{{ $unit->private_area_m2 }}</td>
        </tr>
        @endif
        @if(!empty($unit->total_area_m2))
        <tr>
            <td>إجمالي المساحة (م²)</td>
            <td>{{ $unit->total_area_m2 }}</td>
        </tr>
        @endif
        @if(!empty($unit->facade))
        <tr>
            <td>الواجهة / الاتجاه</td>
            <td>{{ $unit->facade }}</td>
        </tr>
        @endif
        <tr>
            <td>الحالة</td>
            <td>{{ $unit->status ?? '—' }}</td>
        </tr>
        @if(!empty($unit->description))
        <tr>
            <td>وصف</td>
            <td>{{ $unit->description }}</td>
        </tr>
        @endif
    </table>
@endsection

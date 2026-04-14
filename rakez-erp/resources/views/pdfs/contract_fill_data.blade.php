@extends('layouts.pdf_sand_qabd')

@php
    /** @var string $contract_id */
    /** @var list<array{label: string, value: string}> $rows */
    /** @var string $generated_at */
@endphp

@section('title', 'بيانات ملء العقد — عقد ' . $contract_id)

@section('content')
    <p class="sand-title">بيانات ملء قالب العقد الحصري</p>
    <p class="sand-title-en" dir="ltr">Exclusive contract — fill template fields</p>
    <p class="sand-subtitle">
        مرجع العقد (contract_id): <span class="ltr">{{ $contract_id }}</span>
        — تم إنشاء المستند: {{ $generated_at }}
    </p>

    <table class="sand-meta-line" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 55%;">
                <strong>مرجع الوثيقة /</strong>
                حقول مستخرجة من واجهة ملء العقد
            </td>
            <td style="width: 45%; text-align: left; direction: ltr;">
                <strong>API</strong> <span class="ltr">/api/contracts/{id}/fill-data</span>
            </td>
        </tr>
    </table>

    <p class="sand-section first">الحقول</p>
    <table class="sand-kv" cellpadding="0" cellspacing="0">
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="sand-val-ltr">{{ $row['value'] }}</td>
            </tr>
        @endforeach
    </table>

    <p class="sand-auto-msg">نسخة من بيانات /api/contracts/{id}/fill-data — نظام راكز العقارية</p>
@endsection

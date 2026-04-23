<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@yield('title')</title>
    <style>
        @page {
            margin: 14mm 12mm 26mm 12mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'dejavusans', 'dejavu sans', sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 11pt;
            color: #111827;
            margin: 0;
            padding: 0;
            line-height: 1.65;
        }

        .sand-sheet-label {
            text-align: left;
            direction: ltr;
            font-size: 12.5pt;
            font-weight: bold;
            color: #374151;
            margin: 0 0 10px 0;
        }

        .sand-brand-row {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 12px 0;
            border-bottom: 2px solid #1B2A4A;
            padding-bottom: 6px;
        }
        .sand-brand-row td {
            vertical-align: middle;
            border: none;
            font-size: 9.5pt;
            color: #4b5563;
        }

        .sand-title {
            text-align: center;
            font-size: 17pt;
            font-weight: bold;
            color: #1B2A4A;
            letter-spacing: 0.02em;
            margin: 8px 0 10px 0;
            padding: 4px 0;
        }
        .sand-title-en {
            text-align: center;
            font-size: 10pt;
            color: #64748b;
            margin: 0 0 12px 0;
        }
        .sand-subtitle {
            text-align: center;
            font-size: 9.5pt;
            color: #475569;
            margin: 0 0 16px 0;
            padding: 10px 12px;
            background-color: #f8fafc;
            border: 1px solid #d1d5db;
            line-height: 1.55;
        }

        .sand-meta-line {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 16px 0;
            font-size: 11pt;
        }
        .sand-meta-line td {
            padding: 8px 4px;
            vertical-align: baseline;
            border: none;
        }

        /* عناوين أقسام — أسلوب سند قبض (نص + خط سفلي، ليس شريطاً كاملاً) */
        .sand-section {
            font-size: 11.5pt;
            font-weight: bold;
            color: #1B2A4A;
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #9ca3af;
        }
        .sand-section.first {
            margin-top: 6px;
        }
        .sand-section::before {
            content: '\2756\00a0';
            font-weight: bold;
        }

        /* جداول حقل/قيمة — حدود كاملة مثل جدول السند */
        .sand-kv {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 18px 0;
            border: 1.2px solid #111827;
        }
        .sand-kv td {
            padding: 9px 11px;
            border: 1px solid #6b7280;
            font-size: 10pt;
            vertical-align: top;
            line-height: 1.5;
        }
        .sand-kv td:first-child {
            width: 34%;
            background-color: #e5e7eb;
            font-weight: bold;
            color: #111827;
        }
        .sand-kv td:last-child {
            background-color: #ffffff;
            color: #1f2937;
        }
        .sand-kv td.sand-val-ltr {
            direction: ltr;
            text-align: left;
            word-break: break-word;
            font-size: 9.5pt;
        }

        /* جدول بيانات بعدة أعمدة */
        .sand-grid {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 18px 0;
            border: 1.2px solid #111827;
        }
        .sand-grid th {
            background-color: #e5e7eb;
            color: #111827;
            font-weight: bold;
            font-size: 10pt;
            padding: 8px 6px;
            border: 1px solid #374151;
            text-align: center;
        }
        .sand-grid td {
            padding: 9px 7px;
            border: 1px solid #6b7280;
            font-size: 10pt;
            vertical-align: middle;
            text-align: center;
            background-color: #ffffff;
        }
        .sand-grid tr:nth-child(even) td {
            background-color: #f9fafb;
        }
        .sand-grid .sand-ref {
            font-weight: bold;
        }
        .sand-grid .sand-type {
            line-height: 1.45;
            max-width: 110px;
            text-align: center;
        }

        .sand-desc {
            font-size: 10.5pt;
            line-height: 1.85;
            text-align: justify;
            margin: 0 0 8px 0;
            color: #1f2937;
        }
        .sand-desc-num {
            font-weight: bold;
            color: #1B2A4A;
        }

        .sand-terms-wrap {
            margin: 8px 0 16px 0;
            padding: 0 2px 0 0;
        }
        .sand-term {
            font-size: 10pt;
            line-height: 1.75;
            text-align: justify;
            margin-bottom: 8px;
            color: #374151;
        }
        .sand-term .tn {
            font-weight: bold;
            color: #1B2A4A;
        }

        .sand-notice {
            margin: 14px 0 20px 0;
            padding: 10px 12px;
            background-color: #f9fafb;
            border-right: 4px solid #1B2A4A;
            font-size: 10.5pt;
            font-weight: bold;
            line-height: 1.7;
            text-align: justify;
        }

        .sand-sig-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 22px;
        }
        .sand-sig-grid td {
            width: 33%;
            text-align: center;
            font-size: 10pt;
            padding: 6px 8px;
            vertical-align: bottom;
            border: none;
        }
        .sand-sig-line {
            border-top: 1px solid #111827;
            margin-top: 30px;
            padding-top: 6px;
            min-height: 26px;
        }

        .sand-auto-msg {
            text-align: center;
            font-size: 8.5pt;
            color: #6b7280;
            margin-top: 26px;
            padding-top: 12px;
            border-top: 1px dashed #cbd5e1;
            line-height: 1.55;
        }

        .ltr {
            direction: ltr;
            text-align: left;
        }

        @yield('extra-styles')
    </style>
</head>
<body>
    <htmlpagefooter name="sand_qabd_footer">
        <div style="text-align: center; font-size: 8.5pt; color: #6b7280; border-top: 1px solid #d1d5db; padding-top: 6px; margin-top: 2px;">
            <strong style="color: #1B2A4A;">شركة راكز العقارية</strong>
            — <span dir="ltr">920015711</span> — www.rakez.sa
            — سجل تجاري 1010691801 — الرياض<br/>
            <span dir="ltr" style="font-size: 8pt;">— {PAGENO} / {nbpg} —</span>
        </div>
    </htmlpagefooter>
    <sethtmlpagefooter name="sand_qabd_footer" value="on" />

    @php
        $rakezLogoSrcForMpdf = null;
        foreach (['images/rakez-logo.png', 'images/rakez-logo.jpg', 'images/logo.png', 'images/logo.jpg'] as $rel) {
            $full = public_path($rel);
            if (! is_string($full) || ! is_readable($full)) {
                continue;
            }
            $resolved = realpath($full);
            if ($resolved === false) {
                continue;
            }
            $normalized = str_replace('\\', '/', $resolved);
            if ($normalized === '') {
                continue;
            }
            $rakezLogoSrcForMpdf = strncmp($normalized, '/', 1) === 0
                ? 'file://' . $normalized
                : 'file:///' . $normalized;
            break;
        }
    @endphp

    <table class="sand-brand-row" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 28%; text-align: right;">
                @if($rakezLogoSrcForMpdf)
                    <img src="{{ $rakezLogoSrcForMpdf }}" alt="" style="max-height: 44px; width: auto;">
                @else
                    <strong style="color: #1B2A4A; font-size: 11pt;">شركة راكز العقارية</strong>
                @endif
            </td>
            <td style="width: 44%; text-align: center; font-size: 8.5pt; color: #6b7280;">سجل تجاري 1010691801 — الرياض، حي الملقا</td>
            <td style="width: 28%; text-align: left; direction: ltr; font-size: 9pt;">RAKEZ REAL ESTATE</td>
        </tr>
    </table>

    <div class="sand-sheet-label">01</div>

    @yield('content')
</body>
</html>

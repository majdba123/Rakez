<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@yield('title')</title>
    <style>
        @page {
            margin: 14mm 12mm 22mm 12mm;
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
        /* Official receipt typography — clear, formal (مطابقة أسلوب سند قبض) */
        .rcpt-page-num {
            font-size: 13pt;
            font-weight: bold;
            color: #374151;
            margin: 0 0 8px 0;
        }
        .rcpt-brand-row {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 10px 0;
            border-bottom: 2px solid #1B2A4A;
            padding-bottom: 6px;
        }
        .rcpt-brand-row td {
            vertical-align: middle;
            border: none;
            font-size: 9.5pt;
            color: #4b5563;
        }
        .rcpt-doc-title {
            text-align: center;
            font-size: 17pt;
            font-weight: bold;
            color: #1B2A4A;
            letter-spacing: 0.03em;
            margin: 6px 0 16px 0;
            padding: 8px 0;
        }
        .rcpt-client-line {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 14px 0;
            font-size: 11.5pt;
        }
        .rcpt-client-line td {
            padding: 6px 4px;
            vertical-align: baseline;
            border: none;
        }
        .rcpt-diamond { color: #1B2A4A; font-weight: bold; }
        .rcpt-main-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 18px 0;
            border: 1.2px solid #111827;
        }
        .rcpt-main-table th {
            background-color: #e5e7eb;
            color: #111827;
            font-weight: bold;
            font-size: 10pt;
            padding: 8px 5px;
            border: 1px solid #374151;
            text-align: center;
        }
        .rcpt-main-table td {
            padding: 10px 6px;
            border: 1px solid #6b7280;
            font-size: 10.5pt;
            vertical-align: middle;
            text-align: center;
        }
        .rcpt-main-table td.rcpt-ref { font-weight: bold; }
        .rcpt-main-table .rcpt-type { text-align: center; line-height: 1.5; max-width: 95px; }
        .rcpt-section-h {
            font-size: 11.5pt;
            font-weight: bold;
            color: #1B2A4A;
            margin: 18px 0 8px 0;
            padding-bottom: 4px;
            border-bottom: 1px solid #9ca3af;
        }
        .rcpt-desc-block {
            font-size: 10.5pt;
            line-height: 1.85;
            text-align: justify;
            margin: 0 0 6px 0;
            color: #1f2937;
        }
        .rcpt-desc-num { font-weight: bold; color: #1B2A4A; }
        .rcpt-terms-wrap {
            margin: 10px 0 16px 0;
            padding: 0 0 0 4px;
        }
        .rcpt-term-item {
            font-size: 10pt;
            line-height: 1.75;
            text-align: justify;
            margin-bottom: 8px;
            color: #374151;
        }
        .rcpt-term-item .tn { font-weight: bold; color: #1B2A4A; }
        .rcpt-final-bullet {
            margin: 14px 0 20px 0;
            padding: 10px 12px;
            background-color: #f9fafb;
            border-right: 4px solid #1B2A4A;
            font-size: 10.5pt;
            font-weight: bold;
            line-height: 1.7;
            text-align: justify;
        }
        .rcpt-sig-grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 28px;
        }
        .rcpt-sig-grid td {
            width: 33%;
            text-align: center;
            font-size: 10pt;
            padding: 6px 8px;
            vertical-align: bottom;
            border: none;
        }
        .rcpt-sig-line {
            border-top: 1px solid #111827;
            margin-top: 36px;
            padding-top: 6px;
            min-height: 28px;
        }

        @yield('extra-styles')
    </style>
</head>
<body>
    <htmlpagefooter name="receipt_pdf_footer">
        <div style="text-align: center; font-size: 8.5pt; color: #6b7280; border-top: 1px solid #d1d5db; padding-top: 6px; margin-top: 4px;">
            شركة راكز العقارية — <span dir="ltr" style="display:inline-block;">920015711</span> — www.rakez.sa<br/>
            <span dir="ltr">— {PAGENO} / {nbpg} —</span>
        </div>
    </htmlpagefooter>
    <sethtmlpagefooter name="receipt_pdf_footer" value="on" />

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

    <table class="rcpt-brand-row" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 28%; text-align: right;">
                @if($rakezLogoSrcForMpdf)
                    <img src="{{ $rakezLogoSrcForMpdf }}" alt="" style="max-height: 46px; width: auto;">
                @else
                    <strong style="color: #1B2A4A; font-size: 11pt;">شركة راكز العقارية</strong>
                @endif
            </td>
            <td style="width: 44%; text-align: center; font-size: 8.5pt; color: #6b7280;">سجل تجاري 1010691801 — الرياض</td>
            <td style="width: 28%; text-align: left; direction: ltr; font-size: 9pt;">RAKEZ REAL ESTATE</td>
        </tr>
    </table>

    @yield('content')
</body>
</html>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@yield('title')</title>
    <style>
        @page {
            margin: 28px 40px 42mm 40px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'dejavusans', sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 11px;
            color: #222;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        /* ===== Document titles ===== */
        .doc-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            color: #1B2A4A;
            margin: 10px 0 5px 0;
        }
        .doc-title-en {
            text-align: center;
            font-size: 12px;
            color: #888;
            margin: 0 0 5px 0;
        }
        .doc-subtitle {
            text-align: center;
            font-size: 10px;
            color: #666;
            margin: 0 0 15px 0;
        }

        /* ===== Section title ===== */
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #1B2A4A;
            margin: 20px 0 8px 0;
            padding-bottom: 5px;
            border-bottom: 2px solid #1B2A4A;
        }

        /* ===== Info table (label / value rows) ===== */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 5px 0;
        }
        .info-table td {
            padding: 7px 10px;
            border: 1px solid #ddd;
            font-size: 10px;
        }
        .info-table td:first-child {
            width: 40%;
            background-color: #f5f7fa;
            font-weight: bold;
            color: #333;
        }

        /* ===== Data table (multi-column with headers) ===== */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .data-table th {
            background-color: #1B2A4A;
            color: #fff;
            padding: 8px 6px;
            border: 1px solid #152238;
            font-size: 10px;
            text-align: center;
            font-weight: bold;
        }
        .data-table td {
            padding: 8px 6px;
            border: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
        }
        .data-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        /* ===== Summary box ===== */
        .summary-box {
            margin: 15px 0;
            padding: 12px;
            background-color: #f5f7fa;
            border: 1px solid #ddd;
        }
        .summary-box p {
            margin: 4px 0;
            font-size: 10px;
        }

        /* ===== Empty message ===== */
        .empty-msg {
            color: #777;
            font-style: italic;
            padding: 10px;
            font-size: 10px;
        }

        /* ===== Status badges ===== */
        .status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .status-pending { background-color: #FFC107; color: #000; }
        .status-approved { background-color: #4CAF50; color: #fff; }
        .status-paid { background-color: #2196F3; color: #fff; }
        .status-rejected { background-color: #F44336; color: #fff; }
        .status-received { background-color: #4CAF50; color: #fff; }
        .status-confirmed { background-color: #2196F3; color: #fff; }
        .status-refunded { background-color: #F44336; color: #fff; }
        .status-draft { background-color: #FFC107; color: #000; }
        .status-active { background-color: #4CAF50; color: #fff; }
        .status-expired { background-color: #9E9E9E; color: #fff; }
        .status-terminated { background-color: #F44336; color: #fff; }

        /* Footer block: styling used inside <htmlpagefooter> (mPDF) */
        .footer-tbl {
            width: 100%;
            border-collapse: collapse;
        }
        .footer-tbl td {
            padding: 2px 5px;
            vertical-align: top;
            font-size: 7px;
            color: #666;
            border: none;
        }

        /* ===== Auto-generated message ===== */
        .auto-msg {
            text-align: center;
            font-size: 9px;
            color: #999;
            margin-top: 25px;
        }

        /* ===== LTR helper for English/numbers ===== */
        .ltr {
            direction: ltr;
            text-align: left;
        }

        /* ===== Logo header (text fallback) ===== */
        .pdf-logo-wordmark {
            display: inline-block;
            text-align: center;
            padding: 10px 24px 14px 24px;
            border: 2px solid #1B2A4A;
            border-radius: 4px;
            background: #fafbfd;
        }
        .pdf-logo-wordmark-ar {
            font-size: 20px;
            font-weight: bold;
            color: #1B2A4A;
            margin: 0 0 4px 0;
        }
        .pdf-logo-wordmark-en {
            font-size: 11px;
            color: #666;
            letter-spacing: 2px;
            margin: 0;
        }

        @yield('extra-styles')
    </style>
</head>
<body>
    {{-- mPDF: repeat footer on every page (CSS position:fixed is unreliable) --}}
    <htmlpagefooter name="rakez_pdf_footer">
        <div style="border-top: 2px solid #1B2A4A; padding-top: 6px; margin-top: 4px;">
            <table class="footer-tbl">
                <tr>
                    <td style="text-align: right; width: 30%;"><strong>شركة راكز العقارية</strong><br/>RAKEZ REAL ESTATE CO.</td>
                    <td style="text-align: center; width: 40%;">C.R. 1010691801<br/>المملكة العربية السعودية - الرياض 3130 شارع أنس بن مالك، حي الملقا</td>
                    <td style="text-align: left; width: 30%;">920015711<br/>www.rakez.sa</td>
                </tr>
            </table>
        </div>
    </htmlpagefooter>
    <sethtmlpagefooter name="rakez_pdf_footer" value="on" />

    @php
        // mPDF reads local images via file:// URLs; normalize slashes (required on Windows).
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

    <table style="width: 100%; margin-bottom: 12px;" cellpadding="0" cellspacing="0">
        <tr>
            <td style="text-align: center; padding: 6px 0 12px 0; border: none;">
                @if($rakezLogoSrcForMpdf)
                    <img src="{{ $rakezLogoSrcForMpdf }}" alt="راكز" style="max-height: 72px; width: auto;">
                @else
                    <div class="pdf-logo-wordmark">
                        <div class="pdf-logo-wordmark-ar">شركة راكز العقارية</div>
                        <div class="pdf-logo-wordmark-en">RAKEZ REAL ESTATE CO.</div>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    @yield('content')
</body>
</html>

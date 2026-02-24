<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@yield('title')</title>
    <style>
        @page { margin: 30px 40px 80px 40px; }
        body {
            font-family: 'DejaVu Sans', 'dejavusans', sans-serif;
            direction: rtl;
            text-align: right;
            font-size: 11px;
            color: #222;
            margin: 0;
            padding: 0;
            unicode-bidi: embed;
        }
        .ltr { direction: ltr; unicode-bidi: embed; text-align: left; }

        /* Logo */
        .logo-area { text-align: center; padding: 10px 0 15px 0; margin-bottom: 15px; }
        .logo-area img { height: 90px; width: auto; }

        /* Document titles */
        .doc-title { text-align: center; font-size: 16px; font-weight: bold; color: #1B2A4A; margin: 10px 0 5px 0; }
        .doc-title-en { text-align: center; font-size: 12px; color: #666; margin: 0 0 5px 0; }
        .doc-subtitle { text-align: center; font-size: 10px; color: #666; margin: 0 0 15px 0; }

        /* Section title */
        .section-title { font-size: 13px; font-weight: bold; color: #222; margin: 18px 0 8px 0; padding-bottom: 5px; border-bottom: 1px solid #999; }

        /* Info table (label / value rows) */
        .info-table { width: 100%; border-collapse: collapse; margin: 5px 0; }
        .info-table td { padding: 7px 8px; border: 1px solid #ddd; font-size: 10px; }
        .info-table td:first-child { width: 40%; background-color: #f5f5f5; font-weight: bold; color: #444; }

        /* Data table (multi-column with headers) */
        .data-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .data-table th { background-color: #8B8B8B; color: #fff; padding: 8px 6px; border: 1px solid #7B7B7B; font-size: 10px; text-align: center; font-weight: bold; }
        .data-table td { padding: 8px 6px; border: 1px solid #ddd; text-align: center; font-size: 10px; }
        .data-table tr:nth-child(even) { background-color: #f9f9f9; }

        /* Summary box */
        .summary-box { margin: 15px 0; padding: 10px; background-color: #f5f5f5; border: 1px solid #ddd; }
        .summary-box p { margin: 4px 0; font-size: 10px; }

        /* Empty message */
        .empty-msg { color: #777; font-style: italic; padding: 10px; font-size: 10px; }

        /* Status badges */
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 9px; font-weight: bold; }
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

        /* Footer (fixed on every page) */
        .footer-area { position: fixed; bottom: 0; left: 0; right: 0; border-top: 2px solid #1B2A4A; padding: 8px 30px; font-size: 7px; color: #666; }
        .footer-tbl { width: 100%; border-collapse: collapse; }
        .footer-tbl td { padding: 2px 5px; vertical-align: top; font-size: 7px; color: #666; }

        /* Auto-generated message */
        .auto-msg { text-align: center; font-size: 9px; color: #999; margin-top: 25px; }

        @yield('extra-styles')
    </style>
</head>
<body>
    {{-- Footer --}}
    <div class="footer-area">
        <table class="footer-tbl">
            <tr>
                <td style="text-align: right; width: 30%;"><strong>شركة راكز العقارية</strong><br>RAKEZ REAL ESTATE CO.</td>
                <td style="text-align: center; width: 40%;">C.R. 1010691801<br>المملكة العربية السعودية - الرياض 3130 شارع أنس بن مالك، حي الملقا</td>
                <td style="text-align: left; width: 30%;">920015711<br>www.rakez.sa</td>
            </tr>
        </table>
    </div>

    {{-- Logo --}}
    <div class="logo-area">
        <img src="{{ public_path('images/rakez-logo.png') }}" alt="راكز">
    </div>

    @yield('content')
</body>
</html>

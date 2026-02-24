<?php

namespace App\Services\Pdf;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Illuminate\Http\Response;

class PdfFactory
{
    public static function make(array $options = []): Mpdf
    {
        return new Mpdf(array_merge([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'dejavusans',
            'autoArabic' => true,
            'autoLangToFont' => true,
            'biDirectional' => true,
            'useSubstitutions' => true,
            'tempDir' => storage_path('app/mpdf'),
            'margin_top' => 10,
            'margin_bottom' => 25,
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_footer' => 8,
        ], $options));
    }

    /**
     * Render a Blade view to an mPDF instance.
     */
    public static function loadView(string $view, array $data = [], array $options = []): Mpdf
    {
        $html = view($view, $data)->render();

        return self::loadHTML($html, $options);
    }

    /**
     * Load raw HTML into an mPDF instance.
     */
    public static function loadHTML(string $html, array $options = []): Mpdf
    {
        $mpdf = self::make($options);
        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    /**
     * Generate PDF binary string from a Blade view.
     */
    public static function output(string $view, array $data = [], array $options = []): string
    {
        return self::loadView($view, $data, $options)
            ->Output('', Destination::STRING_RETURN);
    }

    /**
     * Return a download response for a Blade view.
     */
    public static function download(string $view, array $data, string $filename, array $options = []): Response
    {
        $content = self::output($view, $data, $options);

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => strlen($content),
        ]);
    }

    /**
     * Return an inline (stream) response for a Blade view.
     */
    public static function stream(string $view, array $data, string $filename, array $options = []): Response
    {
        $content = self::output($view, $data, $options);

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Content-Length' => strlen($content),
        ]);
    }
}

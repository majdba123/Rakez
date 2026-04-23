<?php

namespace App\Services\Pdf;

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Output\Destination;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use Mpdf\MpdfException;

/**
 * PDF generation wrapper using mPDF directly for correct Arabic (RTL + connected letters).
 *
 * Creates mPDF instance directly (bypassing laravel-mpdf facade) to ensure
 * fontdata with useOTL is properly passed to the constructor - which is
 * critical for Arabic letter shaping (connecting letters).
 *
 * Fonts are loaded from storage/fonts/ directory.
 */
class PdfFactory
{
    /**
     * Build a fresh mPDF instance with full Arabic support.
     *
     * @throws MpdfException
     */
    private static function createMpdf(array $options = []): Mpdf
    {
        $fontDir = storage_path('fonts');

        // Get mPDF default font directories
        $defaultConfig = (new ConfigVariables())->getDefaults();
        $defaultFontDirs = $defaultConfig['fontDir'] ?? [];

        // Get mPDF default font data
        $defaultFontConfig = (new FontVariables())->getDefaults();
        $defaultFontData = $defaultFontConfig['fontdata'] ?? [];

        // Put our custom font dir FIRST so our DejaVu files take priority
        $fontDirs = is_dir($fontDir)
            ? array_merge([$fontDir], $defaultFontDirs)
            : $defaultFontDirs;

        // Define our Arabic-capable font with OTL enabled
        // useOTL = 0xFF enables ALL OpenType Layout features (required for Arabic letter shaping)
        // useKashida = 75 enables Arabic text justification using kashida
        $customFontData = [
            'dejavusans' => [
                'R'  => 'DejaVuSans.ttf',
                'B'  => 'DejaVuSans-Bold.ttf',
                'I'  => 'DejaVuSans-Oblique.ttf',
                'BI' => 'DejaVuSans-BoldOblique.ttf',
                'useOTL'    => 0xFF,
                'useKashida' => 75,
            ],
        ];

        // Merge: our custom font data OVERRIDES defaults for 'dejavusans'
        // This ensures our useOTL setting is used instead of the default (which has no useOTL)
        $fontData = array_merge($defaultFontData, $customFontData);

        // Ensure temp directory exists
        $tempDir = storage_path('app/mpdf');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        // Build the config array - passed directly to mPDF constructor
        $mpdfConfig = array_merge([
            'mode'                     => 'utf-8',
            'format'                   => 'A4',
            'default_font_size'        => 11,
            'default_font'             => 'dejavusans',
            'margin_left'              => 12,
            'margin_right'             => 12,
            'margin_top'               => 10,
            'margin_bottom'            => 25,
            'margin_header'            => 0,
            'margin_footer'            => 8,

            // Font directories & data (with OTL)
            'fontDir'                  => $fontDirs,
            'fontdata'                 => $fontData,

            // Temp directory for mPDF cache
            'tempDir'                  => $tempDir,

            // Arabic-specific settings
            'autoScriptToLang'         => true,
            'autoLangToFont'           => true,
            'autoArabic'               => true,
            'biDirectional'            => true,
            'useSubstitutions'         => true,
            'allow_charset_conversion' => true,
            'useOTL'                   => 0xFF,
            'useKashida'               => 75,
        ], $options);

        $mpdf = new Mpdf($mpdfConfig);

        // Set directionality BEFORE any HTML is written (critical for Arabic)
        $mpdf->SetDirectionality('rtl');
        $mpdf->showImageErrors = false;

        // Set metadata
        $mpdf->SetTitle(config('pdf.title', 'راكز العقارية'));
        $mpdf->SetAuthor(config('pdf.author', 'Rakez ERP'));

        return $mpdf;
    }

    /**
     * Generate PDF binary string from a Blade view.
     *
     * @throws MpdfException When mPDF fails (e.g. font file not found)
     */
    public static function output(string $view, array $data = [], array $options = []): string
    {
        try {
            $mpdf = self::createMpdf($options);

            // Render the Blade view to HTML
            $html = View::make($view, $data)->render();

            // Ensure UTF-8 encoding
            if (!mb_check_encoding($html, 'UTF-8')) {
                $html = mb_convert_encoding(
                    $html,
                    'UTF-8',
                    mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1256'], true) ?: 'UTF-8'
                );
            }

            // Clean control characters (preserve Arabic/RTL characters)
            $html = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $html) ?? $html;

            // Write HTML and generate PDF
            $mpdf->WriteHTML($html);

            return $mpdf->Output('', Destination::STRING_RETURN);
        } catch (MpdfException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Cannot find TTF') !== false || stripos($msg, 'font') !== false) {
                throw new MpdfException(
                    'خطأ في تحميل خط الـ PDF. تأكد من وجود ملفات DejaVuSans.ttf في storage/fonts. ' . $msg
                );
            }
            throw $e;
        }
    }

    /**
     * Return a PDF response for a Blade view.
     *
     * By default the PDF opens in the browser (inline); users can still save from the viewer toolbar.
     * Add query ?download=1 to force Save as file (attachment). Pass false as $inline to force attachment from code.
     *
     * @param  ?bool  $inline  null/true = show in browser; false = send as download only.
     */
    public static function download(string $view, array $data, string $filename, array $options = [], ?bool $inline = null): Response
    {
        return self::pdfResponse(self::output($view, $data, $options), $filename, $inline);
    }

    /**
     * Return an inline PDF response (same as download with $inline = true).
     */
    public static function stream(string $view, array $data, string $filename, array $options = []): Response
    {
        return self::pdfResponse(self::output($view, $data, $options), $filename, true);
    }

    /**
     * Wrap raw PDF bytes as an HTTP response suitable for browsers and Postman.
     *
     * Default: Content-Disposition inline so the PDF displays in the tab; append ?download=1 to force attachment.
     * Pass $inline === false to always use attachment (e.g. export-only actions in code).
     */
    public static function pdfResponse(string $content, string $filename, ?bool $inline = null): Response
    {
        $forceDownload = request()->boolean('download');
        $disposition = ($inline === false || $forceDownload) ? 'attachment' : 'inline';
        $safeFilename = str_replace(["\r", "\n", '"', '\\'], '', $filename);
        if ($safeFilename === '') {
            $safeFilename = 'document.pdf';
        }

        $asciiFallback = preg_replace('/[^\x20-\x7E]/', '_', $safeFilename) ?: 'document.pdf';
        $utf8Star = "filename*=UTF-8''" . rawurlencode($safeFilename);

        $length = strlen($content);

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf(
                '%s; filename="%s"; %s',
                $disposition,
                addcslashes($asciiFallback, '"\\'),
                $utf8Star
            ),
            'Content-Length' => (string) $length,
            'Cache-Control' => 'private, no-transform',
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges' => 'bytes',
        ]);
    }
}

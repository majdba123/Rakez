<?php

namespace App\Services\AI\Rag;

use Illuminate\Support\Facades\Log;
use Throwable;
use ZipArchive;

class DocumentAnalyzerService
{
    /**
     * Extract text content from a file.
     */
    public function extractText(string $filePath, string $mimeType): string
    {
        return match (true) {
            $this->isPdf($mimeType) => $this->extractFromPdf($filePath),
            $this->isDocx($mimeType) => $this->extractFromDocx($filePath),
            $this->isPlainText($mimeType) => $this->extractFromText($filePath),
            default => '',
        };
    }

    /**
     * Analyze a file and return structured information.
     *
     * @return array{text: string, page_count: int, metadata: array}
     */
    public function analyze(string $filePath, string $mimeType): array
    {
        $text = $this->extractText($filePath, $mimeType);

        return [
            'text' => $text,
            'page_count' => $this->isPdf($mimeType) ? $this->getPdfPageCount($filePath) : 1,
            'metadata' => [
                'mime_type' => $mimeType,
                'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
                'char_count' => mb_strlen($text),
                'estimated_tokens' => (int) ceil(mb_strlen($text) / 4),
            ],
        ];
    }

    /**
     * Get supported MIME types.
     *
     * @return array<string>
     */
    public static function supportedMimeTypes(): array
    {
        return [
            'application/pdf',
            'text/plain',
            'text/markdown',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
    }

    /**
     * Extract text from a PDF file using smalot/pdfparser.
     */
    private function extractFromPdf(string $filePath): string
    {
        try {
            if (! class_exists(\Smalot\PdfParser\Parser::class)) {
                Log::warning('DocumentAnalyzer: smalot/pdfparser not installed. PDF extraction unavailable.');

                return '';
            }

            $parser = new \Smalot\PdfParser\Parser;
            $pdf = $parser->parseFile($filePath);

            return $pdf->getText() ?? '';
        } catch (Throwable $e) {
            Log::warning('DocumentAnalyzer: PDF extraction failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Get page count from PDF.
     */
    private function getPdfPageCount(string $filePath): int
    {
        try {
            if (! class_exists(\Smalot\PdfParser\Parser::class)) {
                return 0;
            }

            $parser = new \Smalot\PdfParser\Parser;
            $pdf = $parser->parseFile($filePath);

            return count($pdf->getPages());
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Extract text from a DOCX file using ZipArchive.
     */
    private function extractFromDocx(string $filePath): string
    {
        try {
            $zip = new ZipArchive;

            if ($zip->open($filePath) !== true) {
                return '';
            }

            $content = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($content === false) {
                return '';
            }

            // Strip XML tags and clean up
            $text = strip_tags($content);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $text = preg_replace('/\s+/', ' ', $text) ?? $text;

            return trim($text);
        } catch (Throwable $e) {
            Log::warning('DocumentAnalyzer: DOCX extraction failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * Extract text from a plain text / markdown file.
     */
    private function extractFromText(string $filePath): string
    {
        try {
            $content = file_get_contents($filePath);

            return $content !== false ? $content : '';
        } catch (Throwable) {
            return '';
        }
    }

    private function isPdf(string $mimeType): bool
    {
        return $mimeType === 'application/pdf';
    }

    private function isDocx(string $mimeType): bool
    {
        return $mimeType === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }

    private function isPlainText(string $mimeType): bool
    {
        return in_array($mimeType, ['text/plain', 'text/markdown']);
    }
}

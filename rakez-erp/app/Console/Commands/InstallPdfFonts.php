<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class InstallPdfFonts extends Command
{
    protected $signature = 'pdf:install-fonts
                            {--force : Overwrite existing font files}';

    protected $description = 'Install DejaVu Sans fonts in storage/fonts for Arabic PDF export';

    private const FONT_DIR = 'storage/fonts';

    /** SourceForge zip with all DejaVu TTF (contains ttf/DejaVuSans.ttf etc.). */
    private const SOURCEFORGE_ZIP = 'https://sourceforge.net/projects/dejavu/files/dejavu/2.37/dejavu-fonts-ttf-2.37.zip/download';

    public function handle(): int
    {
        $dir = storage_path('fonts');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $required = ['DejaVuSans.ttf'];
        $wanted = ['DejaVuSans.ttf', 'DejaVuSans-Bold.ttf', 'DejaVuSans-Oblique.ttf', 'DejaVuSans-BoldOblique.ttf'];

        $missing = [];
        foreach ($wanted as $name) {
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (!file_exists($path) || $this->option('force')) {
                $missing[] = $name;
            }
        }

        if (empty($missing)) {
            $this->info('DejaVu fonts already present in ' . self::FONT_DIR . '.');
            return self::SUCCESS;
        }

        $this->info('Installing DejaVu fonts for PDF (Arabic support)...');

        if ($this->installFromSourceForgeZip($dir, $missing)) {
            if (file_exists($dir . DIRECTORY_SEPARATOR . 'DejaVuSans.ttf')) {
                $this->info('PDF fonts ready. You can export Arabic PDFs now.');
                return self::SUCCESS;
            }
        }

        $this->newLine();
        $this->warn('DejaVuSans.ttf could not be downloaded automatically.');
        $this->line('1. Download from: https://dejavu-fonts.github.io/Download.html');
        $this->line('2. Extract and copy DejaVuSans.ttf to: ' . $dir);
        $this->line('3. See: docs/PDF_ARABIC_FONTS.md');
        return self::FAILURE;
    }

    /**
     * Download SourceForge zip and extract wanted TTF files into $dir.
     */
    private function installFromSourceForgeZip(string $dir, array $wantedNames): bool
    {
        $this->line('  Downloading DejaVu fonts archive from SourceForge...');
        $response = Http::timeout(120)
            ->withOptions(['verify' => true])
            ->withHeaders(['User-Agent' => 'Laravel-PDF-Font-Installer/1.0'])
            ->get(self::SOURCEFORGE_ZIP);

        if (!$response->successful() || strlen($response->body()) < 10000) {
            $this->warn('  -> Archive download failed (response or size).');
            return false;
        }

        $zipPath = $dir . DIRECTORY_SEPARATOR . 'dejavu-fonts-temp.zip';
        file_put_contents($zipPath, $response->body());

        $extracted = 0;
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if ($entry === false) {
                    continue;
                }
                $base = basename($entry);
                if (!in_array($base, $wantedNames, true)) {
                    continue;
                }
                $content = $zip->getFromIndex($i);
                if ($content !== false && strlen($content) > 1000) {
                    $target = $dir . DIRECTORY_SEPARATOR . $base;
                    file_put_contents($target, $content);
                    $this->info("  -> Extracted {$base}");
                    $extracted++;
                }
            }
            $zip->close();
        }
        @unlink($zipPath);

        return $extracted > 0;
    }
}

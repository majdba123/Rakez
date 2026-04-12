<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads CSV or Excel (first sheet) into associative rows keyed by header (row 1).
 */
final class TabularImportReader
{
    /**
     * Normalized header cells (lowercase, trimmed) from the first row.
     *
     * @return list<string>
     */
    public static function peekHeader(string $fullPath): array
    {
        $ext = strtolower((string) pathinfo($fullPath, PATHINFO_EXTENSION));

        if (in_array($ext, ['xlsx', 'xls'], true)) {
            $spreadsheet = IOFactory::load($fullPath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, false);
            if (empty($data) || empty($data[0])) {
                return [];
            }
            $header = array_map(fn ($col) => strtolower(trim((string) $col)), $data[0]);

            return self::trimTrailingEmptyHeaderCells($header);
        }

        $handle = fopen($fullPath, 'r');
        if ($handle === false) {
            return [];
        }
        $header = fgetcsv($handle);
        fclose($handle);
        if (! $header) {
            return [];
        }

        return array_map(fn ($col) => strtolower(trim((string) $col)), $header);
    }

    /**
     * @return list<array<string, string|null>>
     */
    public static function parseAssocRows(string $fullPath): array
    {
        $ext = strtolower((string) pathinfo($fullPath, PATHINFO_EXTENSION));

        if (in_array($ext, ['xlsx', 'xls'], true)) {
            return self::parseExcelAssocRows($fullPath);
        }

        return self::parseCsvAssocRows($fullPath);
    }

    /**
     * @return list<array<string, string|null>>
     */
    private static function parseCsvAssocRows(string $fullPath): array
    {
        $handle = fopen($fullPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open CSV file.');
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty or has no header row.');
        }

        $header = array_map(fn ($col) => strtolower(trim((string) $col)), $header);

        $rows = [];
        $lineNumber = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if (self::csvLineIsEmpty($line)) {
                continue;
            }
            if (count($line) !== count($header)) {
                fclose($handle);
                throw new \RuntimeException("Row {$lineNumber} has a column count mismatch with the header.");
            }
            $rows[] = array_combine($header, array_map(fn ($v) => $v === null || $v === '' ? null : trim((string) $v), $line));
        }

        fclose($handle);

        if ($rows === []) {
            throw new \RuntimeException('CSV file contains no data rows.');
        }

        return $rows;
    }

    /**
     * @return list<array<string, string|null>>
     */
    private static function parseExcelAssocRows(string $fullPath): array
    {
        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);

        if (empty($data) || empty($data[0])) {
            throw new \RuntimeException('Excel file is empty or has no header row.');
        }

        $header = array_map(fn ($col) => strtolower(trim((string) $col)), $data[0]);
        $header = self::trimTrailingEmptyHeaderCells($header);

        if ($header === [] || ($header[0] ?? '') === '') {
            throw new \RuntimeException('Excel file has no header row.');
        }

        $headerCount = count($header);
        $rows = [];
        $lineNumber = 1;

        foreach (array_slice($data, 1) as $line) {
            $lineNumber++;
            if (! is_array($line)) {
                continue;
            }
            $line = array_values($line);
            if (self::excelRowIsEmpty($line)) {
                continue;
            }

            if (count($line) < $headerCount) {
                $line = array_pad($line, $headerCount, '');
            } elseif (count($line) > $headerCount) {
                $line = array_slice($line, 0, $headerCount);
            }

            $combined = [];
            for ($i = 0; $i < $headerCount; $i++) {
                $key = $header[$i];
                if ($key === '') {
                    continue;
                }
                $val = $line[$i] ?? '';
                $combined[$key] = $val === null || $val === '' ? null : trim((string) $val);
            }

            $rows[] = $combined;
        }

        if ($rows === []) {
            throw new \RuntimeException('Excel file contains no data rows.');
        }

        return $rows;
    }

    /**
     * @param  list<string>  $header
     * @return list<string>
     */
    private static function trimTrailingEmptyHeaderCells(array $header): array
    {
        while ($header !== [] && end($header) === '') {
            array_pop($header);
        }

        return $header;
    }

    /**
     * @param  list<string|null>|null  $line
     */
    private static function csvLineIsEmpty(?array $line): bool
    {
        if ($line === null || $line === []) {
            return true;
        }
        foreach ($line as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<mixed>  $line
     */
    private static function excelRowIsEmpty(array $line): bool
    {
        foreach ($line as $cell) {
            if ($cell !== null && $cell !== '' && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}

<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Plain synchronous CSV streaming — deliberately not Filament's native async
 * export (queue job + notification + export-storage table). That machinery can't be
 * exercised in this environment (no PHP/queue worker available to verify against), and
 * a direct streamDownload is simpler, synchronous, and easy to reason about by hand.
 */
class CsvExportService
{
    /**
     * @param  string[]  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_map([self::class, 'sanitizeCell'], $headers));
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'sanitizeCell'], $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * CSV injection guard (OWASP): a cell starting with =, +, -, @, tab, or CR that
     * Excel/Sheets opens as a formula. Product names, cashier names, and free-text
     * reason/notes fields all pass through here since they're user-supplied. Prefixing
     * a leading apostrophe neutralizes formula evaluation while keeping the value
     * human-readable.
     */
    private static function sanitizeCell(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}

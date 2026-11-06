<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter
{
    /**
     * Download data as a UTF-8 CSV file seamlessly opened in Microsoft Excel.
     *
     * @param string $filename
     * @param array $headers
     * @param iterable $rows
     * @param string $delimiter
     * @return StreamedResponse
     */
    public static function download(string $filename, array $headers, iterable $rows, string $delimiter = ','): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows, $delimiter) {
            $handle = fopen('php://output', 'w');

            // Write UTF-8 BOM for Microsoft Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Write headers
            fputcsv($handle, $headers, $delimiter);

            // Write data rows
            foreach ($rows as $row) {
                fputcsv($handle, (array) $row, $delimiter);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0, no-cache, must-revalidate, proxy-revalidate');

        return $response;
    }
}


<?php

namespace App\Domains\Reporting\Services;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public const XLSX_MAX_ROWS = 10000;

    public const XLSX_MAX_CELLS = 200000;

    public const XLSX_MAX_TEXT_BYTES = 8000000;

    public static function sanitizeCell(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        if (strlen($value) > 0 && in_array($value[0], ["\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    public function export(
        string $baseFilename,
        array $headers,
        iterable $rows,
        string $format = 'csv',
        string $sheetTitle = 'Report'
    ): StreamedResponse|BinaryFileResponse {
        $format = strtolower(trim($format)) === 'xlsx' ? 'xlsx' : 'csv';

        if ($format === 'xlsx') {
            return $this->exportXlsx($baseFilename.'.xlsx', $headers, $rows, $sheetTitle);
        }

        return $this->exportCsv($baseFilename.'.csv', $headers, $rows);
    }

    public function exportCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $sanitizedHeaders = array_map([self::class, 'sanitizeCell'], $headers);

        $response = new StreamedResponse(function () use ($sanitizedHeaders, $rows) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, $sanitizedHeaders);

            foreach ($rows as $row) {
                $sanitizedRow = array_map([self::class, 'sanitizeCell'], array_values($row));
                fputcsv($handle, $sanitizedRow);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    public function exportXlsx(
        string $filename,
        array $headers,
        iterable $rows,
        string $sheetTitle = 'Report'
    ): BinaryFileResponse {
        $cellCount = count($headers);
        $textBytes = array_sum(array_map(fn (mixed $value): int => is_string($value) ? strlen($value) : 0, $headers));
        $memorySetting = strtoupper(trim((string) ini_get('memory_limit')));
        $memoryLimit = preg_match('/^(\d+)([KMG]?)$/', $memorySetting, $matches)
            ? (int) $matches[1] * (1024 ** match ($matches[2]) {
                'K' => 1, 'M' => 2, 'G' => 3, default => 0
            })
            : null;
        $this->assertXlsxCapacity(0, $cellCount, $textBytes, $memoryLimit);
        $spreadsheet = new Spreadsheet;
        $tempFilePath = null;
        try {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(substr($sheetTitle, 0, 31));

            $colIndex = 1;
            foreach ($headers as $header) {
                $cell = $sheet->getCell([$colIndex, 1]);
                $cell->setValueExplicit(self::sanitizeCell($header), DataType::TYPE_STRING);
                $colIndex++;
            }

            $highestColumn = $sheet->getHighestColumn();
            $headerRange = 'A1:'.$highestColumn.'1';

            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '1E293B'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F1F5F9'],
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => 'CBD5E1'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            $rowIndex = 2;
            foreach ($rows as $row) {
                $cellCount += count($row);
                $textBytes += array_sum(array_map(fn (mixed $value): int => is_string($value) ? strlen($value) : 0, $row));
                $this->assertXlsxCapacity($rowIndex - 1, $cellCount, $textBytes, $memoryLimit);
                $colIndex = 1;
                foreach (array_values($row) as $value) {
                    $sanitized = self::sanitizeCell($value);
                    $sheet->getCell([$colIndex, $rowIndex])->setValueExplicit(
                        $sanitized,
                        is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING,
                    );
                    $colIndex++;
                }
                $rowIndex++;
            }
            $this->assertXlsxCapacity($rowIndex - 2, $cellCount, $textBytes, $memoryLimit);

            foreach (range(1, count($headers)) as $col) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
            }

            $tempDir = storage_path('app/exports');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempFilePath = $tempDir.'/'.uniqid('exp_', true).'.xlsx';

            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFilePath);

            return response()->download($tempFilePath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (\Throwable $exception) {
            if ($tempFilePath !== null && is_file($tempFilePath)) {
                unlink($tempFilePath);
            }
            throw $exception;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function assertXlsxCapacity(int $rows, int $cells, int $textBytes, ?int $memoryLimit): void
    {
        if ($rows > self::XLSX_MAX_ROWS || $cells > self::XLSX_MAX_CELLS || $textBytes > self::XLSX_MAX_TEXT_BYTES
            || ($memoryLimit !== null && memory_get_usage(true) > $memoryLimit * 0.65)) {
            throw ValidationException::withMessages(['format' => 'This report exceeds the XLSX size or memory limit (at most 10,000 rows / 200,000 cells). Export CSV with the same filters, or narrow the filters.']);
        }
    }
}

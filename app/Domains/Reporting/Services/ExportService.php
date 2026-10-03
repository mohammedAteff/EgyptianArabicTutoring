<?php

namespace App\Domains\Reporting\Services;

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
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($sheetTitle, 0, 31));

        $colIndex = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCell([$colIndex, 1]);
            $cell->setValue(self::sanitizeCell($header));
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

        foreach (range('A', $highestColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
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
    }
}

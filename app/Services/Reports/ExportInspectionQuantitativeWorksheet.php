<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportInspectionQuantitativeWorksheet
{
    /** @param array<string, mixed> $payload */
    public function download(array $payload, string $filename): StreamedResponse
    {
        // BIFF8 permits 65,536 rows, including our seven header rows.
        if (count($payload['rows']) > 65529) {
            throw ValidationException::withMessages(['export' => 'O quantitativo excede o limite de linhas do formato XLS.']);
        }

        return response()->streamDownload(function () use ($payload): void {
            $spreadsheet = $this->spreadsheet($payload);

            try {
                (new Xls($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, $filename, ['Content-Type' => 'application/vnd.ms-excel', 'Cache-Control' => 'private, no-store']);
    }

    /** @param array<string, mixed> $payload */
    public function spreadsheet(array $payload): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('QUANTITATIVO');
        $lastColumn = Coordinate::stringFromColumnIndex(count($payload['columns']));
        $theme = $payload['theme'];
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10)->getColor()->setRGB($this->rgb($theme['text']));
        $sheet->getSheetView()->setZoomScale(65);
        $sheet->setShowGridlines(false);
        $sheet->getDefaultRowDimension()->setRowHeight(22);

        foreach ($payload['columns'] as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($letter)->setWidth($column['width']);
            $this->writeCell($sheet, $letter.'7', ['value' => $column['label']], 'text');
        }

        $sheet->mergeCells("A1:{$lastColumn}1");
        $this->writeCell($sheet, 'A1', ['value' => $payload['title']], 'text');
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setSize(26)->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(40);

        foreach ($payload['header_rows'] as $index => $headerRow) {
            $row = $index + 2;
            $this->writeCell($sheet, 'D'.$row, ['value' => $headerRow['label']], 'text');
            $sheet->getStyle('D'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('D'.$row)->getFont()->setBold(true);
            $sheet->mergeCells("E{$row}:J{$row}");
            $this->writeCell($sheet, 'E'.$row, $payload['header'][$headerRow['key']], $headerRow['key'] === 'inspection_date' ? 'date' : 'text');
            $sheet->getStyle("E{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFCC');
            if ($headerRow['secondary_key'] !== null) {
                $this->writeCell($sheet, 'K'.$row, ['value' => $headerRow['secondary_label']], 'text');
                $sheet->mergeCells("L{$row}:M{$row}");
                $this->writeCell($sheet, 'L'.$row, $payload['header'][$headerRow['secondary_key']], $headerRow['secondary_key'] === 'row_count' ? 'number' : 'text');
                $sheet->getStyle("L{$row}:M{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFCC');
            }
        }

        $sheet->getRowDimension(6)->setRowHeight(12);
        $sheet->getRowDimension(7)->setRowHeight(44);
        $sheet->getStyle("A7:{$lastColumn}7")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $this->rgb($theme['header_color'])]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->rgb($theme['header_background'])]],
        ]);

        foreach ($payload['rows'] as $index => $row) {
            $rowNumber = $index + 8;
            $lines = 1;
            foreach ($payload['columns'] as $columnIndex => $column) {
                $coordinate = Coordinate::stringFromColumnIndex($columnIndex + 1).$rowNumber;
                $cell = $row['cells'][$column['key']];
                $this->writeCell($sheet, $coordinate, $cell, $column['type']);
                $sheet->getStyle($coordinate)->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($this->rgb($cell['background'] ?? $column['background']));
                if (isset($cell['color'])) {
                    $sheet->getStyle($coordinate)->getFont()->getColor()->setRGB($this->rgb($cell['color']));
                }
                foreach (explode("\n", $cell['display']) as $text) {
                    $lines = max($lines, (int) ceil(mb_strlen($text) / max(1, $column['width'] - 3)));
                }
            }
            $sheet->getRowDimension($rowNumber)->setRowHeight(max(22, $lines * 15 + 6));
        }

        $lastRow = count($payload['rows']) + 7;
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("A7:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB($this->rgb($theme['border']));
        $sheet->freezePane('E8');
        $sheet->setAutoFilter("A7:{$lastColumn}{$lastRow}");
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A3)
            ->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(7, 7)->setPrintArea("A1:{$lastColumn}{$lastRow}");

        return $spreadsheet;
    }

    /** @param array<string, mixed> $cell */
    private function writeCell(Worksheet $sheet, string $coordinate, array $cell, string $type): void
    {
        $value = $cell['value'];
        if ($value === null) {
            return;
        }

        if ($type === 'date') {
            $sheet->setCellValueExplicit($coordinate, Date::PHPToExcel(new \DateTimeImmutable($value)), DataType::TYPE_NUMERIC);
            $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        } elseif ($type === 'quantity' || $type === 'number') {
            $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_NUMERIC);
            $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode($type === 'quantity' ? '#,##0.00 "'.$cell['unit'].'"' : '0');
            $sheet->getStyle($coordinate)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        } else {
            // Codes (including leading zeroes or '=') are literal text, never formulas.
            $sheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);
        }
    }

    private function rgb(string $hex): string
    {
        return strtoupper(ltrim($hex, '#'));
    }
}

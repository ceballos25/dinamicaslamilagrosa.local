<?php
declare(strict_types=1);

namespace App\Application\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class TransferenciasWorkbookExporter
{
    private const IMAGE_COL = 'O';
    private const IMAGE_ROW_HEIGHT = 88;
    private const IMAGE_MAX_HEIGHT = 80;

    /**
     * @param list<array<string, mixed>> $transfers
     * @param array<int, string> $comprobantePaths id_transfer => absolute path
     */
    public function write(string $targetPath, array $transfers, array $comprobantePaths): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Transferencias');

        $headers = [
            'A' => 'Código',
            'B' => 'Fecha',
            'C' => 'Estado',
            'D' => 'Cantidad',
            'E' => 'Monto',
            'F' => 'Ref. comprobante',
            'G' => 'Ref. normalizada',
            'H' => 'Banco',
            'I' => 'Origen',
            'J' => 'Números',
            'K' => 'Código venta',
            'L' => 'Estado venta',
            'M' => 'Cliente',
            'N' => 'Celular',
            self::IMAGE_COL => 'Comprobante',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
        }

        $sheet->getColumnDimension(self::IMAGE_COL)->setWidth(18);
        $sheet->freezePane('A2');

        $row = 2;
        foreach ($transfers as $transfer) {
            $id = (int)($transfer['id_transfer'] ?? 0);
            $cliente = trim(
                (string)($transfer['name_customer'] ?? '') . ' ' . (string)($transfer['lastname_customer'] ?? '')
            );

            $sheet->setCellValue('A' . $row, (string)($transfer['code_transfer'] ?? ''));
            $sheet->setCellValue('B' . $row, (string)($transfer['date_created_transfer'] ?? ''));
            $sheet->setCellValue('C' . $row, $this->statusLabel((int)($transfer['status_transfer'] ?? 0)));
            $sheet->setCellValue('D' . $row, (int)($transfer['quantity_transfer'] ?? 0));
            $sheet->setCellValue('E' . $row, (float)($transfer['amount_transfer'] ?? 0));
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0');
            $sheet->setCellValue('F' . $row, (string)($transfer['proof_reference'] ?? ''));
            $sheet->setCellValue('G' . $row, (string)($transfer['proof_reference_norm'] ?? ''));
            $sheet->setCellValue('H' . $row, (string)($transfer['proof_bank'] ?? ''));
            $sheet->setCellValue('I' . $row, $this->sourceLabel($transfer['source_transfer'] ?? null));
            $sheet->setCellValue('J' . $row, (string)($transfer['numbers_label'] ?? ''));
            $sheet->setCellValue('K' . $row, (string)($transfer['sale_code'] ?? ''));
            $sheet->setCellValue('L' . $row, (string)($transfer['sale_status'] ?? ''));
            $sheet->setCellValue('M' . $row, $cliente);
            $sheet->setCellValue('N' . $row, (string)($transfer['phone_customer'] ?? ''));

            $imagePath = $comprobantePaths[$id] ?? '';
            if ($imagePath !== '' && is_file($imagePath) && is_readable($imagePath)) {
                $sheet->getRowDimension($row)->setRowHeight(self::IMAGE_ROW_HEIGHT);
                $this->embedImage($sheet, self::IMAGE_COL . $row, $imagePath);
            }

            ++$row;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($targetPath);
        $spreadsheet->disconnectWorksheets();
    }

    private function embedImage(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $cell, string $path): void
    {
        $drawing = new Drawing();
        $drawing->setPath($path);
        $drawing->setCoordinates($cell);
        $drawing->setHeight(self::IMAGE_MAX_HEIGHT);
        $drawing->setOffsetX(4);
        $drawing->setOffsetY(4);
        $drawing->setWorksheet($sheet);
    }

    private function statusLabel(int $status): string
    {
        return match ($status) {
            1 => 'Pendiente',
            2 => 'Aprobada',
            3 => 'Rechazada',
            default => 'Desconocido',
        };
    }

    private function sourceLabel(mixed $source): string
    {
        $value = trim((string)($source ?? ''));
        if ($value === '' || strcasecmp($value, 'null') === 0) {
            return 'Sin origen';
        }

        return $value;
    }
}

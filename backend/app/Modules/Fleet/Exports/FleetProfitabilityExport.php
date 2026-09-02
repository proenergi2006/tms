<?php

namespace App\Modules\Fleet\Exports;

use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Ekspor .xlsx asli untuk Laporan Profitabilitas Armada — Wireframe Document
 * Bagian 2.7 & Design Document Bagian 3.5. $rows datang dari
 * FleetProfitabilityReportController::buildReport().
 *
 * Baris data DITULIS MANUAL lewat event AfterSheet (bukan concern FromArray)
 * karena PhpSpreadsheet\Worksheet::fromArray() membandingkan nilai sel
 * dengan $nullValue memakai `==` (longgar) — nilai `0`/`0.0` (biaya/profit
 * yang benar-benar nol) ikut cocok dengan `null` lewat perbandingan longgar
 * PHP dan selnya jadi dikosongkan. Diverifikasi langsung: file hasil FromArray
 * dibuka ulang lewat PhpSpreadsheet, kolom bernilai 0 selalu NULL walau data
 * sumbernya benar. setCellValueExplicit() dengan tipe numerik eksplisit tidak
 * kena masalah ini.
 */
class FleetProfitabilityExport implements ShouldAutoSize, WithEvents, WithHeadings
{
    public function __construct(private readonly array $rows) {}

    public function headings(): array
    {
        return ['Armada', 'No. Polisi', 'Cabang', 'Total Biaya', 'Total Pendapatan', 'Profit/Loss'];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle(1)->getFont()->setBold(true);

                $rowNumber = 2;
                foreach ($this->rows as $row) {
                    $sheet->setCellValueExplicit("A{$rowNumber}", $row['fleet_type'], DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit("B{$rowNumber}", $row['plate_number'], DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit("C{$rowNumber}", $row['branch'], DataType::TYPE_STRING);
                    $sheet->setCellValueExplicit("D{$rowNumber}", $row['total_cost'], DataType::TYPE_NUMERIC);
                    $sheet->setCellValueExplicit("E{$rowNumber}", $row['total_revenue'], DataType::TYPE_NUMERIC);
                    $sheet->setCellValueExplicit("F{$rowNumber}", $row['profit'], DataType::TYPE_NUMERIC);
                    $rowNumber++;
                }

                $lastRow = $rowNumber - 1;
                if ($lastRow >= 2) {
                    $sheet->getStyle("D2:F{$lastRow}")
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                }
            },
        ];
    }
}

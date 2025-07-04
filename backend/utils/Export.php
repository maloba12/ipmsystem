<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use TCPDF;

class Export {
    private $spreadsheet;
    private $pdf;
    private $data;
    private $title;
    private $headers;

    public function __construct($title, $headers, $data) {
        $this->title = $title;
        $this->headers = $headers;
        $this->data = $data;
    }

    // Export to Excel
    public function toExcel() {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        $sheet->setCellValue('A1', $this->title);
        $sheet->mergeCells('A1:' . $this->getColumnLetter(count($this->headers)) . '1');
        $sheet->getStyle('A1')->getFont()->setBold(true);
        
        // Set headers
        $column = 'A';
        foreach ($this->headers as $header) {
            $sheet->setCellValue($column . '2', $header);
            $column++;
        }
        
        // Set data
        $row = 3;
        foreach ($this->data as $record) {
            $column = 'A';
            foreach ($record as $value) {
                $sheet->setCellValue($column . $row, $value);
                $column++;
            }
            $row++;
        }

        // Auto-size columns
        foreach (range('A', $this->getColumnLetter(count($this->headers))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Create writer and output
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $this->title . '.xlsx"');
        $writer->save('php://output');
        exit;
    }

    // Export to PDF
    public function toPDF() {
        $pdf = new TCPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('IPMS System');
        $pdf->SetAuthor('IPMS System');
        $pdf->SetTitle($this->title);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 12);
        
        // Add title
        $pdf->Cell(0, 10, $this->title, 0, 1, 'C');
        $pdf->Ln(10);
        
        // Add headers
        $w = array_fill(0, count($this->headers), 40);
        $pdf->SetFillColor(255, 0, 0);
        $pdf->SetTextColor(255);
        $pdf->SetDrawColor(128, 0, 0);
        $pdf->SetLineWidth(0.3);
        
        $pdf->SetFont('', 'B');
        for($i = 0; $i < count($this->headers); ++$i) {
            $pdf->Cell($w[$i], 7, $this->headers[$i], 1, 0, 'C', 1);
        }
        $pdf->Ln();
        
        // Reset colors
        $pdf->SetFillColor(224, 235, 255);
        $pdf->SetTextColor(0);
        $pdf->SetFont('');
        
        // Add data
        $fill = 0;
        foreach ($this->data as $record) {
            for($i = 0; $i < count($record); ++$i) {
                $pdf->Cell($w[$i], 6, $record[$i], 'LR', 0, 'C', $fill);
            }
            $pdf->Ln();
            $fill = !$fill;
        }
        
        // Close table
        $pdf->Cell(array_sum($w), 0, '', 'T');
        
        // Output PDF
        $pdf->Output($this->title . '.pdf', 'D');
        exit;
    }

    private function getColumnLetter($index) {
        $letters = range('A', 'Z');
        if ($index < 26) {
            return $letters[$index];
        } else {
            $first = floor($index / 26) - 1;
            $second = $index % 26;
            return $letters[$first] . $letters[$second];
        }
    }
}

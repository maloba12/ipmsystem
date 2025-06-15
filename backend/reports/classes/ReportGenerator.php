<?php
/**
 * Report Generator Class
 * 
 * This class handles the generation of various types of reports in the system.
 * It provides methods for generating reports in different formats (PDF, Excel, CSV)
 * and manages the report generation process.
 */
class ReportGenerator {
    private $db;
    private $template_dir;
    private $output_dir;
    private $format;
    private $data;
    private $filename;
    private $date_range;

    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     * @param string $template_dir Directory containing report templates
     * @param string $output_dir Directory for generated reports
     */
    public function __construct($db, $template_dir, $output_dir) {
        $this->db = $db;
        $this->template_dir = $template_dir;
        $this->output_dir = $output_dir;
        $this->format = 'pdf'; // Default format
    }

    /**
     * Set the report format
     * 
     * @param string $format Report format (pdf, excel, csv)
     * @return ReportGenerator
     */
    public function setFormat($format) {
        $allowed_formats = ['pdf', 'excel', 'csv'];
        if (!in_array($format, $allowed_formats)) {
            throw new Exception("Invalid report format. Allowed formats: " . implode(', ', $allowed_formats));
        }
        $this->format = $format;
        return $this;
    }

    /**
     * Set the date range for the report
     * 
     * @param string $start Start date
     * @param string $end End date
     * @return ReportGenerator
     */
    public function setDateRange($start, $end) {
        $this->date_range = [
            'start' => $start,
            'end' => $end
        ];
        return $this;
    }

    /**
     * Set the report data
     * 
     * @param array $data Report data
     * @return ReportGenerator
     */
    public function setData($data) {
        $this->data = $data;
        return $this;
    }

    /**
     * Set the output filename
     * 
     * @param string $filename Output filename
     * @return ReportGenerator
     */
    public function setFilename($filename) {
        $this->filename = $filename;
        return $this;
    }

    /**
     * Generate a report
     * 
     * @param string $template Template name
     * @return string Path to generated report
     */
    public function generate($template) {
        // Validate template exists
        $template_path = $this->template_dir . '/' . $template . '.php';
        if (!file_exists($template_path)) {
            throw new Exception("Template not found: $template");
        }

        // Prepare data
        $data = $this->prepareData();

        // Generate report based on format
        switch ($this->format) {
            case 'pdf':
                return $this->generatePDF($template_path, $data);
            case 'excel':
                return $this->generateExcel($template_path, $data);
            case 'csv':
                return $this->generateCSV($template_path, $data);
            default:
                throw new Exception("Unsupported format: {$this->format}");
        }
    }

    /**
     * Prepare report data
     * 
     * @return array Prepared data
     */
    private function prepareData() {
        $data = $this->data;
        
        // Add metadata
        $data['metadata'] = [
            'report_type' => $this->getReportType(),
            'date_range' => $this->date_range,
            'generated_at' => date('Y-m-d H:i:s')
        ];

        return $data;
    }

    /**
     * Generate PDF report
     * 
     * @param string $template_path Template path
     * @param array $data Report data
     * @return string Path to generated PDF
     */
    private function generatePDF($template_path, $data) {
        // Load HTML template
        ob_start();
        extract($data);
        include $template_path;
        $html = ob_get_clean();

        // Generate PDF using TCPDF or similar library
        require_once 'vendor/tecnickcom/tcpdf/tcpdf.php';
        
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Insurance Management System');
        $pdf->SetTitle($this->getReportType());

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 15);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 10);

        // Write HTML content
        $pdf->writeHTML($html, true, false, true, false, '');

        // Generate output file
        $output_path = $this->getOutputPath('pdf');
        $pdf->Output($output_path, 'F');

        return $output_path;
    }

    /**
     * Generate Excel report
     * 
     * @param string $template_path Template path
     * @param array $data Report data
     * @return string Path to generated Excel file
     */
    private function generateExcel($template_path, $data) {
        require_once 'vendor/phpoffice/phpspreadsheet/src/Bootstrap.php';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set document properties
        $spreadsheet->getProperties()
            ->setCreator('Insurance Management System')
            ->setLastModifiedBy('Insurance Management System')
            ->setTitle($this->getReportType())
            ->setSubject($this->getReportType())
            ->setDescription('Generated on ' . date('Y-m-d H:i:s'));

        // Add data to sheet
        $this->addExcelData($sheet, $data);

        // Generate output file
        $output_path = $this->getOutputPath('xlsx');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($output_path);

        return $output_path;
    }

    /**
     * Generate CSV report
     * 
     * @param string $template_path Template path
     * @param array $data Report data
     * @return string Path to generated CSV file
     */
    private function generateCSV($template_path, $data) {
        $output_path = $this->getOutputPath('csv');
        $fp = fopen($output_path, 'w');

        // Add headers
        fputcsv($fp, ['Report Type', $this->getReportType()]);
        fputcsv($fp, ['Generated At', date('Y-m-d H:i:s')]);
        fputcsv($fp, ['Date Range', $this->date_range['start'] . ' to ' . $this->date_range['end']]);
        fputcsv($fp, []); // Empty line

        // Add data
        $this->addCSVData($fp, $data);

        fclose($fp);
        return $output_path;
    }

    /**
     * Add data to Excel sheet
     * 
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet Worksheet
     * @param array $data Report data
     */
    private function addExcelData($sheet, $data) {
        $row = 1;

        // Add headers
        $sheet->setCellValue('A' . $row, 'Report Type');
        $sheet->setCellValue('B' . $row, $this->getReportType());
        $row++;

        $sheet->setCellValue('A' . $row, 'Generated At');
        $sheet->setCellValue('B' . $row, date('Y-m-d H:i:s'));
        $row++;

        $sheet->setCellValue('A' . $row, 'Date Range');
        $sheet->setCellValue('B' . $row, $this->date_range['start'] . ' to ' . $this->date_range['end']);
        $row += 2;

        // Add data based on report type
        switch ($this->getReportType()) {
            case 'Financial Summary':
                $this->addFinancialSummaryData($sheet, $data, $row);
                break;
            case 'Financial Transactions':
                $this->addFinancialTransactionsData($sheet, $data, $row);
                break;
            case 'Policy Performance':
                $this->addPolicyPerformanceData($sheet, $data, $row);
                break;
            case 'Client Portfolio':
                $this->addClientPortfolioData($sheet, $data, $row);
                break;
        }
    }

    /**
     * Add data to CSV file
     * 
     * @param resource $fp File pointer
     * @param array $data Report data
     */
    private function addCSVData($fp, $data) {
        // Add data based on report type
        switch ($this->getReportType()) {
            case 'Financial Summary':
                $this->addFinancialSummaryCSV($fp, $data);
                break;
            case 'Financial Transactions':
                $this->addFinancialTransactionsCSV($fp, $data);
                break;
            case 'Policy Performance':
                $this->addPolicyPerformanceCSV($fp, $data);
                break;
            case 'Client Portfolio':
                $this->addClientPortfolioCSV($fp, $data);
                break;
        }
    }

    /**
     * Get output file path
     * 
     * @param string $extension File extension
     * @return string Output file path
     */
    private function getOutputPath($extension) {
        if (!$this->filename) {
            $this->filename = $this->generateFilename();
        }
        return $this->output_dir . '/' . $this->filename . '.' . $extension;
    }

    /**
     * Generate filename
     * 
     * @return string Generated filename
     */
    private function generateFilename() {
        $timestamp = date('Ymd_His');
        $type = str_replace(' ', '_', strtolower($this->getReportType()));
        return "report_{$type}_{$timestamp}";
    }

    /**
     * Get report type
     * 
     * @return string Report type
     */
    private function getReportType() {
        return $this->data['metadata']['report_type'] ?? 'Unknown Report';
    }

    /**
     * Format currency value
     * 
     * @param float $value Currency value
     * @return string Formatted currency
     */
    public function formatCurrency($value) {
        return number_format($value, 2);
    }

    /**
     * Clean up old reports
     * 
     * @param int $days Number of days to keep reports
     */
    public function cleanupOldReports($days = 30) {
        $files = glob($this->output_dir . '/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
                    unlink($file);
                }
            }
        }
    }
} 
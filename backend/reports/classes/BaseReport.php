<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helpers/functions.php';

abstract class BaseReport {
    protected $db;
    protected $data;
    protected $template;
    protected $output;
    protected $filename;
    protected $format;
    protected $start_date;
    protected $end_date;

    public function __construct() {
        $this->db = getDBConnection();
        $this->data = [];
        $this->format = 'pdf';
    }

    abstract protected function fetchData();
    abstract protected function processData();
    abstract protected function generateReport();

    public function setDateRange($start_date, $end_date) {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        return $this;
    }

    public function setFormat($format) {
        if (!in_array($format, ['pdf', 'excel', 'csv'])) {
            throw new Exception('Invalid report format');
        }
        $this->format = $format;
        return $this;
    }

    public function setTemplate($template) {
        $template_path = __DIR__ . '/../templates/' . $template . '.php';
        if (!file_exists($template_path)) {
            throw new Exception('Template not found');
        }
        $this->template = $template_path;
        return $this;
    }

    public function setFilename($filename) {
        $this->filename = $filename;
        return $this;
    }

    protected function generateFilename() {
        if (!$this->filename) {
            $this->filename = sprintf(
                '%s_%s_%s.%s',
                strtolower(get_class($this)),
                date('Y-m-d'),
                uniqid(),
                $this->format
            );
        }
        return $this->filename;
    }

    protected function getOutputPath() {
        $filename = $this->generateFilename();
        return __DIR__ . '/../generated/' . $filename;
    }

    protected function saveReport() {
        $output_path = $this->getOutputPath();
        
        // Create directory if it doesn't exist
        if (!file_exists(dirname($output_path))) {
            mkdir(dirname($output_path), 0777, true);
        }

        // Save the report
        file_put_contents($output_path, $this->output);

        // Log the report generation
        logActivity(
            $_SESSION['user_id'] ?? null,
            'report_generated',
            "Generated report: {$this->filename}"
        );

        return $output_path;
    }

    protected function cleanupOldReports($days = 30) {
        $directory = __DIR__ . '/../generated/';
        $files = glob($directory . '*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
                    unlink($file);
                }
            }
        }
    }

    public function execute() {
        try {
            // Fetch and process data
            $this->fetchData();
            $this->processData();

            // Generate the report
            $this->generateReport();

            // Save the report
            $output_path = $this->saveReport();

            // Cleanup old reports
            $this->cleanupOldReports();

            return [
                'success' => true,
                'filename' => $this->filename,
                'path' => $output_path,
                'format' => $this->format
            ];

        } catch (Exception $e) {
            error_log("Error generating report: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    protected function formatCurrency($amount) {
        return number_format($amount, 2, '.', ',');
    }

    protected function formatDate($date) {
        return date('Y-m-d', strtotime($date));
    }

    protected function formatDateTime($datetime) {
        return date('Y-m-d H:i:s', strtotime($datetime));
    }

    protected function calculatePercentage($value, $total) {
        if ($total == 0) return 0;
        return round(($value / $total) * 100, 2);
    }

    protected function getDateRangeSQL() {
        if ($this->start_date && $this->end_date) {
            return "BETWEEN ? AND ?";
        }
        return "";
    }

    protected function getDateRangeParams() {
        if ($this->start_date && $this->end_date) {
            return [$this->start_date, $this->end_date];
        }
        return [];
    }
} 
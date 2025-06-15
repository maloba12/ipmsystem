<?php
require_once __DIR__ . '/BaseReport.php';

class FinancialReport extends BaseReport {
    private $report_type;
    private $group_by;
    private $include_details;

    public function __construct() {
        parent::__construct();
        $this->report_type = 'summary';
        $this->group_by = 'month';
        $this->include_details = false;
    }

    public function setReportType($type) {
        if (!in_array($type, ['summary', 'detailed', 'comparison'])) {
            throw new Exception('Invalid report type');
        }
        $this->report_type = $type;
        return $this;
    }

    public function setGroupBy($group) {
        if (!in_array($group, ['day', 'week', 'month', 'quarter', 'year'])) {
            throw new Exception('Invalid group by value');
        }
        $this->group_by = $group;
        return $this;
    }

    public function setIncludeDetails($include) {
        $this->include_details = (bool)$include;
        return $this;
    }

    protected function fetchData() {
        $date_range = $this->getDateRangeSQL();
        $params = $this->getDateRangeParams();

        // Fetch premium income
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(payment_date, ?) as period,
                SUM(amount) as total_amount,
                COUNT(*) as transaction_count,
                payment_method
            FROM payments 
            WHERE payment_type = 'premium' 
            AND payment_date {$date_range}
            GROUP BY period, payment_method
            ORDER BY payment_date ASC
        ");
        $stmt->execute(array_merge([$this->getDateFormat()], $params));
        $this->data['premium_income'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch claim payments
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(payment_date, ?) as period,
                SUM(amount) as total_amount,
                COUNT(*) as claim_count,
                payment_method
            FROM payments 
            WHERE payment_type = 'claim' 
            AND payment_date {$date_range}
            GROUP BY period, payment_method
            ORDER BY payment_date ASC
        ");
        $stmt->execute(array_merge([$this->getDateFormat()], $params));
        $this->data['claim_payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch payment method distribution
        $stmt = $this->db->prepare("
            SELECT 
                payment_method,
                COUNT(*) as count,
                SUM(amount) as total_amount
            FROM payments 
            WHERE payment_date {$date_range}
            GROUP BY payment_method
        ");
        $stmt->execute($params);
        $this->data['payment_methods'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($this->include_details) {
            // Fetch detailed premium transactions
            $stmt = $this->db->prepare("
                SELECT 
                    p.*,
                    c.first_name,
                    c.last_name,
                    pol.policy_number
                FROM payments p
                JOIN clients c ON p.client_id = c.id
                JOIN policies pol ON p.policy_id = pol.id
                WHERE p.payment_type = 'premium' 
                AND p.payment_date {$date_range}
                ORDER BY p.payment_date DESC
            ");
            $stmt->execute($params);
            $this->data['premium_details'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch detailed claim transactions
            $stmt = $this->db->prepare("
                SELECT 
                    p.*,
                    c.first_name,
                    c.last_name,
                    cl.claim_number
                FROM payments p
                JOIN clients c ON p.client_id = c.id
                JOIN claims cl ON p.claim_id = cl.id
                WHERE p.payment_type = 'claim' 
                AND p.payment_date {$date_range}
                ORDER BY p.payment_date DESC
            ");
            $stmt->execute($params);
            $this->data['claim_details'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    protected function processData() {
        // Calculate totals
        $this->data['totals'] = [
            'premium_income' => array_sum(array_column($this->data['premium_income'], 'total_amount')),
            'claim_payments' => array_sum(array_column($this->data['claim_payments'], 'total_amount')),
            'net_income' => 0
        ];
        $this->data['totals']['net_income'] = $this->data['totals']['premium_income'] - $this->data['totals']['claim_payments'];

        // Calculate payment method percentages
        $total_payments = array_sum(array_column($this->data['payment_methods'], 'count'));
        foreach ($this->data['payment_methods'] as &$method) {
            $method['percentage'] = $this->calculatePercentage($method['count'], $total_payments);
        }

        // Process period-wise data
        $this->processPeriodData('premium_income');
        $this->processPeriodData('claim_payments');

        // Add report metadata
        $this->data['metadata'] = [
            'report_type' => $this->report_type,
            'group_by' => $this->group_by,
            'date_range' => [
                'start' => $this->start_date,
                'end' => $this->end_date
            ],
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => $_SESSION['user_id'] ?? null
        ];
    }

    protected function generateReport() {
        // Set template based on report type
        $template = $this->report_type === 'detailed' ? 'financial_detailed' : 'financial_summary';
        $this->setTemplate($template);

        // Generate report based on format
        switch ($this->format) {
            case 'pdf':
                $this->generatePDF();
                break;
            case 'excel':
                $this->generateExcel();
                break;
            case 'csv':
                $this->generateCSV();
                break;
        }
    }

    private function generatePDF() {
        // TODO: Implement PDF generation using a library like TCPDF or FPDF
        $this->output = "PDF Report Generation - To be implemented";
    }

    private function generateExcel() {
        // TODO: Implement Excel generation using a library like PhpSpreadsheet
        $this->output = "Excel Report Generation - To be implemented";
    }

    private function generateCSV() {
        $output = [];
        
        // Add metadata
        $output[] = ['Report Type', $this->data['metadata']['report_type']];
        $output[] = ['Date Range', $this->data['metadata']['date_range']['start'] . ' to ' . $this->data['metadata']['date_range']['end']];
        $output[] = ['Generated At', $this->data['metadata']['generated_at']];
        $output[] = [];

        // Add summary
        $output[] = ['Financial Summary'];
        $output[] = ['Total Premium Income', $this->formatCurrency($this->data['totals']['premium_income'])];
        $output[] = ['Total Claim Payments', $this->formatCurrency($this->data['totals']['claim_payments'])];
        $output[] = ['Net Income', $this->formatCurrency($this->data['totals']['net_income'])];
        $output[] = [];

        // Add payment method distribution
        $output[] = ['Payment Method Distribution'];
        foreach ($this->data['payment_methods'] as $method) {
            $output[] = [
                $method['payment_method'],
                $method['count'],
                $this->formatCurrency($method['total_amount']),
                $method['percentage'] . '%'
            ];
        }
        $output[] = [];

        // Add period-wise data
        $output[] = ['Period-wise Premium Income'];
        foreach ($this->data['premium_income'] as $period) {
            $output[] = [
                $period['period'],
                $this->formatCurrency($period['total_amount']),
                $period['transaction_count']
            ];
        }
        $output[] = [];

        $output[] = ['Period-wise Claim Payments'];
        foreach ($this->data['claim_payments'] as $period) {
            $output[] = [
                $period['period'],
                $this->formatCurrency($period['total_amount']),
                $period['claim_count']
            ];
        }

        // Convert to CSV
        $this->output = '';
        foreach ($output as $row) {
            $this->output .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }
    }

    private function processPeriodData($type) {
        $periods = [];
        foreach ($this->data[$type] as $record) {
            $period = $record['period'];
            if (!isset($periods[$period])) {
                $periods[$period] = [
                    'total_amount' => 0,
                    'count' => 0,
                    'methods' => []
                ];
            }
            $periods[$period]['total_amount'] += $record['total_amount'];
            $periods[$period]['count'] += $record['transaction_count'];
            $periods[$period]['methods'][$record['payment_method']] = [
                'amount' => $record['total_amount'],
                'count' => $record['transaction_count']
            ];
        }
        $this->data[$type . '_by_period'] = $periods;
    }

    private function getDateFormat() {
        switch ($this->group_by) {
            case 'day':
                return '%Y-%m-%d';
            case 'week':
                return '%Y-%u';
            case 'month':
                return '%Y-%m';
            case 'quarter':
                return '%Y-%m';
            case 'year':
                return '%Y';
            default:
                return '%Y-%m';
        }
    }
} 
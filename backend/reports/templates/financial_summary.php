<?php
/**
 * Financial Summary Report Template
 * 
 * This template is used to generate a summary of financial data including:
 * - Premium income
 * - Claim payments
 * - Net income
 * - Payment method distribution
 * - Period-wise breakdown
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Financial Summary Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .metadata {
            margin-bottom: 20px;
            color: #666;
        }
        .summary {
            margin-bottom: 30px;
        }
        .summary-item {
            margin-bottom: 10px;
        }
        .summary-label {
            font-weight: bold;
            display: inline-block;
            width: 200px;
        }
        .summary-value {
            display: inline-block;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .percentage {
            color: #666;
        }
        .positive {
            color: green;
        }
        .negative {
            color: red;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Financial Summary Report</h1>
        <div class="metadata">
            <p>Report Type: <?php echo htmlspecialchars($data['metadata']['report_type']); ?></p>
            <p>Date Range: <?php echo htmlspecialchars($data['metadata']['date_range']['start']); ?> to <?php echo htmlspecialchars($data['metadata']['date_range']['end']); ?></p>
            <p>Generated At: <?php echo htmlspecialchars($data['metadata']['generated_at']); ?></p>
        </div>
    </div>

    <div class="summary">
        <div class="section-title">Financial Summary</div>
        <div class="summary-item">
            <span class="summary-label">Total Premium Income:</span>
            <span class="summary-value"><?php echo $this->formatCurrency($data['totals']['premium_income']); ?></span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Total Claim Payments:</span>
            <span class="summary-value"><?php echo $this->formatCurrency($data['totals']['claim_payments']); ?></span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Net Income:</span>
            <span class="summary-value <?php echo $data['totals']['net_income'] >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo $this->formatCurrency($data['totals']['net_income']); ?>
            </span>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Payment Method Distribution</div>
        <table>
            <thead>
                <tr>
                    <th>Payment Method</th>
                    <th>Count</th>
                    <th>Total Amount</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['payment_methods'] as $method): ?>
                <tr>
                    <td><?php echo htmlspecialchars($method['payment_method']); ?></td>
                    <td><?php echo $method['count']; ?></td>
                    <td><?php echo $this->formatCurrency($method['total_amount']); ?></td>
                    <td class="percentage"><?php echo $method['percentage']; ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Period-wise Premium Income</div>
        <table>
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Total Amount</th>
                    <th>Transaction Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['premium_income'] as $period): ?>
                <tr>
                    <td><?php echo htmlspecialchars($period['period']); ?></td>
                    <td><?php echo $this->formatCurrency($period['total_amount']); ?></td>
                    <td><?php echo $period['transaction_count']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Period-wise Claim Payments</div>
        <table>
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Total Amount</th>
                    <th>Claim Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['claim_payments'] as $period): ?>
                <tr>
                    <td><?php echo htmlspecialchars($period['period']); ?></td>
                    <td><?php echo $this->formatCurrency($period['total_amount']); ?></td>
                    <td><?php echo $period['claim_count']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html> 
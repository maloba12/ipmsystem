<?php
/**
 * Financial Transactions Report Template
 * 
 * This template is used to generate detailed transaction reports including:
 * - Premium payments
 * - Claim payments
 * - Transaction details
 * - Client information
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Financial Transactions Report</title>
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
        .status {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        .status-completed {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .status-pending {
            background-color: #fcf8e3;
            color: #8a6d3b;
        }
        .status-failed {
            background-color: #f2dede;
            color: #a94442;
        }
        .client-info {
            background-color: #f9f9f9;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .transaction-details {
            margin-top: 10px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 4px;
        }
        .transaction-meta {
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Financial Transactions Report</h1>
        <div class="metadata">
            <p>Report Type: <?php echo htmlspecialchars($data['metadata']['report_type']); ?></p>
            <p>Date Range: <?php echo htmlspecialchars($data['metadata']['date_range']['start']); ?> to <?php echo htmlspecialchars($data['metadata']['date_range']['end']); ?></p>
            <p>Generated At: <?php echo htmlspecialchars($data['metadata']['generated_at']); ?></p>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Premium Payments</div>
        <?php foreach ($data['premium_payments'] as $payment): ?>
        <div class="client-info">
            <strong>Client:</strong> <?php echo htmlspecialchars($payment['client_name']); ?><br>
            <strong>Policy Number:</strong> <?php echo htmlspecialchars($payment['policy_number']); ?>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($payment['transaction_id']); ?></td>
                    <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                    <td><?php echo $this->formatCurrency($payment['amount']); ?></td>
                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                    <td>
                        <span class="status status-<?php echo strtolower($payment['status']); ?>">
                            <?php echo htmlspecialchars($payment['status']); ?>
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="transaction-details">
            <div class="transaction-meta">
                <strong>Reference:</strong> <?php echo htmlspecialchars($payment['reference']); ?><br>
                <strong>Notes:</strong> <?php echo htmlspecialchars($payment['notes']); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="section">
        <div class="section-title">Claim Payments</div>
        <?php foreach ($data['claim_payments'] as $claim): ?>
        <div class="client-info">
            <strong>Client:</strong> <?php echo htmlspecialchars($claim['client_name']); ?><br>
            <strong>Claim Number:</strong> <?php echo htmlspecialchars($claim['claim_number']); ?><br>
            <strong>Policy Number:</strong> <?php echo htmlspecialchars($claim['policy_number']); ?>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($claim['transaction_id']); ?></td>
                    <td><?php echo htmlspecialchars($claim['payment_date']); ?></td>
                    <td><?php echo $this->formatCurrency($claim['amount']); ?></td>
                    <td><?php echo htmlspecialchars($claim['payment_method']); ?></td>
                    <td>
                        <span class="status status-<?php echo strtolower($claim['status']); ?>">
                            <?php echo htmlspecialchars($claim['status']); ?>
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="transaction-details">
            <div class="transaction-meta">
                <strong>Claim Type:</strong> <?php echo htmlspecialchars($claim['claim_type']); ?><br>
                <strong>Reference:</strong> <?php echo htmlspecialchars($claim['reference']); ?><br>
                <strong>Notes:</strong> <?php echo htmlspecialchars($claim['notes']); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="section">
        <div class="section-title">Summary</div>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Count</th>
                    <th>Total Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Premium Payments</td>
                    <td><?php echo $data['summary']['premium_count']; ?></td>
                    <td><?php echo $this->formatCurrency($data['summary']['premium_total']); ?></td>
                </tr>
                <tr>
                    <td>Claim Payments</td>
                    <td><?php echo $data['summary']['claim_count']; ?></td>
                    <td><?php echo $this->formatCurrency($data['summary']['claim_total']); ?></td>
                </tr>
                <tr class="total-row">
                    <td>Net Total</td>
                    <td><?php echo $data['summary']['total_count']; ?></td>
                    <td><?php echo $this->formatCurrency($data['summary']['net_total']); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html> 
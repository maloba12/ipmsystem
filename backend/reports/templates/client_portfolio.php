<?php
/**
 * Client Portfolio Report Template
 * 
 * This template is used to generate client portfolio reports including:
 * - Client demographics
 * - Policy portfolio
 * - Payment history
 * - Claims history
 * - Risk assessment
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Client Portfolio Report</title>
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
        .status-active {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .status-expired {
            background-color: #f2dede;
            color: #a94442;
        }
        .status-pending {
            background-color: #fcf8e3;
            color: #8a6d3b;
        }
        .client-info {
            background-color: #f9f9f9;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        .client-info h2 {
            margin-top: 0;
            color: #333;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .info-item {
            margin-bottom: 10px;
        }
        .info-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 16px;
            color: #333;
        }
        .risk-indicator {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 10px;
        }
        .risk-low {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .risk-medium {
            background-color: #fcf8e3;
            color: #8a6d3b;
        }
        .risk-high {
            background-color: #f2dede;
            color: #a94442;
        }
        .chart-container {
            margin: 20px 0;
            padding: 15px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Client Portfolio Report</h1>
        <div class="metadata">
            <p>Report Type: <?php echo htmlspecialchars($data['metadata']['report_type']); ?></p>
            <p>Generated At: <?php echo htmlspecialchars($data['metadata']['generated_at']); ?></p>
        </div>
    </div>

    <div class="client-info">
        <h2>Client Information</h2>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Client Name</div>
                <div class="info-value"><?php echo htmlspecialchars($data['client']['name']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Client ID</div>
                <div class="info-value"><?php echo htmlspecialchars($data['client']['client_id']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Risk Profile</div>
                <div class="info-value">
                    <?php echo htmlspecialchars($data['client']['risk_profile']); ?>
                    <span class="risk-indicator risk-<?php echo strtolower($data['client']['risk_level']); ?>">
                        <?php echo htmlspecialchars($data['client']['risk_level']); ?>
                    </span>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Date of Birth</div>
                <div class="info-value"><?php echo htmlspecialchars($data['client']['dob']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Contact Number</div>
                <div class="info-value"><?php echo htmlspecialchars($data['client']['contact']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Email</div>
                <div class="info-value"><?php echo htmlspecialchars($data['client']['email']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Address</div>
                <div class="info-value"><?php echo htmlspecialchars($data['client']['address']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Client Since</div>
                <div class="info-value"><?php echo htmlspecialchars($data['client']['client_since']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Total Policies</div>
                <div class="info-value"><?php echo number_format($data['client']['total_policies']); ?></div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Policy Portfolio</div>
        <table>
            <thead>
                <tr>
                    <th>Policy Number</th>
                    <th>Product Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Premium</th>
                    <th>Status</th>
                    <th>Coverage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['policies'] as $policy): ?>
                <tr>
                    <td><?php echo htmlspecialchars($policy['policy_number']); ?></td>
                    <td><?php echo htmlspecialchars($policy['product_type']); ?></td>
                    <td><?php echo htmlspecialchars($policy['start_date']); ?></td>
                    <td><?php echo htmlspecialchars($policy['end_date']); ?></td>
                    <td><?php echo $this->formatCurrency($policy['premium']); ?></td>
                    <td>
                        <span class="status status-<?php echo strtolower($policy['status']); ?>">
                            <?php echo htmlspecialchars($policy['status']); ?>
                        </span>
                    </td>
                    <td><?php echo $this->formatCurrency($policy['coverage']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Payment History</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Policy Number</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                    <th>Reference</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['payments'] as $payment): ?>
                <tr>
                    <td><?php echo htmlspecialchars($payment['date']); ?></td>
                    <td><?php echo htmlspecialchars($payment['policy_number']); ?></td>
                    <td><?php echo $this->formatCurrency($payment['amount']); ?></td>
                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                    <td>
                        <span class="status status-<?php echo strtolower($payment['status']); ?>">
                            <?php echo htmlspecialchars($payment['status']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($payment['reference']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Claims History</div>
        <table>
            <thead>
                <tr>
                    <th>Claim Number</th>
                    <th>Policy Number</th>
                    <th>Date Filed</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['claims'] as $claim): ?>
                <tr>
                    <td><?php echo htmlspecialchars($claim['claim_number']); ?></td>
                    <td><?php echo htmlspecialchars($claim['policy_number']); ?></td>
                    <td><?php echo htmlspecialchars($claim['date_filed']); ?></td>
                    <td><?php echo htmlspecialchars($claim['type']); ?></td>
                    <td><?php echo $this->formatCurrency($claim['amount']); ?></td>
                    <td>
                        <span class="status status-<?php echo strtolower($claim['status']); ?>">
                            <?php echo htmlspecialchars($claim['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Risk Assessment</div>
        <table>
            <thead>
                <tr>
                    <th>Factor</th>
                    <th>Score</th>
                    <th>Weight</th>
                    <th>Weighted Score</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['risk_assessment'] as $factor): ?>
                <tr>
                    <td><?php echo htmlspecialchars($factor['factor']); ?></td>
                    <td><?php echo number_format($factor['score'], 1); ?></td>
                    <td><?php echo number_format($factor['weight'], 1); ?></td>
                    <td><?php echo number_format($factor['weighted_score'], 1); ?></td>
                    <td><?php echo htmlspecialchars($factor['notes']); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="3">Total Risk Score</td>
                    <td><?php echo number_format($data['total_risk_score'], 1); ?></td>
                    <td>
                        <span class="risk-indicator risk-<?php echo strtolower($data['risk_level']); ?>">
                            <?php echo htmlspecialchars($data['risk_level']); ?>
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html> 
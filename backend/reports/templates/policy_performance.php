<?php
/**
 * Policy Performance Report Template
 * 
 * This template is used to generate policy performance reports including:
 * - Policy metrics
 * - Claim statistics
 * - Premium collection
 * - Policy status distribution
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Policy Performance Report</title>
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
        .metric-card {
            background-color: #f9f9f9;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            display: inline-block;
            width: calc(33.33% - 20px);
            margin-right: 20px;
            vertical-align: top;
        }
        .metric-title {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .metric-change {
            font-size: 12px;
            margin-top: 5px;
        }
        .positive {
            color: green;
        }
        .negative {
            color: red;
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
        <h1>Policy Performance Report</h1>
        <div class="metadata">
            <p>Report Type: <?php echo htmlspecialchars($data['metadata']['report_type']); ?></p>
            <p>Date Range: <?php echo htmlspecialchars($data['metadata']['date_range']['start']); ?> to <?php echo htmlspecialchars($data['metadata']['date_range']['end']); ?></p>
            <p>Generated At: <?php echo htmlspecialchars($data['metadata']['generated_at']); ?></p>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Key Performance Metrics</div>
        <div class="metric-card">
            <div class="metric-title">Total Active Policies</div>
            <div class="metric-value"><?php echo number_format($data['metrics']['total_policies']); ?></div>
            <div class="metric-change <?php echo $data['metrics']['policy_growth'] >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo $data['metrics']['policy_growth']; ?>% vs previous period
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Premium Collection Rate</div>
            <div class="metric-value"><?php echo number_format($data['metrics']['collection_rate'], 1); ?>%</div>
            <div class="metric-change <?php echo $data['metrics']['collection_change'] >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo $data['metrics']['collection_change']; ?>% vs previous period
            </div>
        </div>
        <div class="metric-card">
            <div class="metric-title">Claims Ratio</div>
            <div class="metric-value"><?php echo number_format($data['metrics']['claims_ratio'], 1); ?>%</div>
            <div class="metric-change <?php echo $data['metrics']['claims_ratio_change'] <= 0 ? 'positive' : 'negative'; ?>">
                <?php echo $data['metrics']['claims_ratio_change']; ?>% vs previous period
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Policy Status Distribution</div>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Count</th>
                    <th>Percentage</th>
                    <th>Total Premium</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['policy_status'] as $status): ?>
                <tr>
                    <td>
                        <span class="status status-<?php echo strtolower($status['status']); ?>">
                            <?php echo htmlspecialchars($status['status']); ?>
                        </span>
                    </td>
                    <td><?php echo number_format($status['count']); ?></td>
                    <td><?php echo number_format($status['percentage'], 1); ?>%</td>
                    <td><?php echo $this->formatCurrency($status['total_premium']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Premium Collection by Product</div>
        <table>
            <thead>
                <tr>
                    <th>Product Type</th>
                    <th>Active Policies</th>
                    <th>Total Premium</th>
                    <th>Average Premium</th>
                    <th>Collection Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['product_performance'] as $product): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['product_type']); ?></td>
                    <td><?php echo number_format($product['active_policies']); ?></td>
                    <td><?php echo $this->formatCurrency($product['total_premium']); ?></td>
                    <td><?php echo $this->formatCurrency($product['average_premium']); ?></td>
                    <td><?php echo number_format($product['collection_rate'], 1); ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Claims Analysis</div>
        <table>
            <thead>
                <tr>
                    <th>Product Type</th>
                    <th>Total Claims</th>
                    <th>Total Claim Amount</th>
                    <th>Average Claim</th>
                    <th>Claims Ratio</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['claims_analysis'] as $claim): ?>
                <tr>
                    <td><?php echo htmlspecialchars($claim['product_type']); ?></td>
                    <td><?php echo number_format($claim['total_claims']); ?></td>
                    <td><?php echo $this->formatCurrency($claim['total_amount']); ?></td>
                    <td><?php echo $this->formatCurrency($claim['average_claim']); ?></td>
                    <td><?php echo number_format($claim['claims_ratio'], 1); ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Policy Renewal Analysis</div>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Expiring Policies</th>
                    <th>Renewed</th>
                    <th>Renewal Rate</th>
                    <th>Lost Premium</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['renewal_analysis'] as $renewal): ?>
                <tr>
                    <td><?php echo htmlspecialchars($renewal['month']); ?></td>
                    <td><?php echo number_format($renewal['expiring_policies']); ?></td>
                    <td><?php echo number_format($renewal['renewed']); ?></td>
                    <td><?php echo number_format($renewal['renewal_rate'], 1); ?>%</td>
                    <td><?php echo $this->formatCurrency($renewal['lost_premium']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html> 
<?php
session_start();
require_once '../backend/helpers/functions.php';
require_once __DIR__ . '/includes/navigation.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Check user role
if (!in_array($_SESSION['user_role'], ['Admin', 'Insurance Agent'])) {
    header('Location: login.php?error=Unauthorized+access');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - IPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/your-code.js"></script>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/includes/navigation.php'; ?>
    <div class="container mx-auto p-6">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - IPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Analytics Dashboard</h1>
            <a href="javascript:history.back()" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Policy Statistics -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Policy Statistics</h2>
                <canvas id="policyChart"></canvas>
            </div>

            <!-- Revenue Analytics -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Revenue Analytics</h2>
                <canvas id="revenueChart"></canvas>
            </div>

            <!-- Client Distribution -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold mb-4">Client Distribution</h2>
                <canvas id="clientChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Sample data - replace with actual data from backend
        const policyData = {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Policies Issued',
                data: [12, 19, 3, 5, 2, 3],
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        };

        const revenueData = {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Revenue',
                data: [65, 59, 80, 81, 56, 55],
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgb(54, 162, 235)',
                borderWidth: 1
            }]
        };

        const clientData = {
            labels: ['New', 'Returning', 'Lapsed'],
            datasets: [{
                data: [300, 500, 200],
                backgroundColor: [
                    'rgb(255, 99, 132)',
                    'rgb(54, 162, 235)',
                    'rgb(255, 205, 86)'
                ]
            }]
        };

        // Initialize charts
        new Chart(document.getElementById('policyChart'), {
            type: 'line',
            data: policyData
        });

        new Chart(document.getElementById('revenueChart'), {
            type: 'bar',
            data: revenueData
        });

        new Chart(document.getElementById('clientChart'), {
            type: 'pie',
            data: clientData
        });
    </script>

    <script src="https://kit.fontawesome.com/your-code.js"></script>
</body>
</html>

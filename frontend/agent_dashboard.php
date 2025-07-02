<?php
session_start();

// Check if user is logged in and is an agent
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Insurance Agent') {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard - ZamSure Insurance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1e40af;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .chart-container {
            height: 300px;
            background: white;
            border-radius: 0.5rem;
            padding: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <aside class="fixed left-0 top-0 h-full w-64 bg-white shadow-lg">
        <div class="p-4 border-b">
            <h1 class="text-xl font-bold text-blue-600">ZamSure Insurance</h1>
            <p class="text-sm text-gray-600">Agent Dashboard</p>
        </div>
        <nav class="mt-4">
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-home mr-3"></i> Dashboard
            </a>
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-user mr-3"></i> My Profile
            </a>
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-file-alt mr-3"></i> Policies
            </a>
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-chart-line mr-3"></i> Performance
            </a>
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-users mr-3"></i> Clients
            </a>
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-cog mr-3"></i> Settings
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="ml-64 p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Welcome, <?php echo htmlspecialchars($_SESSION['user']['first_name']); ?>!</h2>
            <div class="flex items-center space-x-4">
                <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
                <button onclick="showFileModal('upload')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    <i class="fas fa-upload mr-2"></i>Upload Document
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="dashboard-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-value">125</div>
                        <div class="stat-label">Active Policies</div>
                    </div>
                    <i class="fas fa-file-contract text-2xl text-blue-600"></i>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-value">20</div>
                        <div class="stat-label">Uploaded Files</div>
                    </div>
                    <i class="fas fa-file-upload text-2xl text-green-600"></i>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-value">28</div>
                        <div class="stat-label">New Clients</div>
                    </div>
                    <i class="fas fa-users text-2xl text-green-600"></i>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-value">K12,450</div>
                        <div class="stat-label">Monthly Revenue</div>
                    </div>
                    <i class="fas fa-dollar-sign text-2xl text-yellow-600"></i>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="chart-container">
                <h3 class="text-lg font-semibold mb-4">Policy Distribution</h3>
                <canvas id="policyChart"></canvas>
            </div>
            <div class="chart-container">
                <h3 class="text-lg font-semibold mb-4">Performance Overview</h3>
                <canvas id="performanceChart"></canvas>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="mt-6">
            <h3 class="text-lg font-semibold mb-4">Recent Activities</h3>
            <div class="space-y-4">
                <div class="dashboard-card">
                    <div class="flex justify-between items-center">
                        <div>
                            <h4 class="font-medium">New Policy Issued</h4>
                            <p class="text-sm text-gray-600">Client: John Doe</p>
                            <p class="text-sm text-gray-600">Type: Health Insurance</p>
                        </div>
                        <span class="px-3 py-1 text-sm bg-green-100 text-green-800 rounded-full">New</span>
                    </div>
                </div>
                <div class="dashboard-card">
                    <div class="flex justify-between items-center">
                        <div>
                            <h4 class="font-medium">Claim Processed</h4>
                            <p class="text-sm text-gray-600">Client: Jane Smith</p>
                            <p class="text-sm text-gray-600">Amount: K5,000</p>
                        </div>
                        <span class="px-3 py-1 text-sm bg-blue-100 text-blue-800 rounded-full">Completed</span>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sample data for charts
        const policyData = {
            labels: ['Health', 'Auto', 'Home', 'Life'],
            datasets: [{
                data: [30, 25, 20, 25],
                backgroundColor: ['#4F46E5', '#10B981', '#F59E0B', '#EF4444'],
            }]
        };

        const performanceData = {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Revenue',
                data: [1200, 1900, 1500, 2500, 2000, 3000],
                borderColor: '#4F46E5',
                tension: 0.1
            }]
        };

        // Initialize charts
        new Chart(document.getElementById('policyChart'), {
            type: 'doughnut',
            data: policyData,
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        new Chart(document.getElementById('performanceChart'), {
            type: 'line',
            data: performanceData,
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>

    <!-- File Upload Modal -->
    <div id="fileModal" class="fixed inset-0 bg-black bg-opacity-50 hidden">
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold mb-4">Upload File</h3>
                <form id="fileForm" onsubmit="event.preventDefault(); uploadFile();">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">File</label>
                        <input type="file" id="fileInput" name="file" accept=".pdf,.xlsx,.xls" 
                               class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('fileModal')" 
                                class="px-4 py-2 text-gray-700 hover:text-gray-900">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/dashboard.js"></script>
</body>
</html>

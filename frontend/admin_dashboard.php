<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ZamSure Insurance</title>
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

        .admin-action {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: white;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .admin-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .admin-icon {
            font-size: 1.5rem;
            margin-right: 1rem;
            background: #f3f4f6;
            padding: 0.75rem;
            border-radius: 0.5rem;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-success {
            background: #dcfce7;
            color: #166534;
        }

        .status-warning {
            background: #fef3c7;
            color: #d97706;
        }

        .status-error {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <aside class="fixed left-0 top-0 h-full w-64 bg-white shadow-lg">
        <div class="p-4 border-b">
            <h1 class="text-xl font-bold text-blue-600">ZamSure Insurance</h1>
            <p class="text-sm text-gray-600">Admin Dashboard</p>
        </div>
        <nav class="mt-4">
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-home mr-3"></i> Dashboard
            </a>
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-users mr-3"></i> Users
            </a>
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-file-alt mr-3"></i> Policies
            </a>
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-chart-line mr-3"></i> Analytics
            </a>
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-cog mr-3"></i> Settings
            </a>
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-shield-alt mr-3"></i> Security
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="ml-64 p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Welcome, Admin!</h2>
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
                        <div class="stat-value">35</div>
                        <div class="stat-label">Uploaded Files</div>
                    </div>
                    <i class="fas fa-file-upload text-2xl text-green-600"></i>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-value">K125,000</div>
                        <div class="stat-label">Monthly Revenue</div>
                    </div>
                    <i class="fas fa-dollar-sign text-2xl text-yellow-600"></i>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="admin-action">
                    <div class="admin-icon bg-blue-100 text-blue-600">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div>
                        <h4 class="font-medium">Create New User</h4>
                        <p class="text-sm text-gray-600">Add new client or agent</p>
                    </div>
                </div>
                <div class="admin-action">
                    <div class="admin-icon bg-green-100 text-green-600">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div>
                        <h4 class="font-medium">Manage Policies</h4>
                        <p class="text-sm text-gray-600">View and update policies</p>
                    </div>
                </div>
                <div class="admin-action">
                    <div class="admin-icon bg-purple-100 text-purple-600">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <h4 class="font-medium">View Analytics</h4>
                        <p class="text-sm text-gray-600">View performance metrics</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-4">System Status</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="dashboard-card">
                    <h4 class="font-medium mb-4">Server Status</h4>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span>Database Connection</span>
                            <span class="status-badge status-success">Healthy</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>API Services</span>
                            <span class="status-badge status-success">Online</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>Email Service</span>
                            <span class="status-badge status-success">Active</span>
                        </div>
                    </div>
                </div>
                <div class="dashboard-card">
                    <h4 class="font-medium mb-4">Recent Activity</h4>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <div>
                                <h5 class="font-medium">New User Registration</h5>
                                <p class="text-sm text-gray-600">John Doe - Client</p>
                            </div>
                            <span class="text-sm text-gray-600">5 minutes ago</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <div>
                                <h5 class="font-medium">Policy Update</h5>
                                <p class="text-sm text-gray-600">Policy #H123456</p>
                            </div>
                            <span class="text-sm text-gray-600">15 minutes ago</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <div>
                                <h5 class="font-medium">Claim Processed</h5>
                                <p class="text-sm text-gray-600">Claim #C7890</p>
                            </div>
                            <span class="text-sm text-gray-600">30 minutes ago</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-4">Analytics Overview</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="chart-container">
                    <h4 class="font-medium mb-4">Revenue Growth</h4>
                    <canvas id="revenueChart"></canvas>
                </div>
                <div class="chart-container">
                    <h4 class="font-medium mb-4">User Distribution</h4>
                    <canvas id="userDistributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- File Management -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-4">File Management</h3>
            <div class="bg-white rounded-lg shadow">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Uploaded By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="files-table" class="bg-white divide-y divide-gray-200">
                            <!-- Files will be loaded here via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-4">Recent Activities</h3>
            <div class="bg-white rounded-lg shadow">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">Created new policy</td>
                                <td class="px-6 py-4 whitespace-nowrap">John Doe</td>
                                <td class="px-6 py-4 whitespace-nowrap">5 minutes ago</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">Updated client information</td>
                                <td class="px-6 py-4 whitespace-nowrap">Jane Smith</td>
                                <td class="px-6 py-4 whitespace-nowrap">1 hour ago</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sample data for charts
        const revenueData = {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Revenue',
                data: [120000, 190000, 150000, 250000, 200000, 300000],
                borderColor: '#4F46E5',
                tension: 0.1
            }]
        };

        const userDistributionData = {
            labels: ['Clients', 'Agents', 'Admins'],
            datasets: [{
                data: [750, 300, 2],
                backgroundColor: ['#4F46E5', '#10B981', '#F59E0B'],
            }]
        };

        // Initialize charts
        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: revenueData,
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        new Chart(document.getElementById('userDistributionChart'), {
            type: 'doughnut',
            data: userDistributionData,
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

    </body>
</html>

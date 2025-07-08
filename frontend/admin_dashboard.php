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

        /* Form validation styles */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.25rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1e40af;
            box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.1);
        }

        .form-group .error {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
            animation: fadeIn 0.3s ease-in;
        }

        .form-group.success .success {
            color: #166534;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
            animation: fadeIn 0.3s ease-in;
        }

        .form-group.error input,
        .form-group.error select {
            border-color: #dc2626;
            background-color: #fee2e2;
        }

        .form-group.success input,
        .form-group.success select {
            border-color: #166534;
            background-color: #dcfce7;
        }

        .form-group.error .error,
        .form-group.success .success {
            display: block;
        }

        /* Loading states */
        .loading {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .loading::after {
            content: '...';
            animation: dots 1s steps(5, end) infinite;
        }

        @keyframes dots {
            0%, 20% { content: '...'; }
            40% { content: '..'; }
            60% { content: '.'; }
            80%, 100% { content: ''; }
        }

        /* Animation classes */
        .animate-fade-in {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Modal styles */
        .modal {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }

        .modal-content {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            max-width: 500px;
            margin: 2rem auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
            <a href="admin_dashboard.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-home mr-3"></i> Dashboard
            </a>
            <a href="users.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-users mr-3"></i> Users
            </a>
            <a href="policies.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-file-alt mr-3"></i> Policies
            </a>
            <a href="analytics.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
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
                        <h4 class="font-medium">Add New Client</h4>
                        <p class="text-sm text-gray-600">Add a new client user</p>
                        <button onclick="showAddClientModal()" class="mt-2 px-4 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">Add Client</button>
                    </div>
                </div>
                <div class="admin-action">
                    <div class="admin-icon bg-green-100 text-green-600">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div>
                        <h4 class="font-medium">Add New Agent</h4>
                        <p class="text-sm text-gray-600">Add a new insurance agent</p>
                        <button onclick="showAddAgentModal()" class="mt-2 px-4 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700">Add Agent</button>
                    </div>
                </div>
                <div class="admin-action">
                    <div class="admin-icon bg-purple-100 text-purple-600">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h4 class="font-medium">View Users</h4>
                        <p class="text-sm text-gray-600">Manage all users</p>
                        <button onclick="showUsersList()" class="mt-2 px-4 py-1 text-sm bg-purple-600 text-white rounded hover:bg-purple-700">View Users</button>
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
                        <i class="fas fa-cog"></i>
                    </div>
                    <div>
                        <h3 class="font-medium">Settings</h3>
                        <p class="text-gray-500">Configure system settings</p>
                    </div>
                </button>
            </div>
        </div>

        <!-- Insurance Types Management -->
        <div class="p-4 border-b">
            <h1 class="text-xl font-bold mb-4">Insurance Types</h1>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="font-medium">Available Insurance Types</h2>
                    <button onclick="showAddInsuranceModal()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        <i class="fas fa-plus mr-2"></i>Add New Type
                    </button>
                </div>
                <div id="insuranceTypesList" class="space-y-4">
                    <!-- Insurance types will be loaded here via JavaScript -->
                </div>
                        <p class="text-sm text-gray-600">View system reports</p>
                    </div>
                </a>
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

        <!-- Analytics Overview -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-4">Analytics Overview</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="dashboard-card">
                    <h4 class="font-medium mb-4">Total Revenue</h4>
                    <div class="stat-value">$120,000</div>
                    <div class="stat-label">Current month revenue</div>
                </div>
                <div class="dashboard-card">
                    <h4 class="font-medium mb-4">Active Users</h4>
                    <div class="stat-value">450</div>
                    <div class="stat-label">Active user count</div>
                </div>
                <div class="dashboard-card">
                    <h4 class="font-medium mb-4">Policy Count</h4>
                    <div class="stat-value">820</div>
                    <div class="stat-label">Total active policies</div>
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

    <script>
        // Analytics data loading
        async function loadAnalytics() {
            try {

            // Test bar chart
            createChart(document.getElementById('testBarChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May'],
                    datasets: [{
                        label: 'Policies',
                        data: [12, 19, 3, 5, 2]
                    }]
                },
                options: {
                    title: {
                        display: true,
                        text: 'Test Bar Chart'
                    }
                }
            });
        }

        // Initialize charts with error handling
        async function initializeCharts() {
            try {
                // Get chart data from API
                const response = await fetch('../backend/api/analytics.php');
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error('Failed to fetch chart data');
                }

                // Add test charts for verification
                testChartTypes();

                // Initialize each chart with proper error handling
                data.charts.forEach(chartData => {
                    try {
                        const ctx = document.getElementById(chartData.id)?.getContext('2d');
                        if (!ctx) {
                            console.error(`Canvas element not found for chart: ${chartData.id}`);
                            showError(`Chart element not found: ${chartData.id}`);
                            return;
                        }

                        // Validate chart configuration
                        if (!chartData.type || !chartData.data) {
                            console.error('Invalid chart data:', chartData);
                            showError('Invalid chart configuration');
                            return;
                        }

                        // Create chart with proper configuration
                        const chart = createChart(ctx, {
                            type: chartData.type,
                            data: chartData.data,
                            options: chartData.options || {}
                        });

                        if (!chart) {
                            console.error(`Failed to create chart: ${chartData.id}`);
                            showError(`Failed to create chart: ${chartData.id}`);
                        }
                    } catch (error) {
                        console.error(`Error initializing chart ${chartData.id}:`, error);
                        showError(`Error initializing chart: ${error.message}`);
                    }
                });
            } catch (error) {
                console.error('Error initializing charts:', error);
                showError(`Failed to initialize charts: ${error.message}`);
            }
        }
        // Utility functions for validation and error handling
        function showError(message, element = null) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded relative';
            errorDiv.innerHTML = `
                <p>${message}</p>
                <button onclick="this.parentElement.remove()" class="absolute right-2 top-2 text-red-500 hover:text-red-700">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            if (element) {
                element.parentElement.insertBefore(errorDiv, element);
            } else {
                document.body.insertBefore(errorDiv, document.body.firstChild);
            }
        }

        function showSuccess(message, element = null) {
            const successDiv = document.createElement('div');
            successDiv.className = 'bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded relative';
            successDiv.innerHTML = `
                <p>${message}</p>
                <button onclick="this.parentElement.remove()" class="absolute right-2 top-2 text-green-500 hover:text-green-700">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            if (element) {
                element.parentElement.insertBefore(successDiv, element);
            } else {
                document.body.insertBefore(successDiv, document.body.firstChild);
            }
        }

        function validateForm(formId) {
            const form = document.getElementById(formId);
            let isValid = true;
            let validationErrors = [];
            
            // Reset all validation states
            form.querySelectorAll('.form-group').forEach(group => {
                group.classList.remove('error', 'success');
            });

            // Get all required fields
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                const group = field.closest('.form-group');
                if (!field.value.trim()) {
                    group.classList.add('error');
                    validationErrors.push(`Please fill in the ${field.name} field`);
                    isValid = false;
                }
            });

            // Validate specific field types
            form.querySelectorAll('input[type="email"]').forEach(email => {
                const group = email.closest('.form-group');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email.value.trim())) {
                    group.classList.add('error');
                    validationErrors.push('Please enter a valid email address');
                    isValid = false;
                }
            });

            // Validate numbers
            form.querySelectorAll('input[type="number"]').forEach(number => {
                const group = number.closest('.form-group');
                if (number.value.trim() && isNaN(Number(number.value))) {
                    group.classList.add('error');
                    validationErrors.push('Please enter a valid number');
                    isValid = false;
                }
            });

            // Display all validation errors at once
            if (validationErrors.length > 0) {
                const errorMessages = validationErrors.join('<br>');
                showError(errorMessages, form);
            }

            return isValid;
        }

        // Modal functions
        function showAddClientModal() {
            document.getElementById('addClientModal').classList.remove('hidden');
            // Reset form validation state
            document.getElementById('addClientForm').querySelectorAll('.form-group').forEach(group => {
                group.classList.remove('error', 'success');
            });
        }

        function closeAddClientModal() {
            document.getElementById('addClientModal').classList.add('hidden');
        }

        async function addClient() {
            const form = document.getElementById('addClientForm');
            const formData = new FormData(form);
            try {
                const response = await fetch('../backend/api/clients.php?action=add', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showNotification('success', 'Client added successfully');
                    closeAddClientModal();
                    // Refresh client list
                    await fetchClients();
                } else {
                    showNotification('error', result.message);
                }
            } catch (error) {
                showNotification('error', 'Failed to add client');
                console.error('Error:', error);
            }
        }

        function showNotification(type, message) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg ${
                type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
            }`;
            notification.innerHTML = `
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            ${type === 'success' ? `<path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>` : `<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>`}
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium">${message}</h3>
                    </div>
                    <button type="button" class="ml-auto inline-flex p-1 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500" onclick="this.parentElement.remove()">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
            `;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        async function fetchInsuranceTypes() {
            try {
                const response = await fetch('../backend/api/insurance_types.php?action=list');
                const data = await response.json();
                if (data.success) {
                    const typesList = document.getElementById('insuranceTypesList');
                    typesList.innerHTML = data.insurance_types.map(type => `
                        <div class="bg-white p-4 rounded-lg shadow mb-4">
                            <div class="flex justify-between items-center">
                                <div>
                                    <h3 class="font-medium">${type.name}</h3>
                                    <p class="text-sm text-gray-500">${type.description}</p>
                                    <div class="mt-2">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                            type.status === 'active' ? 'bg-green-100 text-green-800' :
                                            type.status === 'inactive' ? 'bg-yellow-100 text-yellow-800' :
                                            'bg-red-100 text-red-800'
                                        }">
                                            ${type.status.charAt(0).toUpperCase() + type.status.slice(1)}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <button onclick="editInsuranceType(${type.id})" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteInsuranceType(${type.id})" class="text-red-600 hover:text-red-800">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mt-4 text-sm text-gray-500">
                                <p>Created: ${type.created_at}</p>
                                <p>Services: ${type.service_count}</p>
                                <p>Policies: ${type.policy_count}</p>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (error) {
                console.error('Error fetching insurance types:', error);
                showNotification('error', 'Failed to load insurance types');
            }
        }

        async function showAddInsuranceModal() {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50';
            modal.innerHTML = `
                <div class="fixed inset-0 flex items-center justify-center p-4">
                    <div class="bg-white rounded-lg p-6 max-w-md w-full">
                        <h2 class="text-lg font-medium mb-4">Add Insurance Type</h2>
                        <form id="addInsuranceForm" onsubmit="return addInsuranceType(event)">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1">Name</label>
                                    <input type="text" name="name" required class="w-full px-3 py-2 border rounded-lg">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Description</label>
                                    <textarea name="description" required class="w-full px-3 py-2 border rounded-lg"></textarea>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Status</label>
                                    <select name="status" class="w-full px-3 py-2 border rounded-lg">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="archived">Archived</option>
                                    </select>
                                </div>
                                <div>
                                    <button type="submit" class="w-full bg-blue-500 text-white py-2 px-4 rounded-lg hover:bg-blue-600">Add Insurance Type</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        async function addInsuranceType(event) {
            event.preventDefault();
            const form = document.getElementById('addInsuranceForm');
            
            // Validate form before submission
            if (!validateForm('addInsuranceForm')) {
                return;
            }

            const name = form.elements['name'].value.trim();
            const description = form.elements['description'].value.trim();
            const status = form.elements['status'].value;

            // Client-side validation
            if (!name) {
                showToast('error', 'Insurance name is required');
                return;
            }
            if (name.length > 100) {
                showToast('error', 'Insurance name must be no more than 100 characters');
                return;
            }
            if (description.length > 500) {
                showToast('error', 'Description must be no more than 500 characters');
                return;
            }
            if (!status || !['active', 'inactive', 'archived'].includes(status)) {
                showToast('error', 'Invalid status value. Must be active, inactive, or archived');
                return;
            }

            // Add CSRF token
            const formData = new FormData(form);
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch('../backend/api/insurance_types.php?action=add', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-Token': csrfToken
                    }
                });
                const data = await response.json();

                if (data.success) {
                    showToast('success', 'Insurance type added successfully');
                    closeAddModal();
                    loadInsuranceTypes();
                } else {
                    showToast('error', data.error || 'Failed to add insurance type');
                }
            } catch (error) {
                showToast('error', 'An error occurred while adding insurance type: ' + error.message);
            }
        }

        async function editInsuranceType(typeId) {
            try {
                const response = await fetch(`../backend/api/insurance_types.php?action=get&id=${typeId}`);
                const data = await response.json();
                
                if (data.success) {
                    const modal = document.createElement('div');
                    modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4';
                    modal.innerHTML = `
                        <div class="bg-white rounded-lg p-6 max-w-md w-full">
                            <h3 class="text-lg font-semibold mb-4">Edit Insurance Type</h3>
                            <form id="editInsuranceForm" onsubmit="event.preventDefault(); updateInsuranceType(${typeId}, event)">
                                <input type="hidden" name="id" value="${typeId}">
                                
                                <!-- Name field with validation -->
                                <div class="form-group" name="name">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                    <input type="text" name="name" value="${data.data.name}" 
                                           class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           placeholder="Enter insurance type name (2-100 characters)"
                                           pattern="^[a-zA-Z0-9\s\-\_\.]+$"
                                           required>
                                    <div class="error"></div>
                                </div>

                                <!-- Description field with validation -->
                                <div class="form-group" name="description">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <textarea name="description" rows="3"
                                              class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                              placeholder="Enter description (10-500 characters)"
                                              required>${data.data.description}</textarea>
                                    <div class="error"></div>
                                </div>

                                <!-- Status field with validation -->
                                <div class="form-group" name="status">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select name="status" 
                                            class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            required>
                                        <option value="" disabled ${!data.data.status ? 'selected' : ''}>Select status</option>
                                        <option value="active" ${data.data.status === 'active' ? 'selected' : ''}>Active</option>
                                        <option value="inactive" ${data.data.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                        <option value="archived" ${data.data.status === 'archived' ? 'selected' : ''}>Archived</option>
                                    </select>
                                    <div class="error"></div>
                                </div>

                                <!-- Error message container -->
                                <div class="error-message"></div>

                                <!-- Action buttons -->
                                <div class="flex justify-end space-x-3 mt-6">
                                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-700 hover:text-gray-900">Cancel</button>
                                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    `;
                    document.body.appendChild(modal);

                    // Add real-time validation
                    const form = document.getElementById('editInsuranceForm');
                    const nameInput = form.querySelector('input[name="name"]');
                    const descInput = form.querySelector('textarea[name="description"]');
                    const statusSelect = form.querySelector('select[name="status"]');

                    // Name validation
                    nameInput.addEventListener('input', () => {
                        const nameGroup = nameInput.closest('.form-group');
                        const name = nameInput.value.trim();
                        
                        if (!name) {
                            nameGroup.classList.add('error');
                            nameGroup.querySelector('.error').textContent = 'Name is required';
                        } else if (!/^[a-zA-Z0-9\s\-\_\.]+$/.test(name)) {
                            nameGroup.classList.add('error');
                            nameGroup.querySelector('.error').textContent = 'Name can only contain letters, numbers, spaces, hyphens, underscores, and periods';
                        } else if (name.length < 2 || name.length > 100) {
                            nameGroup.classList.add('error');
                            nameGroup.querySelector('.error').textContent = 'Name must be between 2 and 100 characters';
                        } else {
                            nameGroup.classList.remove('error');
                            nameGroup.querySelector('.error').textContent = '';
                        }
                    });

                    // Description validation
                    descInput.addEventListener('input', () => {
                        const descGroup = descInput.closest('.form-group');
                        const desc = descInput.value.trim();
                        
                        if (!desc) {
                            descGroup.classList.add('error');
                            descGroup.querySelector('.error').textContent = 'Description is required';
                        } else if (desc.length < 10 || desc.length > 500) {
                            descGroup.classList.add('error');
                            descGroup.querySelector('.error').textContent = 'Description must be between 10 and 500 characters';
                        } else {
                            descGroup.classList.remove('error');
                            descGroup.querySelector('.error').textContent = '';
                        }
                    });

                    // Status validation
                    statusSelect.addEventListener('change', () => {
                        const statusGroup = statusSelect.closest('.form-group');
                        const status = statusSelect.value;
                        
                        if (!status) {
                            statusGroup.classList.add('error');
                            statusGroup.querySelector('.error').textContent = 'Status is required';
                        } else if (!['active', 'inactive', 'archived'].includes(status.toLowerCase())) {
                            statusGroup.classList.add('error');
                            statusGroup.querySelector('.error').textContent = 'Invalid status value';
                        } else {
                            statusGroup.classList.remove('error');
                            statusGroup.querySelector('.error').textContent = '';
                        }
                    });
                }
            } catch (error) {
                showError('Error loading insurance type details');
            }
        }

        async function updateInsuranceType(typeId, event) {
            event.preventDefault();
            const form = document.getElementById('editInsuranceForm');
            
            // Validate form before submission
            if (!validateForm('editInsuranceForm')) {
                return;
            }
            const id = form.elements['id'].value;
            const name = form.elements['name'].value.trim();
            const description = form.elements['description'].value.trim();
            const status = form.elements['status'].value;

            // Client-side validation
            if (!id || isNaN(id)) {
                showToast('error', 'Invalid insurance type ID');
                return;
            }
            if (!name) {
                showToast('error', 'Insurance name is required');
                return;
            }
            if (name.length > 100) {
                showToast('error', 'Insurance name must be no more than 100 characters');
                return;
            }
            if (description.length > 500) {
                showToast('error', 'Description must be no more than 500 characters');
                return;
            }
            if (!status || !['active', 'inactive', 'archived'].includes(status)) {
                showToast('error', 'Invalid status value. Must be active, inactive, or archived');
                return;
            }

            // Add CSRF token
            const formData = new FormData(form);
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch('../backend/api/insurance_types.php?action=update', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-Token': csrfToken
                    }
                });
                const data = await response.json();

                if (data.success) {
                    showToast('success', 'Insurance type updated successfully');
                    closeEditModal();
                    loadInsuranceTypes();
                } else {
                    showToast('error', data.error || 'Failed to update insurance type');
                }
            } catch (error) {
                showToast('error', 'An error occurred while updating insurance type: ' + error.message);
            }
        }

        function closeEditModal() {
            const modal = document.querySelector('.fixed.inset-0.bg-black');
            if (modal) modal.remove();
        }

        async function deleteInsuranceType(id) {
            // Client-side validation
            if (!id || isNaN(id)) {
                showToast('error', 'Invalid insurance type ID');
                return;
            }

            if (!confirm('Are you sure you want to delete this insurance type? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('../backend/api/insurance_types.php?action=delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': csrfToken
                    },
                    body: new URLSearchParams({
                        id: id,
                        csrf_token: csrfToken
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showToast('success', 'Insurance type deleted successfully');
                    loadInsuranceTypes();
                } else {
                    showToast('error', data.error || 'Failed to delete insurance type');
                }
            } catch (error) {
                showToast('error', 'An error occurred while deleting insurance type: ' + error.message);
            }
        }

        // Form validation functions
        function validateForm(formId) {
            const form = document.getElementById(formId);
            let isValid = true;
            let validationErrors = [];

            // Clear all previous errors
            form.querySelectorAll('.error').forEach(error => {
                error.closest('.form-group').classList.remove('error');
                error.style.display = 'none';
            });

            // Validate name
            const name = form.elements['name'].value.trim();
            const nameGroup = form.querySelector('div[name="name"]');
            if (!name) {
                validationErrors.push('Name is required');
                nameGroup.classList.add('error');
            } else if (!/^[a-zA-Z0-9\s\-\_\.]+$/.test(name)) {
                validationErrors.push('Name can only contain letters, numbers, spaces, hyphens, underscores, and periods');
                nameGroup.classList.add('error');
            } else if (name.length < 2 || name.length > 100) {
                validationErrors.push('Name must be between 2 and 100 characters');
                nameGroup.classList.add('error');
            }

            // Validate description
            const description = form.elements['description'].value.trim();
            const descGroup = form.querySelector('div[name="description"]');
            if (!description) {
                validationErrors.push('Description is required');
                descGroup.classList.add('error');
            } else if (description.length < 10 || description.length > 500) {
                validationErrors.push('Description must be between 10 and 500 characters');
                descGroup.classList.add('error');
            }

            // Validate status
            const status = form.elements['status'].value;
            const statusGroup = form.querySelector('div[name="status"]');
            if (status && !['active', 'inactive', 'archived'].includes(status.toLowerCase())) {
                validationErrors.push('Status must be one of: active, inactive, or archived');
                statusGroup.classList.add('error');
            }

            if (validationErrors.length > 0) {
                isValid = false;
                // Show individual field errors
                validationErrors.forEach(error => {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error';
                    errorDiv.textContent = error;
                    form.querySelector('.error-message').appendChild(errorDiv);
                });
            }

            return isValid;
        }

        async function addInsuranceType(event) {
            event.preventDefault();
            const form = document.getElementById('addInsuranceForm');

            // Validate form
            if (!validateForm('addInsuranceForm')) {
                return;
            }

            try {
                // Show loading state
                form.classList.add('loading');
                const submitButton = form.querySelector('button[type="submit"]');
                const originalText = submitButton.textContent;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

                // Get form data
                const formData = new FormData(form);
                const name = formData.get('name').trim();
                const description = formData.get('description').trim();
                const status = formData.get('status');

                // Additional validation for backend constraints
                if (await checkNameExists(name)) {
                    showError('An insurance type with this name already exists');
                    return;
                }

                // Prepare request
                const response = await fetch('../backend/api/insurance_types.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'add',
                        name: name,
                        description: description,
                        status: status,
                        csrf_token: document.querySelector('input[name="csrf_token"]').value
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('addInsuranceModal').classList.add('hidden');
                    form.reset();
                    await loadInsuranceTypes();
                    showSuccess('Insurance type added successfully');
                } else {
                    showError(data.message);
                }
            } catch (error) {
                showError('An error occurred while adding the insurance type');
            } finally {
                // Reset form state
                form.classList.remove('loading');
                const submitButton = form.querySelector('button[type="submit"]');
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }
        }

        async function checkNameExists(name) {
            try {
                const response = await fetch(`../backend/api/insurance_types.php?check_name=${encodeURIComponent(name)}`);
                const data = await response.json();
                return data.exists;
            } catch (error) {
                console.error('Error checking name existence:', error);
                return false;
            }
        }

        // Helper function to show errors
        function showError(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'fixed top-4 right-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg shadow-md z-50 animate-fade-in';
            errorDiv.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span class="flex-grow">${message}</span>
                    <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.body.appendChild(errorDiv);
            
            // Add animation classes
            errorDiv.classList.add('animate-slide-in');
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                errorDiv.classList.add('animate-slide-out');
                setTimeout(() => errorDiv.remove(), 300);
            }, 5000);
        }

        async function deleteInsuranceType(typeId) {
            // Get type name for confirmation message
            const typeRow = document.querySelector(`tr[data-type-id="${typeId}"]`);
            const typeName = typeRow ? typeRow.querySelector('.type-name').textContent : 'this insurance type';

            // Show confirmation dialog with more details
            const confirmDelete = confirm(`
                Are you sure you want to delete ${typeName}?\n\n
                This action cannot be undone.\n
                ${typeRow ? `\nActive policies: ${typeRow.querySelector('.policy-count').textContent}` : ''}
            `);

            if (!confirmDelete) {
                return;
            }

            try {
                // Show loading state
                const deleteButton = document.querySelector(`button[data-type-id="${typeId}"]`);
                const originalText = deleteButton.textContent;
                deleteButton.disabled = true;
                deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

                const response = await fetch('../backend/api/insurance_types.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        action: 'delete',
                        id: typeId,
                        csrf_token: document.querySelector('input[name="csrf_token"]').value
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    await loadInsuranceTypes();
                    showSuccess('Insurance type deleted successfully');
                } else {
                    showError(data.message);
                }
            } catch (error) {
                showError('An error occurred while deleting the insurance type');
            } finally {
                // Reset button state
                deleteButton.disabled = false;
                deleteButton.textContent = originalText;
            }
        }

        document.getElementById('typeForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            try {
                const formData = new FormData(e.target);
                const response = await fetch('../backend/api/insurance_types.php?action=add', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    closeModal();
                    loadInsuranceTypes();
                }
            } catch (error) {
                console.error('Error adding type:', error);
            }
        });

        // Load types when page loads
        loadInsuranceTypes();
    </script>
</body>
</html>

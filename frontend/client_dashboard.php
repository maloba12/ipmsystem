<?php
session_start();

// Check if user is logged in and is a client
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Client') {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - ZamSure Insurance</title>
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

        .policy-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }

        .policy-status {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-expired {
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
            <p class="text-sm text-gray-600">Client Dashboard</p>
        </div>
        <nav class="mt-4">
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-home mr-3"></i> Dashboard
            </a>
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-user mr-3"></i> My Profile
            </a>
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-file-alt mr-3"></i> My Policies
            </a>
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-file-invoice mr-3"></i> Payments
            </a>
            <a href="settings.php" class="flex items-center p-3 text-gray-700 hover:bg-gray-100">
                <i class="fas fa-file-medical mr-3"></i> Claims
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
                        <div class="stat-value">10</div>
                        <div class="stat-label">Uploaded Files</div>
                    </div>
                    <i class="fas fa-file-upload text-2xl text-green-600"></i>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-value">K250</div>
                        <div class="stat-label">Monthly Premium</div>
                    </div>
                    <i class="fas fa-money-bill-wave text-2xl text-yellow-600"></i>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-value">12/31/2025</div>
                        <div class="stat-label">Next Payment Due</div>
                    </div>
                    <i class="fas fa-calendar-alt text-2xl text-green-600"></i>
                </div>
            </div>
        </div>

        <!-- Policies Section -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-4">My Policies</h3>
            <div class="space-y-4">
                <div class="policy-card">
                    <div class="flex justify-between items-center">
                        <div>
                            <h4 class="font-medium">Health Insurance</h4>
                            <p class="text-sm text-gray-600">Policy Number: H123456</p>
                            <p class="text-sm text-gray-600">Coverage: K500,000</p>
                        </div>
                        <span class="policy-status status-active">Active</span>
                    </div>
                </div>
                <div class="policy-card">
                    <div class="flex justify-between items-center">
                        <div>
                            <h4 class="font-medium">Auto Insurance</h4>
                            <p class="text-sm text-gray-600">Policy Number: A789456</p>
                            <p class="text-sm text-gray-600">Coverage: K100,000</p>
                        </div>
                        <span class="policy-status status-active">Active</span>
                    </div>
                </div>
                <div class="policy-card">
                    <div class="flex justify-between items-center">
                        <div>
                            <h4 class="font-medium">Home Insurance</h4>
                            <p class="text-sm text-gray-600">Policy Number: H987654</p>
                            <p class="text-sm text-gray-600">Coverage: K200,000</p>
                        </div>
                        <span class="policy-status status-pending">Pending</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Claims -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-4">Recent Claims</h3>
            <div class="space-y-4">
                <div class="policy-card">
                    <div class="flex justify-between items-center">
                        <div>
                            <h4 class="font-medium">Medical Emergency</h4>
                            <p class="text-sm text-gray-600">Amount: K1,250.00</p>
                            <p class="text-sm text-gray-600">Status: Approved</p>
                        </div>
                        <span class="policy-status status-active">Completed</span>
                    </div>
                </div>
                <div class="policy-card">
                    <div class="flex justify-between items-center">
                        <div>
                            <h4 class="font-medium">Auto Accident</h4>
                            <p class="text-sm text-gray-600">Amount: $2,500</p>
                            <p class="text-sm text-gray-600">Status: Under Review</p>
                        </div>
                        <span class="policy-status status-pending">Pending</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <button class="w-full bg-blue-600 text-white px-4 py-3 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-file-medical mr-2"></i> File a Claim
                </button>
                <button class="w-full bg-green-600 text-white px-4 py-3 rounded-lg hover:bg-green-700">
                    <i class="fas fa-file-invoice mr-2"></i> Make Payment
                </button>
                <button class="w-full bg-yellow-600 text-white px-4 py-3 rounded-lg hover:bg-yellow-700">
                    <i class="fas fa-envelope mr-2"></i> Contact Support
                </button>
                <button class="w-full bg-purple-600 text-white px-4 py-3 rounded-lg hover:bg-purple-700">
                    <i class="fas fa-file-alt mr-2"></i> View Documents
                </button>
            </div>
        </div>
    <!-- File Upload Modal -->
    <div id="fileModal" class="fixed inset-0 bg-black bg-opacity-50 hidden">
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold">Upload Document</h3>
                    <button onclick="hideFileModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="uploadForm" action="upload.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label for="fileUpload" class="block text-sm font-medium text-gray-700 mb-2">Select Document</label>
                        <input type="file" id="fileUpload" name="file" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                    </div>
                    <div>
                        <label for="documentType" class="block text-sm font-medium text-gray-700 mb-2">Document Type</label>
                        <select id="documentType" name="documentType" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                            <option value="">Select Document Type</option>
                            <option value="policy_document">Policy Document</option>
                            <option value="claim_document">Claim Documentation</option>
                            <option value="id_proof">ID Proof</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="description" name="description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Describe the document..." rows="3"></textarea>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideFileModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Upload Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/dashboard.js"></script>
    </main>
</body>
</html>

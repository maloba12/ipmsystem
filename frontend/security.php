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
if ($_SESSION['user_role'] !== 'Admin') {
    header('Location: login.php?error=Unauthorized+access');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - IPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/your-code.js"></script>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/includes/navigation.php'; ?>
    <div class="container mx-auto p-6">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - IPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="container mx-auto p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Security Settings</h1>
            <a href="javascript:history.back()" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="space-y-6">
                <!-- Password Policy -->
                <div>
                    <h2 class="text-xl font-semibold mb-4">Password Policy</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Minimum Length</label>
                            <input type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Require Special Characters</label>
                            <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option>Yes</option>
                                <option>No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Password Expiration (days)</label>
                            <input type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Session Settings -->
                <div>
                    <h2 class="text-xl font-semibold mb-4">Session Settings</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Session Timeout (minutes)</label>
                            <input type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Auto Logout After Inactivity</label>
                            <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option>Yes</option>
                                <option>No</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- API Security -->
                <div>
                    <h2 class="text-xl font-semibold mb-4">API Security</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Rate Limiting Enabled</label>
                            <select class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option>Yes</option>
                                <option>No</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Max Requests per Hour</label>
                            <input type="number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="mt-8">
                    <button class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/your-code.js"></script>
</body>
</html>

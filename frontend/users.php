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
    <title>Users Management - IPMS</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/your-code.js"></script>
    <script src="js/users.js"></script>
</head>
<body class="bg-gray-50">
    <?php include __DIR__ . '/includes/navigation.php'; ?>
    <div class="container mx-auto p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Users Management</h1>
            <a href="javascript:history.back()" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Users List</h2>
                <a href="#" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Add New User
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-2">Name</th>
                            <th class="px-4 py-2">Email</th>
                            <th class="px-4 py-2">Role</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- User data will be populated here -->
                        <tr>
                            <td class="border px-4 py-2">Loading...</td>
                            <td class="border px-4 py-2">Loading...</td>
                            <td class="border px-4 py-2">Loading...</td>
                            <td class="border px-4 py-2">Loading...</td>
                            <td class="border px-4 py-2">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/your-code.js"></script>
</body>
</html>
